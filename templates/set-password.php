<div class="form-page">
    <h1>Set Your Password</h1>
    <p style="margin-bottom:1rem;color:var(--text-muted)">Optional: set a password so you can log in without a magic link next time.</p>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= \Heirloom\Template::escape($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= \Heirloom\Template::escape($success) ?></div>
    <?php endif; ?>

    <div class="form-card">
        <form method="POST" action="/set-password">
            <div class="form-group">
                <label for="password">New Password (min 8 characters)</label>
                <input type="password" name="password" id="password" required minlength="8">
            </div>
            <div class="form-group">
                <label for="password_confirm">Confirm Password</label>
                <input type="password" name="password_confirm" id="password_confirm" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">Set Password</button>
        </form>
        <p style="text-align:center;margin-top:1rem;"><a href="/">Skip for now &rarr;</a></p>
    </div>
</div>
