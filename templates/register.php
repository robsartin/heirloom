<div class="form-page">
    <h1>Create Account</h1>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= \Heirloom\Template::escape($error) ?></div>
    <?php endif; ?>

    <?php if (empty($closed)): ?>
        <p style="margin-bottom:1rem;color:var(--text-muted)">Enter your name and email to receive a login link.</p>

        <div class="form-card">
            <form method="POST" action="/register">
            <?= \Heirloom\Csrf::hiddenField() ?>
                <div class="form-group">
                    <label for="name">Your Name</label>
                    <input type="text" name="name" id="name" required placeholder="Jane Smith">
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" name="email" id="email" required placeholder="you@example.com">
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%">Register (sends login link)</button>
            </form>

            <p style="text-align:center;margin-top:1rem;font-size:0.9rem;">
                Already have an account? <a href="/login">Log in</a>
            </p>
        </div>
    <?php else: ?>
        <p style="margin-top:1rem;">
            Already have an account? <a href="/login">Log in</a>
        </p>
    <?php endif; ?>
</div>
