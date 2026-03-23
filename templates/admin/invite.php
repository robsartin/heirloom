<?php use Heirloom\Template; ?>

<div style="margin-bottom:1rem;"><a href="/admin">&laquo; Back to dashboard</a></div>

<h1>Invite User</h1>
<p style="color:var(--text-muted);margin-bottom:1.5rem;">Send a magic link invitation to a new or existing user.</p>

<?php include __DIR__ . '/../partials/alerts.php'; ?>

<div class="form-card" style="max-width:500px;">
    <form method="POST" action="/admin/invite">
        <?= \Heirloom\Csrf::hiddenField() ?>
        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" name="email" id="email" required placeholder="user@example.com">
        </div>
        <div class="form-group">
            <label for="name">Name (optional)</label>
            <input type="text" name="name" id="name" placeholder="Jane Smith">
            <small style="color:var(--text-muted)">If the user already exists, their name won't be overwritten.</small>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%">Send Invite</button>
    </form>
</div>
