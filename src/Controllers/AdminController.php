<?php
declare(strict_types=1);

namespace Heirloom\Controllers;

use Heirloom\Auth;
use Heirloom\Database;
use Heirloom\Template;

class AdminController
{
    private const PER_PAGE = 20;

    public function __construct(private Database $db, private Auth $auth) {}

    public function dashboard(): void
    {
        $this->auth->requireAdmin();

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
            'wanted' => 'WHERE p.awarded_to IS NULL AND (SELECT COUNT(*) FROM interests i2 WHERE i2.painting_id = p.id) > 0',
            'all' => '',
            default => 'WHERE p.awarded_to IS NULL',
        };

        $total = (int) $this->db->scalar(
            "SELECT COUNT(*) FROM paintings p $where"
        );
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * self::PER_PAGE;

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
            [':limit' => self::PER_PAGE, ':offset' => $offset]
        );

        Template::render('admin/dashboard', [
            'paintings' => $paintings,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'filter' => $filter,
            'sort' => $sort,
            'dir' => $dir,
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
            'SELECT i.*, u.name, u.email FROM interests i
             JOIN users u ON u.id = i.user_id
             WHERE i.painting_id = :pid
             ORDER BY i.created_at ASC',
            [':pid' => (int) $id]
        );

        Template::render('admin/manage', [
            'painting' => $painting,
            'interests' => $interests,
            'auth' => $this->auth,
            'success' => $_SESSION['admin_success'] ?? null,
        ]);
        unset($_SESSION['admin_success']);
    }

    public function edit(string $id): void
    {
        $this->auth->requireAdmin();

        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($title === '') {
            $_SESSION['admin_success'] = 'Title cannot be empty.';
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

        $this->db->execute(
            'UPDATE paintings SET awarded_to = :uid WHERE id = :id',
            [':uid' => $userId, ':id' => (int) $id]
        );

        $_SESSION['admin_success'] = 'Painting awarded!';
        header('Location: /admin/painting/' . $id);
        exit;
    }

    public function delete(string $id): void
    {
        $this->auth->requireAdmin();

        $painting = $this->db->fetchOne(
            'SELECT * FROM paintings WHERE id = :id',
            [':id' => (int) $id]
        );

        if ($painting) {
            $filepath = dirname(__DIR__, 2) . '/public/uploads/' . $painting['filename'];
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            $this->db->execute('DELETE FROM paintings WHERE id = :id', [':id' => (int) $id]);
        }

        header('Location: /admin');
        exit;
    }
}
