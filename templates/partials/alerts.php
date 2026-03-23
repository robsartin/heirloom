<?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= \Heirloom\Template::escape($error) ?></div>
<?php endif; ?>
<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= \Heirloom\Template::escape($success) ?></div>
<?php endif; ?>
