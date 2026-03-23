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
            <?= \Heirloom\Csrf::hiddenField() ?>
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

    <?php if (!empty($awardedPaintings)): ?>
    <div class="form-card" style="margin-top:2rem;">
        <h2 style="margin-bottom:1rem;">Awarded Paintings</h2>
        <?php foreach ($awardedPaintings as $painting): ?>
        <div style="display:flex;gap:1rem;align-items:flex-start;margin-bottom:1.5rem;padding-bottom:1.5rem;border-bottom:1px solid var(--border-color,#eee);">
            <img src="/uploads/<?= \Heirloom\Template::escape($painting['filename']) ?>"
                 alt="<?= \Heirloom\Template::escape($painting['title']) ?>"
                 style="width:80px;height:80px;object-fit:cover;border-radius:4px;">
            <div>
                <p style="margin:0 0 0.25rem;"><strong><?= \Heirloom\Template::escape($painting['title']) ?></strong></p>
                <?php if ($painting['awarded_at']): ?>
                    <p style="margin:0 0 0.25rem;font-size:0.85rem;color:var(--text-muted);">
                        Awarded <?= \Heirloom\Template::escape(date('M j, Y', strtotime($painting['awarded_at']))) ?>
                    </p>
                <?php endif; ?>
                <?php if ($painting['tracking_number']): ?>
                    <p style="margin:0;font-size:0.85rem;">
                        Tracking: <strong><?= \Heirloom\Template::escape($painting['tracking_number']) ?></strong>
                    </p>
                <?php else: ?>
                    <p style="margin:0;font-size:0.85rem;color:var(--text-muted);">No tracking number yet</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
