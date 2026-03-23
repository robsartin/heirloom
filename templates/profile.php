<div class="form-page">
    <h1>Your Profile</h1>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= \Heirloom\Template::escape($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= \Heirloom\Template::escape($success) ?></div>
    <?php endif; ?>

    <div class="form-card">
        <p style="margin-bottom:0.5rem;"><strong><?= \Heirloom\Template::escape($user['name'] ?: $user['email']) ?></strong></p>
        <p style="margin-bottom:1rem;color:var(--text-muted);font-size:0.9rem;"><?= \Heirloom\Template::escape($user['email']) ?></p>

        <form method="POST" action="/profile">
            <div class="form-group">
                <label for="shipping_address">Shipping Address</label>
                <textarea name="shipping_address" id="shipping_address" rows="4"
                          placeholder="Street address, city, state, zip, country"><?= \Heirloom\Template::escape($user['shipping_address'] ?? '') ?></textarea>
                <small style="color:var(--text-muted)">Used for delivering paintings you're awarded.</small>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">Save Address</button>
        </form>

        <p style="text-align:center;margin-top:1rem;font-size:0.9rem;">
            <a href="/set-password">Change password</a>
        </p>
    </div>
</div>
