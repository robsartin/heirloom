<?php
use Heirloom\Template;

$sortUrl = function (string $col) use ($filter, $sort, $dir): string {
    $newDir = ($sort === $col && $dir === 'DESC') ? 'asc' : 'desc';
    return '/admin?' . http_build_query(['filter' => $filter, 'sort' => $col, 'dir' => $newDir]);
};

$sortIndicator = function (string $col) use ($sort, $dir): string {
    if ($sort !== $col) return '';
    return $dir === 'ASC' ? ' &#9650;' : ' &#9660;';
};
?>

<h1>Admin Dashboard</h1>

<div class="stats-bar">
    <div class="stat-card">
        <span class="stat-value"><?= $stats['total_paintings'] ?></span>
        <span class="stat-label">Total Paintings</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?= $stats['available'] ?></span>
        <span class="stat-label">Available</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?= $stats['awarded'] ?></span>
        <span class="stat-label">Awarded</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?= $stats['total_users'] ?></span>
        <span class="stat-label">Users</span>
    </div>
    <div class="stat-card">
        <span class="stat-value"><?= $stats['total_interests'] ?></span>
        <span class="stat-label">Interests</span>
    </div>
    <div class="stat-card stat-card-wide">
        <span class="stat-value stat-value-sm"><?= $stats['most_wanted'] ? Template::escape($stats['most_wanted']) : '&mdash;' ?></span>
        <span class="stat-label">Most Wanted</span>
    </div>
</div>

<p style="color:var(--text-muted);margin-bottom:1rem;"><?= $total ?> paintings (<?= $filter ?>)</p>

<div class="filter-bar">
    <a href="/admin?filter=available" class="<?= $filter === 'available' ? 'active' : '' ?>">Available</a>
    <a href="/admin?filter=wanted" class="<?= $filter === 'wanted' ? 'active' : '' ?>">Wanted</a>
    <a href="/admin?filter=awarded" class="<?= $filter === 'awarded' ? 'active' : '' ?>">Awarded</a>
    <a href="/admin?filter=all" class="<?= $filter === 'all' ? 'active' : '' ?>">All</a>
    <a href="/admin/upload" class="btn btn-primary btn-sm" style="margin-left:auto">Upload Paintings</a>
</div>

<?php if (empty($paintings)): ?>
    <p>No paintings found.</p>
<?php else: ?>
    <div class="admin-table-wrap">
    <table class="admin-table">
        <thead>
            <tr>
                <th></th>
                <th><a href="<?= $sortUrl('title', $filter, $sort, $dir) ?>" class="sort-header">Title<?= $sortIndicator('title', $sort, $dir) ?></a></th>
                <th><a href="<?= $sortUrl('interest_count', $filter, $sort, $dir) ?>" class="sort-header">Interested<?= $sortIndicator('interest_count', $sort, $dir) ?></a></th>
                <th><a href="<?= $sortUrl('last_interest_at', $filter, $sort, $dir) ?>" class="sort-header">Last Interest<?= $sortIndicator('last_interest_at', $sort, $dir) ?></a></th>
                <th>Status</th>
                <th><a href="<?= $sortUrl('created_at', $filter, $sort, $dir) ?>" class="sort-header">Uploaded<?= $sortIndicator('created_at', $sort, $dir) ?></a></th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($paintings as $p): ?>
                <tr>
                    <td><img src="/uploads/<?= Template::escape($p['filename']) ?>" alt=""></td>
                    <td><?= Template::escape($p['title']) ?></td>
                    <td><?= $p['interest_count'] ?></td>
                    <td style="font-size:0.8rem;color:var(--text-muted)"><?= $p['last_interest_at'] ? date('M j, g:ia', strtotime($p['last_interest_at'])) : '&mdash;' ?></td>
                    <td>
                        <?php if ($p['awarded_to']): ?>
                            <span class="awarded-label">Awarded to <?= Template::escape($p['awarded_name'] ?: $p['awarded_email']) ?></span>
                        <?php else: ?>
                            Available
                        <?php endif; ?>
                    </td>
                    <td style="font-size:0.8rem;color:var(--text-muted)"><?= date('M j, Y', strtotime($p['created_at'])) ?></td>
                    <td>
                        <a href="/admin/painting/<?= $p['id'] ?>" class="btn btn-sm btn-primary">Manage</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <?php $qs = http_build_query(['filter' => $filter, 'sort' => $sort, 'dir' => strtolower($dir)]); ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?<?= $qs ?>&page=<?= $page - 1 ?>">&laquo; Prev</a>
            <?php else: ?>
                <span class="disabled">&laquo; Prev</span>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php if ($i === $page): ?>
                    <span class="current"><?= $i ?></span>
                <?php else: ?>
                    <a href="?<?= $qs ?>&page=<?= $i ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?<?= $qs ?>&page=<?= $page + 1 ?>">Next &raquo;</a>
            <?php else: ?>
                <span class="disabled">Next &raquo;</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
