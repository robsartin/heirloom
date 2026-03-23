<?php
declare(strict_types=1);

namespace Heirloom\Controllers;

use Heirloom\Auth;
use Heirloom\Database;
use Heirloom\SiteSettings;
use Heirloom\Template;
use Heirloom\Thumbnail;

/**
 * Admin-only controller for the dashboard, painting management (upload, edit, award, delete),
 * CSV data exports, and site settings management.
 */
class AdminController
{
    public function __construct(private Database $db, private Auth $auth, private SiteSettings $settings) {}

    public function dashboard(): void
    {
        $this->auth->requireAdmin();
        $perPage = $this->settings->getInt('admin_per_page', 20);

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $filter = $_GET['filter'] ?? 'available';
        $sort = $_GET['sort'] ?? 'created_at';
        $dir = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

        $allowedSorts = [
            'title' => 'p.title',
            'interest_count' => 'interest_count',
            'last_interest_at' => 'last_interest_at',
            'created_at' => 'p.created_at',
        ];
        $orderCol = $allowedSorts[$sort] ?? 'p.created_at';

        $where = match ($filter) {
            'awarded' => 'WHERE p.awarded_to IS NOT NULL',
            'wanted' => 'WHERE p.awarded_to IS NULL AND EXISTS (SELECT 1 FROM interests i2 WHERE i2.painting_id = p.id)',
            'all' => '',
            default => 'WHERE p.awarded_to IS NULL',
        };

        $total = (int) $this->db->scalar(
            "SELECT COUNT(*) FROM paintings p $where"
        );
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $paintings = $this->db->fetchAll(
            "SELECT p.*,
                (SELECT COUNT(*) FROM interests i WHERE i.painting_id = p.id) AS interest_count,
                (SELECT MAX(i3.created_at) FROM interests i3 WHERE i3.painting_id = p.id) AS last_interest_at,
                u.name AS awarded_name, u.email AS awarded_email
             FROM paintings p
             LEFT JOIN users u ON u.id = p.awarded_to
             $where
             ORDER BY $orderCol $dir
             LIMIT :limit OFFSET :offset",
            [':limit' => $perPage, ':offset' => $offset]
        );

        $stats = [
            'total_paintings' => (int) $this->db->scalar('SELECT COUNT(*) FROM paintings'),
            'available' => (int) $this->db->scalar('SELECT COUNT(*) FROM paintings WHERE awarded_to IS NULL'),
            'awarded' => (int) $this->db->scalar('SELECT COUNT(*) FROM paintings WHERE awarded_to IS NOT NULL'),
            'total_users' => (int) $this->db->scalar('SELECT COUNT(*) FROM users'),
            'total_interests' => (int) $this->db->scalar('SELECT COUNT(*) FROM interests'),
        ];

        $mostWanted = $this->db->fetchOne(
            'SELECT p.title, COUNT(i.id) AS cnt
             FROM paintings p
             JOIN interests i ON i.painting_id = p.id
             WHERE p.awarded_to IS NULL
             GROUP BY p.id
             ORDER BY cnt DESC
             LIMIT 1'
        );
        $stats['most_wanted'] = $mostWanted['title'] ?? null;

        Template::render('admin/dashboard', [
            'paintings' => $paintings,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'filter' => $filter,
            'sort' => $sort,
            'dir' => $dir,
            'stats' => $stats,
            'auth' => $this->auth,
        ]);
    }

    public function uploadForm(): void
    {
        $this->auth->requireAdmin();
        Template::render('admin/upload', [
            'auth' => $this->auth,
            'error' => $_SESSION['upload_error'] ?? null,
            'success' => $_SESSION['upload_success'] ?? null,
        ]);
        unset($_SESSION['upload_error'], $_SESSION['upload_success']);
    }

    public function upload(): void
    {
        $this->auth->requireAdmin();

        // Detect when PHP rejected the POST body as too large
        if (empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && (int) $_SERVER['CONTENT_LENGTH'] > 0) {
            $maxPost = ini_get('post_max_size');
            $_SESSION['upload_error'] = "Upload too large. The server limit is {$maxPost}. Try fewer files at once, or ask the admin to increase post_max_size/upload_max_filesize.";
            header('Location: /admin/upload');
            exit;
        }

        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($_FILES['paintings']) || !is_array($_FILES['paintings']['name'])) {
            $_SESSION['upload_error'] = 'Please select at least one image.';
            header('Location: /admin/upload');
            exit;
        }

