<div class="form-page">
    <h1>Log In</h1>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= \Heirloom\Template::escape($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= \Heirloom\Template::escape($success) ?></div>
    <?php endif; ?>

    <div class="form-card">
        <a href="/auth/google" class="btn btn-google">Sign in with Google</a>

        <div class="divider">or use email</div>

        <form method="POST" action="/login">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" required placeholder="you@example.com">
            </div>

            <div class="form-group">
                <label for="password">Password <span style="font-weight:normal;color:var(--text-muted)">(leave blank to get a magic link)</span></label>
                <input type="password" name="password" id="password" placeholder="Enter password or leave blank">
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%">Log In</button>
        </form>

        <p style="text-align:center;margin-top:1rem;font-size:0.9rem;">
            Don't have an account? <a href="/register">Register</a>
        </p>
    </div>
</div>
