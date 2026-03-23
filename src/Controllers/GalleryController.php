<?php
declare(strict_types=1);

namespace Heirloom\Controllers;

use Heirloom\Auth;
use Heirloom\Database;
use Heirloom\SiteSettings;
use Heirloom\Template;

class GalleryController
{
    public function __construct(private Database $db, private Auth $auth, private SiteSettings $settings) {}

    public function index(): void
    {
        $perPage = $this->settings->getInt('gallery_per_page', 12);
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $total = (int) $this->db->scalar(
            'SELECT COUNT(*) FROM paintings WHERE awarded_to IS NULL'
        );
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $paintings = $this->db->fetchAll(
            'SELECT p.*, (SELECT COUNT(*) FROM interests i WHERE i.painting_id = p.id) AS interest_count
             FROM paintings p
             WHERE p.awarded_to IS NULL
             ORDER BY p.created_at DESC
             LIMIT :limit OFFSET :offset',
            [':limit' => $perPage, ':offset' => $offset]
        );

        // Check which ones current user has expressed interest in
        $userInterests = [];
        if ($this->auth->isLoggedIn()) {
            $rows = $this->db->fetchAll(
                'SELECT painting_id FROM interests WHERE user_id = :uid',
                [':uid' => $this->auth->userId()]
            );
            foreach ($rows as $row) {
                $userInterests[$row['painting_id']] = true;
            }
        }

        Template::render('gallery', [
            'paintings' => $paintings,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'userInterests' => $userInterests,
            'auth' => $this->auth,
        ]);
    }

    public function show(string $id): void
    {
        $painting = $this->db->fetchOne(
            'SELECT * FROM paintings WHERE id = :id',
            [':id' => (int) $id]
        );
        if (!$painting) {
            http_response_code(404);
            echo '<h1>Painting not found</h1>';
            return;
        }

        $hasInterest = false;
        if ($this->auth->isLoggedIn()) {
            $hasInterest = (bool) $this->db->fetchOne(
                'SELECT 1 FROM interests WHERE painting_id = :pid AND user_id = :uid',
                [':pid' => (int) $id, ':uid' => $this->auth->userId()]
            );
        }

        $interestCount = (int) $this->db->scalar(
            'SELECT COUNT(*) FROM interests WHERE painting_id = :pid',
            [':pid' => (int) $id]
        );

        Template::render('painting', [
            'painting' => $painting,
            'hasInterest' => $hasInterest,
            'interestCount' => $interestCount,
            'auth' => $this->auth,
        ]);
    }

    public function expressInterest(string $id): void
    {
        $this->auth->requireLogin();

        $painting = $this->db->fetchOne(
            'SELECT * FROM paintings WHERE id = :id AND awarded_to IS NULL',
            [':id' => (int) $id]
        );
        if (!$painting) {
            header('Location: /');
            exit;
        }

        $existing = $this->db->fetchOne(
            'SELECT 1 FROM interests WHERE painting_id = :pid AND user_id = :uid',
            [':pid' => (int) $id, ':uid' => $this->auth->userId()]
        );

        $message = trim($_POST['message'] ?? '');

        if ($existing) {
            // Toggle off - remove interest
            $this->db->execute(
                'DELETE FROM interests WHERE painting_id = :pid AND user_id = :uid',
                [':pid' => (int) $id, ':uid' => $this->auth->userId()]
            );
        } else {
            $this->db->execute(
                'INSERT INTO interests (painting_id, user_id, message) VALUES (:pid, :uid, :msg)',
                [':pid' => (int) $id, ':uid' => $this->auth->userId(), ':msg' => $message]
            );
        }

        $redirect = $_POST['redirect'] ?? '/painting/' . $id;
        if (!str_starts_with($redirect, '/') || str_starts_with($redirect, '//')) {
            $redirect = '/painting/' . $id;
        }
        header('Location: ' . $redirect);
        exit;
    }

    public function myPaintings(): void
    {
        $this->auth->requireLogin();
        $userId = $this->auth->userId();

        $wanted = $this->db->fetchAll(
            'SELECT p.* FROM paintings p
             JOIN interests i ON i.painting_id = p.id
             WHERE i.user_id = :uid AND p.awarded_to IS NULL
             ORDER BY i.created_at DESC',
            [':uid' => $userId]
        );

        $awarded = $this->db->fetchAll(
            'SELECT * FROM paintings WHERE awarded_to = :uid ORDER BY awarded_at DESC',
            [':uid' => $userId]
        );

        $noLongerAvailable = $this->db->fetchAll(
            'SELECT p.* FROM paintings p
             JOIN interests i ON i.painting_id = p.id
             WHERE i.user_id = :uid AND p.awarded_to IS NOT NULL AND p.awarded_to != :uid2
             ORDER BY p.awarded_at DESC',
            [':uid' => $userId, ':uid2' => $userId]
        );

        Template::render('my-paintings', [
            'wanted' => $wanted,
            'awarded' => $awarded,
            'noLongerAvailable' => $noLongerAvailable,
            'auth' => $this->auth,
        ]);
    }
}
