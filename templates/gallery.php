<?php use Heirloom\Template; ?>

<div class="gallery-header">
    <h1>Paintings Available</h1>
    <p><?= $total ?> painting<?= $total !== 1 ? 's' : '' ?> looking for a new home</p>
</div>

<?php if (empty($paintings)): ?>
    <p>No paintings available right now. Check back soon!</p>
<?php else: ?>
    <div class="gallery-grid">
        <?php foreach ($paintings as $p): ?>
            <div class="painting-card">
                <a href="/painting/<?= $p['id'] ?>">
                    <img class="painting-card-image"
                         src="/uploads/<?= Template::escape($p['filename']) ?>"
                         alt="<?= Template::escape($p['title']) ?>"
                         loading="lazy">
                    <div class="painting-card-body">
                        <div class="painting-card-title"><?= Template::escape($p['title']) ?></div>
                        <div class="painting-card-meta">
                            <span><?= $p['interest_count'] ?> interested</span>
                            <?php if (isset($userInterests[$p['id']])): ?>
                                <span class="interest-badge interested">You want this</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>">&laquo; Prev</a>
            <?php else: ?>
                <span class="disabled">&laquo; Prev</span>
            <?php endif; ?>

            <?php
            $start = max(1, $page - 2);
            $end = min($totalPages, $page + 2);
            if ($start > 1): ?>
                <a href="?page=1">1</a>
                <?php if ($start > 2): ?><span>...</span><?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $start; $i <= $end; $i++): ?>
                <?php if ($i === $page): ?>
                    <span class="current"><?= $i ?></span>
                <?php else: ?>
                    <a href="?page=<?= $i ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($end < $totalPages): ?>
                <?php if ($end < $totalPages - 1): ?><span>...</span><?php endif; ?>
                <a href="?page=<?= $totalPages ?>"><?= $totalPages ?></a>
            <?php endif; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>">Next &raquo;</a>
            <?php else: ?>
                <span class="disabled">Next &raquo;</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
