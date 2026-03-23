<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= \Heirloom\Template::escape($siteName) ?></title>
    <?php if (!empty($ogTitle)): ?>
    <meta property="og:title" content="<?= \Heirloom\Template::escape($ogTitle) ?>">
    <meta property="og:description" content="<?= \Heirloom\Template::escape($ogDescription ?? '') ?>">
    <meta property="og:image" content="<?= \Heirloom\Template::escape($ogImage ?? '') ?>">
    <meta property="og:type" content="website">
    <?php endif; ?>
    <link rel="stylesheet" href="/css/style.css?v=1">
</head>
<body>
    <nav class="navbar">
        <div class="container nav-container">
            <a href="/" class="nav-brand"><?= \Heirloom\Template::escape($siteName) ?></a>
            <div class="nav-links">
                <?php if ($auth && $auth->isLoggedIn()): ?>
                    <?php $user = $auth->user(); ?>
                    <span class="nav-user"><?= \Heirloom\Template::escape($user['name'] ?: $user['email']) ?></span>
                    <a href="/my-paintings">My Paintings</a>
                    <a href="/profile">Profile</a>
                    <?php if ($auth->isAdmin()): ?>
                        <a href="/admin">Admin</a>
                        <a href="/admin/upload">Upload</a>
                        <a href="/admin/settings">Settings</a>
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
            <p><?= \Heirloom\Template::escape($siteName) ?> &mdash; Paintings looking for new homes</p>
            <?php if (!empty($contactEmail)): ?>
                <p>Contact: <a href="mailto:<?= \Heirloom\Template::escape($contactEmail) ?>"><?= \Heirloom\Template::escape($contactEmail) ?></a></p>
            <?php endif; ?>
        </div>
    </footer>
</body>
</html>
