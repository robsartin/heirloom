<?php use Heirloom\Template; ?>

<div style="margin-bottom:1rem;"><a href="/admin">&laquo; Back to dashboard</a></div>

<div class="painting-detail">
    <div>
        <img class="painting-detail-image"
             src="/uploads/<?= Template::escape($painting['filename']) ?>"
             alt="<?= Template::escape($painting['title']) ?>">
    </div>
    <div class="painting-detail-info">

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" action="/admin/painting/<?= $painting['id'] ?>/edit" style="margin-bottom:1.5rem;">
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
            <p><span class="awarded-label">This painting has been awarded</span></p>
            <form method="POST" action="/admin/painting/<?= $painting['id'] ?>/award" style="margin-top:0.5rem;">
                <input type="hidden" name="user_id" value="">
                <button type="submit" class="btn btn-sm btn-danger"
                        onclick="this.form.user_id.value=''; return confirm('Unassign this painting?')">
                    Unassign
                </button>
            </form>
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
                            <?php if ($interest['message']): ?>
                                <br><em style="font-size:0.85rem;">"<?= Template::escape($interest['message']) ?>"</em>
                            <?php endif; ?>
                        </div>
                        <?php if (!$painting['awarded_to']): ?>
                            <form method="POST" action="/admin/painting/<?= $painting['id'] ?>/award">
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

        <form method="POST" action="/admin/painting/<?= $painting['id'] ?>/delete"
              style="margin-top:2rem;"
              onsubmit="return confirm('Delete this painting permanently?')">
            <button type="submit" class="btn btn-danger btn-sm">Delete Painting</button>
        </form>
    </div>
</div>
