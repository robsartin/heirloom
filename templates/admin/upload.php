<div class="form-page" style="max-width:600px;">
    <h1>Upload Paintings</h1>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= \Heirloom\Template::escape($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= \Heirloom\Template::escape($success) ?></div>
    <?php endif; ?>

    <div class="form-card">
        <form method="POST" action="/admin/upload" enctype="multipart/form-data">
            <?= \Heirloom\Csrf::hiddenField() ?>
            <div class="form-group">
                <label for="title">Title</label>
                <input type="text" name="title" id="title" placeholder="Painting title">
                <small style="color:var(--text-muted)">Required for single uploads. For multiple files, filenames are used as titles (this field becomes an optional prefix).</small>
            </div>

            <div class="form-group">
                <label for="description">Description (optional)</label>
                <textarea name="description" id="description" placeholder="About this painting..."></textarea>
            </div>

            <div class="form-group">
                <label for="paintings">Images (PNG or JPEG)</label>
                <input type="file" name="paintings[]" id="paintings" accept="image/jpeg,image/png" multiple required>
                <small style="color:var(--text-muted)">Select multiple files for batch upload.</small>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%">Upload</button>
        </form>
    </div>
</div>
