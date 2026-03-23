<div class="landing">
    <h1><?= \Heirloom\Template::escape($siteName ?? \Heirloom\SiteSettings::DEFAULT_SITE_NAME) ?></h1>
    <p class="landing-tagline">Paintings looking for new homes</p>
    <p class="landing-desc">Browse the collection, find paintings you love, and let us know which ones you'd like to have.</p>
    <div class="landing-actions">
        <a href="/login" class="btn btn-primary btn-lg">Log In</a>
        <a href="/register" class="btn btn-outline btn-lg">Register</a>
    </div>
</div>
