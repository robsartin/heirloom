<?php
declare(strict_types=1);

namespace Heirloom\Controllers;

use Heirloom\Auth;
use Heirloom\Config;
use Heirloom\Database;
use Heirloom\SiteSettings;
use Heirloom\Template;

class GalleryController
{
    public function __construct(private Database $db, private Auth $auth, private SiteSettings $settings) {}

    public function index(): void
    {
        if (!$this->auth->isLoggedIn()) {
            Template::render('landing', ['auth' => $this->auth]);
            return;
        }

        $perPage = $this->settings->getInt('gallery_per_page', 12);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $search = trim($_GET['q'] ?? '');
        $sort = $_GET['sort'] ?? 'newest';

        // Build WHERE clause
        $where = 'p.awarded_to IS NULL';
        $params = [];
        if ($search !== '') {
            $where .= ' AND (p.title LIKE :search OR p.description LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $total = (int) $this->db->scalar(
            "SELECT COUNT(*) FROM paintings p WHERE $where",
            $params
        );
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        // Determine ORDER BY
        $orderBy = match ($sort) {
            'wanted' => 'interest_count DESC',
            'title'  => 'p.title ASC',
            default  => 'p.created_at DESC',
        };

        $paintings = $this->db->fetchAll(
            "SELECT p.*, (SELECT COUNT(*) FROM interests i WHERE i.painting_id = p.id) AS interest_count
             FROM paintings p
             WHERE $where
             ORDER BY $orderBy
             LIMIT :limit OFFSET :offset",
            $params + [':limit' => $perPage, ':offset' => $offset]
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

        Template::setGlobal('ogTitle', $this->settings->get('site_name', SiteSettings::DEFAULT_SITE_NAME));

        Template::render('gallery', [
            'paintings' => $paintings,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'userInterests' => $userInterests,
            'auth' => $this->auth,
            'search' => $search,
            'sort' => $sort,
        ]);
    }

    public function show(string $id): void
    {
        $this->auth->requireLogin();

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

        Template::setGlobal('ogTitle', $painting['title']);
        Template::setGlobal('ogDescription', $painting['description'] ?? '');
        Template::setGlobal('ogImage', Config::get('APP_URL') . '/uploads/' . $painting['filename']);

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

    public function sitemapXml(): void
    {
        $appUrl = rtrim(Config::get('APP_URL', 'http://localhost:8080'), '/');

        $paintings = $this->db->fetchAll(
            'SELECT id, updated_at FROM paintings WHERE awarded_to IS NULL ORDER BY created_at DESC'
        );

        header('Content-Type: text/xml; charset=UTF-8');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // Gallery home page
        $xml .= "  <url>\n";
        $xml .= '    <loc>' . htmlspecialchars($appUrl . '/') . "</loc>\n";
        $xml .= "    <changefreq>daily</changefreq>\n";
        $xml .= "    <priority>1.0</priority>\n";
        $xml .= "  </url>\n";

        // Individual painting pages
        foreach ($paintings as $painting) {
            $loc = $appUrl . '/painting/' . $painting['id'];
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . htmlspecialchars($loc) . "</loc>\n";
            if (!empty($painting['updated_at'])) {
                $xml .= '    <lastmod>' . date('Y-m-d', strtotime($painting['updated_at'])) . "</lastmod>\n";
            }
            $xml .= "    <changefreq>weekly</changefreq>\n";
            $xml .= "    <priority>0.8</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= "</urlset>\n";

        echo $xml;
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