        $uploadDir = dirname(__DIR__, 2) . '/public/uploads/';
        $allowed = ['image/jpeg', 'image/png'];
        $uploaded = 0;
        $errors = [];

        $fileCount = count($_FILES['paintings']['name']);

        // Single file with no title is an error
        if ($fileCount === 1 && $title === '') {
            $_SESSION['upload_error'] = 'Title is required for single file uploads.';
            header('Location: /admin/upload');
            exit;
        }

        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['paintings']['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }

            $tmpName = $_FILES['paintings']['tmp_name'][$i];
            $originalName = $_FILES['paintings']['name'][$i];
            $mimeType = mime_content_type($tmpName);

            if (!in_array($mimeType, $allowed, true)) {
                $errors[] = "$originalName: not a JPEG or PNG.";
                continue;
            }

            $ext = $mimeType === 'image/png' ? 'png' : 'jpg';
            $filename = bin2hex(random_bytes(16)) . '.' . $ext;

            if (!move_uploaded_file($tmpName, $uploadDir . $filename)) {
                $errors[] = "$originalName: upload failed.";
                continue;
            }

            Thumbnail::generateThumbnail(
                $uploadDir . $filename,
                $uploadDir . Thumbnail::thumbFilename($filename)
            );

            if ($fileCount === 1) {
                $paintingTitle = $title;
            } else {
                $baseName = pathinfo($originalName, PATHINFO_FILENAME);
                $paintingTitle = $title !== '' ? $title . ' - ' . $baseName : $baseName;
            }

            $this->db->execute(
                'INSERT INTO paintings (title, description, filename, original_filename) VALUES (:title, :desc, :file, :orig)',
                [':title' => $paintingTitle, ':desc' => $description, ':file' => $filename, ':orig' => $originalName]
            );
            $uploaded++;
        }

