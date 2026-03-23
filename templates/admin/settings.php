<?php use Heirloom\Template; ?>

<div style="margin-bottom:1rem;"><a href="/admin">&laquo; Back to dashboard</a></div>

<h1>Site Settings</h1>
<p style="color:var(--text-muted);margin-bottom:1.5rem;">Configure how Heirloom Gallery behaves. Changes take effect immediately.</p>

<?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= Template::escape($error) ?></div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= Template::escape($success) ?></div>
<?php endif; ?>

<?php
$groups = [
    'General' => ['site_name', 'contact_email'],
    'Authentication' => ['magic_link_expiry_minutes', 'registration_open'],
    'Display' => ['gallery_per_page', 'admin_per_page'],
];
$numeric = ['magic_link_expiry_minutes', 'gallery_per_page', 'admin_per_page'];
$boolean = ['registration_open'];
$keyed = [];
foreach ($settings as $row) {
    $keyed[$row['setting_key']] = $row;
}
?>

<form method="POST" action="/admin/settings">
    <div class="settings-grid">
        <?php foreach ($groups as $groupName => $keys): ?>
            <div class="settings-card">
                <h2 class="settings-card-title"><?= Template::escape($groupName) ?></h2>
                <?php foreach ($keys as $key): ?>
                    <?php if (!isset($keyed[$key])) continue; ?>
                    <?php $row = $keyed[$key]; ?>
                    <div class="settings-field">
                        <div class="settings-field-header">
                            <label for="<?= Template::escape($key) ?>"><?= Template::escape($row['label'] ?: $key) ?></label>
                        </div>
                        <?php if (in_array($key, $boolean, true)): ?>
                            <div class="settings-toggle">
                                <select name="<?= Template::escape($key) ?>" id="<?= Template::escape($key) ?>">
                                    <option value="1" <?= $row['setting_value'] === '1' ? 'selected' : '' ?>>Yes</option>
                                    <option value="0" <?= $row['setting_value'] === '0' ? 'selected' : '' ?>>No</option>
                                </select>
                            </div>
                        <?php elseif (in_array($key, $numeric, true)): ?>
                            <input type="number" name="<?= Template::escape($key) ?>"
                                   id="<?= Template::escape($key) ?>"
                                   value="<?= Template::escape($row['setting_value']) ?>"
                                   min="1" class="settings-input-short">
                        <?php else: ?>
                            <input type="text" name="<?= Template::escape($key) ?>"
                                   id="<?= Template::escape($key) ?>"
                                   value="<?= Template::escape($row['setting_value']) ?>">
                        <?php endif; ?>
                        <?php if ($row['description']): ?>
                            <p class="settings-desc"><?= Template::escape($row['description']) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div style="margin-top:1.5rem;">
        <button type="submit" class="btn btn-primary">Save All Settings</button>
    </div>
</form>
