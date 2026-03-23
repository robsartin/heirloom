<div class="form-page">
    <h1>Create Account</h1>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= \Heirloom\Template::escape($error) ?></div>
    <?php endif; ?>

    <div class="form-card">
        <a href="/auth/google" class="btn btn-google">Sign up with Google</a>

        <div class="divider">or use email</div>

        <form method="POST" action="/register">
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
</div>