        if ($uploaded > 0) {
            $_SESSION['upload_success'] = "$uploaded painting(s) uploaded successfully." .
                ($errors ? ' Errors: ' . implode(', ', $errors) : '');
        } else {
            $_SESSION['upload_error'] = 'No paintings uploaded. ' . implode(', ', $errors);
        }
        header('Location: /admin/upload');
        exit;
    }

    public function managePainting(string $id): void
    {
        $this->auth->requireAdmin();

        $painting = $this->db->fetchOne(
            'SELECT * FROM paintings WHERE id = :id',
            [':id' => (int) $id]
        );
        if (!$painting) {
            http_response_code(404);
            echo '<h1>Painting not found</h1>';
            return;
        }

        $interests = $this->db->fetchAll(
            'SELECT i.*, u.name, u.email, u.shipping_address FROM interests i
             JOIN users u ON u.id = i.user_id
             WHERE i.painting_id = :pid
             ORDER BY i.created_at ASC',
            [':pid' => (int) $id]
        );

        $awardedUser = null;
        if ($painting['awarded_to']) {
            $awardedUser = $this->db->fetchOne(
                'SELECT id, name, email, shipping_address FROM users WHERE id = :id',
                [':id' => $painting['awarded_to']]
            );
        }

        $awardLog = $this->db->fetchAll(
            'SELECT al.*, u.name AS user_name, u.email AS user_email, adm.name AS admin_name
             FROM award_log al
             JOIN users u ON u.id = al.user_id
             JOIN users adm ON adm.id = al.awarded_by
             WHERE al.painting_id = :pid
             ORDER BY al.created_at DESC',
            [':pid' => (int) $id]
        );

        Template::render('admin/manage', [
            'painting' => $painting,
            'interests' => $interests,
            'awardedUser' => $awardedUser,
            'awardLog' => $awardLog,
            'auth' => $this->auth,
            'success' => $_SESSION['admin_success'] ?? null,
            'error' => $_SESSION['admin_error'] ?? null,
        ]);
        unset($_SESSION['admin_success'], $_SESSION['admin_error']);
    }

    public function edit(string $id): void
    {
        $this->auth->requireAdmin();

        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($title === '') {
            $_SESSION['admin_error'] = 'Title cannot be empty.';
            header('Location: /admin/painting/' . $id);
            exit;
        }

        $this->db->execute(
            'UPDATE paintings SET title = :title, description = :desc WHERE id = :id',
            [':title' => $title, ':desc' => $description, ':id' => (int) $id]
        );

        $_SESSION['admin_success'] = 'Painting updated.';
        header('Location: /admin/painting/' . $id);
        exit;
    }

    public function award(string $id): void
    {
        $this->auth->requireAdmin();

        $userIdRaw = $_POST['user_id'] ?? '';
        $userId = $userIdRaw !== '' ? (int) $userIdRaw : null;
        $adminId = $this->auth->userId();

        if ($userId) {
            $this->db->execute(
                'UPDATE paintings SET awarded_to = :uid, awarded_at = NOW() WHERE id = :id',
                [':uid' => $userId, ':id' => (int) $id]
            );
            $this->db->execute(
                'INSERT INTO award_log (painting_id, user_id, awarded_by, action) VALUES (:pid, :uid, :aid, :action)',
                [':pid' => (int) $id, ':uid' => $userId, ':aid' => $adminId, ':action' => 'awarded']
            );

            $recipient = $this->db->fetchOne(
                'SELECT email FROM users WHERE id = :id',
                [':id' => $userId]
            );
            $painting = $this->db->fetchOne(
                'SELECT title FROM paintings WHERE id = :id',
                [':id' => (int) $id]
            );
            if ($recipient && $painting) {
                $this->auth->sendAwardNotification($recipient['email'], $painting['title']);

                $losers = $this->db->fetchAll(
                    'SELECT u.email FROM interests i
                     JOIN users u ON u.id = i.user_id
                     WHERE i.painting_id = :pid AND i.user_id != :uid',
                    [':pid' => (int) $id, ':uid' => $userId]
                );
                $loserEmails = array_map(fn(array $row) => $row['email'], $losers);
                $this->auth->sendLoserNotifications($loserEmails, $painting['title']);
            }

            $_SESSION['admin_success'] = 'Painting awarded!';
        } else {
            $painting = $this->db->fetchOne('SELECT awarded_to FROM paintings WHERE id = :id', [':id' => (int) $id]);
            if ($painting && $painting['awarded_to']) {
                $this->db->execute(
                    'INSERT INTO award_log (painting_id, user_id, awarded_by, action) VALUES (:pid, :uid, :aid, :action)',
                    [':pid' => (int) $id, ':uid' => $painting['awarded_to'], ':aid' => $adminId, ':action' => 'unassigned']
                );
            }
            $this->db->execute(
                'UPDATE paintings SET awarded_to = NULL, awarded_at = NULL, tracking_number = NULL WHERE id = :id',
                [':id' => (int) $id]
            );
            $_SESSION['admin_success'] = 'Painting unassigned.';
        }

        header('Location: /admin/painting/' . $id);
        exit;
    }

    public function updateTracking(string $id): void
    {
        $this->auth->requireAdmin();

        $tracking = trim($_POST['tracking_number'] ?? '');
        $this->db->execute(
            'UPDATE paintings SET tracking_number = :tn WHERE id = :id',
            [':tn' => $tracking !== '' ? $tracking : null, ':id' => (int) $id]
        );

        $_SESSION['admin_success'] = 'Tracking number updated.';
        header('Location: /admin/painting/' . $id);
        exit;
    }

    public function delete(string $id): void
    {
        $this->auth->requireAdmin();

        $painting = $this->db->fetchOne(
            'SELECT filename FROM paintings WHERE id = :id',
            [':id' => (int) $id]
        );

        if ($painting) {
            $uploadDir = dirname(__DIR__, 2) . '/public/uploads/';
            @unlink($uploadDir . $painting['filename']);
            @unlink($uploadDir . Thumbnail::thumbFilename($painting['filename']));
            $this->db->execute('DELETE FROM paintings WHERE id = :id', [':id' => (int) $id]);
        }

        header('Location: /admin');
        exit;
    }

    // ---------------------------------------------------------------
    // CSV exports
    // ---------------------------------------------------------------

    /** Return the header row for the paintings CSV. */
    public static function paintingsCsvHeader(): array
    {
        return [
            'ID',
            'Title',
            'Description',
            'Filename',
            'Interest Count',
            'Awarded To Name',
            'Awarded To Email',
            'Awarded At',
            'Tracking Number',
            'Created At',
        ];
    }

    /** Query all paintings with interest count and awarded-user info. */
    public static function paintingsCsvRows(Database $db): array
    {
        return $db->fetchAll(
            "SELECT p.id, p.title, p.description, p.filename,
                    (SELECT COUNT(*) FROM interests i WHERE i.painting_id = p.id) AS interest_count,
                    u.name AS awarded_name, u.email AS awarded_email,
                    p.awarded_at, p.tracking_number, p.created_at
             FROM paintings p
             LEFT JOIN users u ON u.id = p.awarded_to
             ORDER BY p.id"
        );
    }

    /** Stream paintings CSV to the browser. */
    public function exportPaintings(): void
    {
        $this->auth->requireAdmin();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="paintings_' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, self::paintingsCsvHeader());

        foreach (self::paintingsCsvRows($this->db) as $row) {
            fputcsv($out, [
                $row['id'],
                $row['title'],
                $row['description'],
                $row['filename'],
                $row['interest_count'],
                $row['awarded_name'] ?? '',
                $row['awarded_email'] ?? '',
                $row['awarded_at'] ?? '',
                $row['tracking_number'] ?? '',
                $row['created_at'],
            ]);
        }

        fclose($out);
        exit;
    }

    /** Return the header row for the users CSV. */
    public static function usersCsvHeader(): array
    {
        return [
            'ID',
            'Email',
            'Name',
            'Shipping Address',
            'Interest Count',
            'Awarded Painting Count',
            'Is Admin',
            'Created At',
        ];
    }

    /** Query all users with interest count and awarded painting count. */
    public static function usersCsvRows(Database $db): array
    {
        return $db->fetchAll(
            "SELECT u.id, u.email, u.name, u.shipping_address,
                    (SELECT COUNT(*) FROM interests i WHERE i.user_id = u.id) AS interest_count,
                    (SELECT COUNT(*) FROM paintings p WHERE p.awarded_to = u.id) AS awarded_count,
                    u.is_admin, u.created_at
             FROM users u
             ORDER BY u.id"
        );
    }

    /** Stream users CSV to the browser. */
    public function exportUsers(): void
    {
        $this->auth->requireAdmin();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="users_' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, self::usersCsvHeader());

        foreach (self::usersCsvRows($this->db) as $row) {
            fputcsv($out, [
                $row['id'],
                $row['email'],
                $row['name'],
                $row['shipping_address'] ?? '',
                $row['interest_count'],
                $row['awarded_count'],
                $row['is_admin'] ? 'Yes' : 'No',
                $row['created_at'],
            ]);
        }

        fclose($out);
        exit;
    }

    public function settingsForm(): void
    {
        $this->auth->requireAdmin();

        $allSettings = $this->settings->getAll();

        Template::render('admin/settings', [
            'settings' => $allSettings,
            'auth' => $this->auth,
            'success' => $_SESSION['admin_success'] ?? null,
            'error' => $_SESSION['admin_error'] ?? null,
        ]);
        unset($_SESSION['admin_success'], $_SESSION['admin_error']);
    }

    public function updateSettings(): void
    {
        $this->auth->requireAdmin();

        $allSettings = $this->settings->getAll();
        $updates = [];
        foreach ($allSettings as $row) {
            $key = $row['setting_key'];
            if (isset($_POST[$key])) {
                $updates[$key] = trim($_POST[$key]);
            }
        }

        $this->settings->setBulk($updates);
        $_SESSION['admin_success'] = 'Settings saved.';
        header('Location: /admin/settings');
        exit;
    }

    public function inviteForm(): void
    {
        $this->auth->requireAdmin();
        Template::render('admin/invite', [
            'auth' => $this->auth,
            'success' => $_SESSION['admin_success'] ?? null,
            'error' => $_SESSION['admin_error'] ?? null,
            'inviteEmail' => $_SESSION['invite_email'] ?? null,
        ]);
        unset($_SESSION['admin_success'], $_SESSION['admin_error'], $_SESSION['invite_email']);
    }

    public function invite(): void
    {
        $this->auth->requireAdmin();

        $email = Auth::normalizeEmail($_POST['email'] ?? '');
        $name = trim($_POST['name'] ?? '');

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['admin_error'] = 'Valid email is required.';
            header('Location: /admin/invite');
            exit;
        }

        $token = $this->auth->createInvite($email, $name);
        $emailMessage = $this->auth->buildInviteEmail($email, $token);

        $_SESSION['admin_success'] = 'Invite created for ' . $email;
        $_SESSION['invite_email'] = [
            'to' => $emailMessage->to,
            'subject' => $emailMessage->subject,
            'htmlBody' => $emailMessage->htmlBody,
            'textBody' => $emailMessage->textBody,
        ];
        header('Location: /admin/invite');
        exit;
    }
}
