<div class="form-page">
    <h1>My Paintings</h1>

    <?php if (empty($wanted) && empty($awarded) && empty($noLongerAvailable)): ?>
        <p style="text-align:center;color:var(--text-muted);margin:2rem 0;">
            You haven't expressed interest in any paintings yet.
            <a href="/">Browse the gallery</a> to find paintings you love.
        </p>
    <?php endif; ?>

    <?php if (!empty($awarded)): ?>
    <div class="form-card" style="margin-bottom:2rem;">
        <h2 style="margin-bottom:1rem;">Paintings I Was Awarded</h2>
        <?php foreach ($awarded as $painting): ?>
        <div style="display:flex;gap:1rem;align-items:flex-start;margin-bottom:1.5rem;padding-bottom:1.5rem;border-bottom:1px solid var(--border-color,#eee);">
            <a href="/painting/<?= (int) $painting['id'] ?>">
                <img src="/uploads/<?= \Heirloom\Template::escape($painting['filename']) ?>"
                     alt="<?= \Heirloom\Template::escape($painting['title']) ?>"
                     style="width:80px;height:80px;object-fit:cover;border-radius:4px;">
            </a>
            <div>
                <p style="margin:0 0 0.25rem;">
                    <a href="/painting/<?= (int) $painting['id'] ?>"><strong><?= \Heirloom\Template::escape($painting['title']) ?></strong></a>
                </p>
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

    <?php if (!empty($wanted)): ?>
    <div class="form-card" style="margin-bottom:2rem;">
        <h2 style="margin-bottom:1rem;">Paintings I Want</h2>
        <?php foreach ($wanted as $painting): ?>
        <div style="display:flex;gap:1rem;align-items:flex-start;margin-bottom:1.5rem;padding-bottom:1.5rem;border-bottom:1px solid var(--border-color,#eee);">
            <a href="/painting/<?= (int) $painting['id'] ?>">
                <img src="/uploads/<?= \Heirloom\Template::escape($painting['filename']) ?>"
                     alt="<?= \Heirloom\Template::escape($painting['title']) ?>"
                     style="width:80px;height:80px;object-fit:cover;border-radius:4px;">
            </a>
            <div>
                <p style="margin:0 0 0.25rem;">
                    <a href="/painting/<?= (int) $painting['id'] ?>"><strong><?= \Heirloom\Template::escape($painting['title']) ?></strong></a>
                </p>
                <p style="margin:0;font-size:0.85rem;color:var(--text-muted);">Waiting for award decision</p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($noLongerAvailable)): ?>
    <div class="form-card" style="margin-bottom:2rem;">
        <h2 style="margin-bottom:1rem;">No Longer Available</h2>
        <?php foreach ($noLongerAvailable as $painting): ?>
        <div style="display:flex;gap:1rem;align-items:flex-start;margin-bottom:1.5rem;padding-bottom:1.5rem;border-bottom:1px solid var(--border-color,#eee);">
            <a href="/painting/<?= (int) $painting['id'] ?>">
                <img src="/uploads/<?= \Heirloom\Template::escape($painting['filename']) ?>"
                     alt="<?= \Heirloom\Template::escape($painting['title']) ?>"
                     style="width:80px;height:80px;object-fit:cover;border-radius:4px;opacity:0.6;">
            </a>
            <div>
                <p style="margin:0 0 0.25rem;">
                    <a href="/painting/<?= (int) $painting['id'] ?>"><strong><?= \Heirloom\Template::escape($painting['title']) ?></strong></a>
                </p>
                <p style="margin:0;font-size:0.85rem;color:var(--text-muted);">Awarded to someone else</p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
