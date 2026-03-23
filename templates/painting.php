<?php use Heirloom\Template; ?>

<div class="painting-detail">
    <div>
        <img class="painting-detail-image"
             src="/uploads/<?= Template::escape($painting['filename']) ?>"
             alt="<?= Template::escape($painting['title']) ?>">
    </div>
    <div class="painting-detail-info">
        <h1><?= Template::escape($painting['title']) ?></h1>

        <?php if ($painting['description']): ?>
            <p><?= nl2br(Template::escape($painting['description'])) ?></p>
        <?php endif; ?>

        <p><?= $interestCount ?> person<?= $interestCount !== 1 ? 's' : '' ?> interested</p>

        <?php if ($painting['awarded_to']): ?>
            <p><span class="awarded-label">This painting has been claimed</span></p>
        <?php elseif ($auth->isLoggedIn()): ?>
            <form method="POST" action="/painting/<?= $painting['id'] ?>/interest">
            <?= \Heirloom\Csrf::hiddenField() ?>
                <?php if (!$hasInterest): ?>
                    <div class="form-group">
                        <label for="message">Why do you want this painting? (optional)</label>
                        <textarea name="message" id="message" placeholder="Tell us why this painting speaks to you..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">I want this painting</button>
                <?php else: ?>
                    <p class="alert alert-success">You've expressed interest in this painting!</p>
                    <button type="submit" class="btn btn-danger btn-sm">Withdraw interest</button>
                <?php endif; ?>
            </form>
        <?php else: ?>
            <p><a href="/login" class="btn btn-primary">Log in to express interest</a></p>
        <?php endif; ?>

        <p style="margin-top: 1.5rem;"><a href="/">&laquo; Back to gallery</a></p>
    </div>
</div>
