<?php
$pageSubtitle  = $pageSubtitle  ?? '';
$breadcrumbs   = $breadcrumbs   ?? [];
$headerActions = $headerActions ?? '';
?>
<div class="px-6 pt-6 pb-4 border-b border-gray-100 bg-white">
    <?php if (!empty($breadcrumbs)): ?>
    <nav class="flex items-center gap-1.5 mb-2 text-xs text-gray-400">
        <?php foreach ($breadcrumbs as $i => $crumb): ?>
            <?php if ($i > 0): ?><span>/</span><?php endif; ?>
            <?php if (!empty($crumb['url'])): ?>
                <a href="<?= htmlspecialchars($crumb['url']) ?>" class="hover:text-gray-600">
                    <?= htmlspecialchars($crumb['label']) ?>
                </a>
            <?php else: ?>
                <span class="text-gray-600 font-medium"><?= htmlspecialchars($crumb['label']) ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>

    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 md:text-2xl"><?= htmlspecialchars(ucwords(strtolower($pageTitle))) ?></h1>
            <?php if ($pageSubtitle): ?>
                <p class="mt-0.5 text-sm text-gray-500"><?= htmlspecialchars(ucfirst($pageSubtitle)) ?></p>
            <?php endif; ?>
        </div>
        <?php if ($headerActions): ?>
        <div class="flex items-center gap-2 shrink-0">
            <?= $headerActions ?>
        </div>
        <?php endif; ?>
    </div>
</div>
