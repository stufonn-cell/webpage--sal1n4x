<?php
/** @var array $result */
/** @var string $baseUrl */
if (($result['pages'] ?? 1) <= 1) {
    return;
}
$separator = str_contains($baseUrl, '?') ? '&' : '?';
?>
<nav class="pagination">
    <?php for ($page = 1; $page <= (int) $result['pages']; $page++): ?>
        <?php if ($page === (int) $result['page']): ?>
            <span class="is-current"><?= $page ?></span>
        <?php else: ?>
            <a href="<?= e($baseUrl . $separator . 'page=' . $page) ?>"><?= $page ?></a>
        <?php endif; ?>
    <?php endfor; ?>
</nav>
