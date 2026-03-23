<?php use Heirloom\Template; ?>

<div style="margin-bottom:1rem;"><a href="/admin">&laquo; Back to dashboard</a></div>

<h1>Invite User</h1>
<p style="color:var(--text-muted);margin-bottom:1.5rem;">Create a magic link invitation for a new or existing user.</p>

<?php include __DIR__ . '/../partials/alerts.php'; ?>

<?php if (!empty($inviteEmail)): ?>
    <div class="form-card" style="max-width:700px;margin-bottom:1.5rem;">
        <h2 style="font-size:1.1rem;margin-bottom:0.75rem;color:var(--accent);">Generated Email</h2>
        <table class="admin-table" style="font-size:0.9rem;">
            <tr>
                <th style="width:80px;">To</th>
                <td><?= Template::escape($inviteEmail['to']) ?></td>
            </tr>
            <tr>
                <th>Subject</th>
                <td><?= Template::escape($inviteEmail['subject']) ?></td>
            </tr>
        </table>
        <div style="margin-top:1rem;padding:1rem;background:#f9f6f2;border-radius:var(--radius);font-size:0.9rem;">
            <strong>HTML Body:</strong>
            <div style="margin-top:0.5rem;padding:0.75rem;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);">
                <?= $inviteEmail['htmlBody'] ?>
            </div>
        </div>
        <div style="margin-top:0.75rem;padding:1rem;background:#f9f6f2;border-radius:var(--radius);font-size:0.9rem;">
            <strong>Plain Text:</strong>
            <pre style="margin-top:0.5rem;white-space:pre-wrap;font-family:monospace;font-size:0.85rem;"><?= Template::escape($inviteEmail['textBody']) ?></pre>
        </div>
    </div>
<?php endif; ?>

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
        <button type="submit" class="btn btn-primary" style="width:100%">Create Invite</button>
    </form>
</div>
