<?php use Heirloom\Template; ?>

<div style="margin-bottom:1rem;"><a href="/admin">&laquo; Back to dashboard</a></div>

<div class="painting-detail">
    <div>
        <img class="painting-detail-image"
             src="/uploads/<?= Template::escape($painting['filename']) ?>"
             alt="<?= Template::escape($painting['title']) ?>">
    </div>
    <div class="painting-detail-info">

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= Template::escape($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= Template::escape($success) ?></div>
        <?php endif; ?>

        <form method="POST" action="/admin/painting/<?= $painting['id'] ?>/edit" style="margin-bottom:1.5rem;">
            <?= \Heirloom\Csrf::hiddenField() ?>
            <div class="form-group">
                <label for="title">Title</label>
                <input type="text" name="title" id="title" required
                       value="<?= Template::escape($painting['title']) ?>">
            </div>
            <div class="form-group">
                <label for="description">Description</label>
                <textarea name="description" id="description" rows="3"><?= Template::escape($painting['description']) ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
        </form>

        <?php if ($painting['awarded_to']): ?>
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:1rem;margin-bottom:1.5rem;">
                <p><span class="awarded-label">Awarded</span>
                    <?php if ($painting['awarded_at']): ?>
                        <span style="color:var(--text-muted);font-size:0.85rem;margin-left:0.5rem;"><?= date('M j, Y g:ia', strtotime($painting['awarded_at'])) ?></span>
                    <?php endif; ?>
                </p>

                <?php if ($awardedUser): ?>
                    <p style="margin-top:0.5rem;">
                        <strong><?= Template::escape($awardedUser['name'] ?: 'Anonymous') ?></strong>
                        <br><small style="color:var(--text-muted)"><?= Template::escape($awardedUser['email']) ?></small>
                    </p>

                    <?php if ($awardedUser['shipping_address']): ?>
                        <div style="margin-top:0.5rem;">
                            <label style="font-size:0.85rem;font-weight:bold;">Shipping Address:</label>
                            <p style="white-space:pre-line;font-size:0.9rem;background:#f9f6f2;padding:0.5rem;border-radius:var(--radius);margin-top:0.25rem;"><?= Template::escape($awardedUser['shipping_address']) ?></p>
                        </div>
                    <?php else: ?>
                        <p style="margin-top:0.5rem;color:var(--text-muted);font-size:0.85rem;font-style:italic;">No shipping address on file.</p>
                    <?php endif; ?>
                <?php endif; ?>

                <form method="POST" action="/admin/painting/<?= $painting['id'] ?>/tracking" style="margin-top:0.75rem;">
            <?= \Heirloom\Csrf::hiddenField() ?>
                    <div class="form-group" style="margin-bottom:0.5rem;">
                        <label for="tracking_number" style="font-size:0.85rem;">Tracking Number</label>
                        <input type="text" name="tracking_number" id="tracking_number"
                               value="<?= Template::escape($painting['tracking_number'] ?? '') ?>"
                               placeholder="Enter tracking number">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Save Tracking</button>
                </form>

                <form method="POST" action="/admin/painting/<?= $painting['id'] ?>/award" style="margin-top:0.75rem;">
            <?= \Heirloom\Csrf::hiddenField() ?>
                    <input type="hidden" name="user_id" value="">
                    <button type="submit" class="btn btn-sm btn-danger"
                            onclick="return confirm('Unassign this painting? This clears tracking info too.')">
                        Unassign
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <h2 style="margin-top:1.5rem;font-size:1.2rem;">Interested Users (<?= count($interests) ?>)</h2>

        <?php if (empty($interests)): ?>
            <p style="color:var(--text-muted)">No one has expressed interest yet.</p>
        <?php else: ?>
            <ul class="interest-list" style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);">
                <?php foreach ($interests as $interest): ?>
                    <li>
                        <div>
                            <strong><?= Template::escape($interest['name'] ?: 'Anonymous') ?></strong>
                            <br><small style="color:var(--text-muted)"><?= Template::escape($interest['email']) ?></small>
                            <?php if ($interest['shipping_address']): ?>
                                <br><small style="color:var(--text-muted);">Address on file</small>
                            <?php else: ?>
                                <br><small style="color:var(--text-muted);font-style:italic;">No address</small>
                            <?php endif; ?>
                            <?php if ($interest['message']): ?>
                                <br><em style="font-size:0.85rem;">"<?= Template::escape($interest['message']) ?>"</em>
                            <?php endif; ?>
                        </div>
                        <?php if (!$painting['awarded_to']): ?>
                            <form method="POST" action="/admin/painting/<?= $painting['id'] ?>/award">
            <?= \Heirloom\Csrf::hiddenField() ?>
                                <input type="hidden" name="user_id" value="<?= $interest['user_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-success"
                                        onclick="return confirm('Award this painting to <?= Template::escape($interest['name'] ?: $interest['email']) ?>?')">
                                    Award
                                </button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if (!empty($awardLog)): ?>
            <h2 style="margin-top:1.5rem;font-size:1.2rem;">Award History</h2>
            <table class="admin-table" style="font-size:0.85rem;">
                <thead>
                    <tr>
                        <th>Action</th>
                        <th>User</th>
                        <th>By</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($awardLog as $log): ?>
                        <tr>
                            <td><?= $log['action'] === 'awarded' ? '<span class="awarded-label">Awarded</span>' : 'Unassigned' ?></td>
                            <td><?= Template::escape($log['user_name'] ?: $log['user_email']) ?></td>
                            <td><?= Template::escape($log['admin_name']) ?></td>
                            <td><?= date('M j, Y g:ia', strtotime($log['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <form method="POST" action="/admin/painting/<?= $painting['id'] ?>/delete"
              style="margin-top:2rem;"
              onsubmit="return confirm('Delete this painting permanently?')">
            <?= \Heirloom\Csrf::hiddenField() ?>
            <button type="submit" class="btn btn-danger btn-sm">Delete Painting</button>
        </form>
    </div>
</div>
