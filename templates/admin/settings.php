<?php use Heirloom\Template; ?>

<h1>Site Settings</h1>

<?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= Template::escape($error) ?></div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= Template::escape($success) ?></div>
<?php endif; ?>

<div class="form-card" style="max-width:600px;">
    <form method="POST" action="/admin/settings">
        <?php foreach ($settings as $row): ?>
            <div class="form-group">
                <label for="<?= Template::escape($row['setting_key']) ?>">
                    <?= Template::escape($row['label'] ?: $row['setting_key']) ?>
                </label>
                <?php if (in_array($row['setting_key'], ['registration_open'], true)): ?>
                    <select name="<?= Template::escape($row['setting_key']) ?>" id="<?= Template::escape($row['setting_key']) ?>">
                        <option value="1" <?= $row['setting_value'] === '1' ? 'selected' : '' ?>>Yes</option>
                        <option value="0" <?= $row['setting_value'] === '0' ? 'selected' : '' ?>>No</option>
                    </select>
                <?php else: ?>
                    <input type="text" name="<?= Template::escape($row['setting_key']) ?>"
                           id="<?= Template::escape($row['setting_key']) ?>"
                           value="<?= Template::escape($row['setting_value']) ?>">
                <?php endif; ?>
                <?php if ($row['description']): ?>
                    <small style="color:var(--text-muted)"><?= Template::escape($row['description']) ?></small>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <button type="submit" class="btn btn-primary" style="width:100%">Save Settings</button>
    </form>
</div>

<p style="margin-top:1rem;"><a href="/admin">&laquo; Back to dashboard</a></p>
