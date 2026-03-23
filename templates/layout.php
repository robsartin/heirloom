<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Heirloom Gallery</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="container nav-container">
            <a href="/" class="nav-brand">Heirloom Gallery</a>
            <div class="nav-links">
                <?php if ($auth && $auth->isLoggedIn()): ?>
                    <?php $user = $auth->user(); ?>
                    <span class="nav-user"><?= \Heirloom\Template::escape($user['name'] ?: $user['email']) ?></span>
                    <?php if ($auth->isAdmin()): ?>
                        <a href="/admin">Admin</a>
                        <a href="/admin/upload">Upload</a>
                    <?php endif; ?>
                    <a href="/logout">Log out</a>
                <?php else: ?>
                    <a href="/login">Log in</a>
                    <a href="/register">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <main class="container">
        <?= $content ?>
    </main>
    <footer class="footer">
        <div class="container">
            <p>Heirloom Gallery &mdash; Paintings looking for new homes</p>
        </div>
    </footer>
</body>
</html>
