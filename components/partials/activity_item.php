<?php
$iconClass = 'fas fa-circle';
$iconBg    = 'bg-gray-500';

switch ($activity['action_type'] ?? '') {
    case 'project_created':
        $iconClass = 'fas fa-folder-plus';
        $iconBg = 'bg-blue-500';
        break;
    case 'task_created':
        $iconClass = 'fas fa-plus-circle';
        $iconBg = 'bg-green-500';
        break;
    case 'task_updated':
        $iconClass = 'fas fa-edit';
        $iconBg = 'bg-orange-500';
        break;
    case 'task_completed':
        $iconClass = 'fas fa-check-circle';
        $iconBg = 'bg-green-600';
        break;
    case 'member_added':
        $iconClass = 'fas fa-user-plus';
        $iconBg = 'bg-purple-500';
        break;
}

$actorName = $activity['actor_name'] ?? 'Seseorang';
$message = 'Aktivitas tidak diketahui';

switch ($activity['action_type'] ?? '') {
    case 'project_created':
        $message = '<strong>' . htmlspecialchars($actorName) . '</strong> membuat project <strong>'
            . htmlspecialchars($activity['target_name'] ?? '—') . '</strong>';
        break;
    case 'task_created':
        $message = '<strong>' . htmlspecialchars($actorName) . '</strong> membuat tugas <strong>'
            . htmlspecialchars($activity['target_name'] ?? '—') . '</strong>';
        if (!empty($activity['project_name'])) {
            $message .= ' di project <strong>' . htmlspecialchars($activity['project_name']) . '</strong>';
        }
        if (!empty($activity['status'])) {
            $statusLabel = ucfirst(str_replace('_', ' ', $activity['status']));
            $message .= ' <span class="text-gray-500">(' . htmlspecialchars($statusLabel) . ')</span>';
        }
        break;
    case 'member_added':
        $parts = explode(' → ', $activity['target_name'] ?? '');
        if (count($parts) >= 2) {
            $message = '<strong>' . htmlspecialchars($actorName) . '</strong> menambahkan <strong>'
                . htmlspecialchars($parts[0]) . '</strong> ke project <strong>'
                . htmlspecialchars($parts[1]) . '</strong>';
        } else {
            $message = '<strong>' . htmlspecialchars($actorName) . '</strong> menambahkan anggota baru';
        }
        break;
}
?>

<div class="p-4 md:p-5 hover:bg-gray-50 transition-colors">
    <div class="flex items-start gap-3">
        <div class="w-9 h-9 <?= $iconBg ?> rounded-full flex items-center justify-center shrink-0">
            <i class="<?= $iconClass ?> text-white text-xs"></i>
        </div>

        <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm text-gray-900 leading-relaxed"><?= $message ?></p>
                    <div class="mt-1 flex items-center text-xs text-gray-500">
                        <i class="fas fa-clock mr-1"></i>
                        <?php if (!empty($activity['action_time'])): ?>
                            <?= timeAgo($activity['action_time']) ?>
                            <span class="mx-2">•</span>
                            <?= date('d M Y H:i', strtotime($activity['action_time'])) ?>
                        <?php else: ?>
                            <span class="text-gray-400">Waktu tidak diketahui</span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (($activity['action_type'] ?? '') === 'project_created'): ?>
                    <a href="<?= url('projects/'.(int)$activity['target_id']) ?>"
                       class="shrink-0 text-xs font-medium text-blue-600 hover:text-blue-700">
                        Lihat Project
                    </a>
                <?php elseif (($activity['action_type'] ?? '') === 'task_created' && !empty($activity['project_id'])): ?>
                    <a href="<?= url('projects/'.(int)$activity['project_id'].'/tasks/'.(int)$activity['target_id']) ?>"
                       class="shrink-0 text-xs font-medium text-blue-600 hover:text-blue-700">
                        Lihat Tugas
                    </a>
                <?php elseif (($activity['action_type'] ?? '') === 'task_created'): ?>
                    <span class="shrink-0 text-xs text-gray-500">
                        Tugas #<?= (int)$activity['target_id'] ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
