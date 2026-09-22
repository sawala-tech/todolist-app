<?php
require_once __DIR__ . '/../../assets/helpers/libs.php';
require_once __DIR__ . '/../../assets/helpers/functions.php';
require_once __DIR__ . '/../../assets/helpers/auth_helpers.php';
require_once __DIR__ . '/../../assets/helpers/ui_helpers.php';
require_once __DIR__ . '/../../assets/helpers/project_helpers.php';
require_once __DIR__ . '/../../assets/helpers/task_helpers.php';
require_once __DIR__ . '/../../components/layouts/sidebar_layout.php';

requireActiveUser();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: '.url('dashboard')); exit; }

requireProjectAccess($id);

$project = getProject($id);
if (!$project) { header('Location: '.url('dashboard')); exit; }

$userId    = (int)$_SESSION['user']['id'];
$members   = getProjectAssignableUsers($id);
$memberCount = getProjectMemberCount($id);
$isOwner   = isProjectOwner($id, $userId);
$taskStats = getProjectTaskStats($id);
$total     = array_sum($taskStats);
$done      = (int)($taskStats['done'] ?? 0);
$pct       = $total > 0 ? round($done / $total * 100) : 0;

function renderProjectTaskCard(array $task, int $projectId, string $cardClass): void
{
    $taskUrl = url('projects/' . $projectId . '/tasks/' . (int)($task['id'] ?? 0));
    $description = trim(strip_tags((string)($task['description'] ?? '')));
    $description = preg_replace('/\s+/', ' ', $description) ?: '';
    $excerpt = $description !== '' ? mb_strimwidth($description, 0, 120, '…') : 'Tidak ada deskripsi.';
    ?>
    <a href="<?= htmlspecialchars($taskUrl) ?>"
       class="block p-3 <?= htmlspecialchars($cardClass) ?> rounded-lg transition">
        <div class="flex items-start justify-between gap-3 mb-2">
            <h4 class="text-sm font-medium text-gray-900 line-clamp-2">
                <?= htmlspecialchars($task['title'] ?? 'Tanpa judul') ?>
            </h4>
            <?= taskPriorityBadge($task['priority'] ?? 'medium') ?>
        </div>
        <p class="text-xs text-gray-500 leading-relaxed line-clamp-3">
            <?= htmlspecialchars($excerpt) ?>
        </p>
    </a>
    <?php
}

// Filters (for list view fallback)
$filterStatus   = $_GET['status']      ?? '';
$filterPriority = $_GET['priority']    ?? '';
$filterLabel    = $_GET['label']       ?? '';
$filterAssignee = $_GET['assigned_to'] ?? '';

// Labels — before getProjectTasks to avoid commands out of sync
$stmtL = $conn->prepare("SELECT DISTINCT label FROM tasks WHERE project_id=? AND label IS NOT NULL AND label!='' ORDER BY label ASC");
$stmtL->bind_param('i', $id);
$stmtL->execute();
$labelsRes = $stmtL->get_result();
$labels = [];
while ($r = $labelsRes->fetch_assoc()) $labels[] = $r['label'];
$labelsRes->free();
$stmtL->close();

// Kanban columns (open, in_progress, done) + review/revision for badges
$kanbanFilters = array_filter(['priority' => $filterPriority, 'label' => $filterLabel,
    'assigned_to' => $filterAssignee ? (int)$filterAssignee : null]);
$kanbanFilters = array_filter($kanbanFilters);

$allTasks    = getProjectTasks($id, $kanbanFilters);
$filterCount = count(array_filter([$filterStatus, $filterPriority, $filterLabel, $filterAssignee]));

// Group tasks for kanban. Review and revision are opened through their badges.
$kanbanCols  = [
    'open'        => ['label' => 'Open',              'color' => 'gray',    'tasks' => []],
    'in_progress' => ['label' => 'Sedang Dikerjakan', 'color' => 'blue',    'tasks' => []],
    'done'        => ['label' => 'Selesai',           'color' => 'emerald', 'tasks' => []],
];
$reviewCount   = 0;
$revisionCount = 0;

foreach ($allTasks as $t) {
    $s = $t['status'] ?? 'open';
    if ($s === 'review')   { $reviewCount++;   continue; }
    if ($s === 'revision') { $revisionCount++; continue; }
    if (isset($kanbanCols[$s])) $kanbanCols[$s]['tasks'][] = $t;
}

$pageTitle   = $project['name'];
$pageSubtitle = $project['description'] ?? '';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => url('dashboard')],
    ['label' => 'Project'],
    ['label' => $project['name']],
];
$headerActions = '';
if ($isOwner) {
    $headerActions .= '<button id="inviteBtn" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">
        <i class="fas fa-user-plus mr-2"></i>Undang Anggota
    </button>';
}
$headerActions .= '<a href="'.url('projects/'.$id.'/tasks/create').'" class="flex items-center px-4 py-2 text-white text-sm font-medium rounded-lg hover:opacity-90 transition-opacity" style="background-color: #10b981;">
        <i class="fas fa-plus mr-2"></i>Buat Tugas
    </a>';

$flashAlert = $_SESSION['flash_alert'] ?? null;
unset($_SESSION['flash_alert']);

// Start content buffering for sidebar layout
ob_start();
?>

<?php if ($flashAlert): ?>
<script>
document.addEventListener('DOMContentLoaded',function(){
    Swal.fire({icon:<?=json_encode($flashAlert['icon'])?>,title:<?=json_encode($flashAlert['title'])?>,timer:2500,showConfirmButton:false});
});
</script>
<?php endif; ?>

<main>
    <?php include __DIR__ . '/../../components/partials/page_header.php'; ?>

    <div class="p-4 md:p-6">

        <!-- Project Info Bar -->
        <div class="bg-white border border-gray-200 rounded-lg p-4 mb-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600">
                    <?php if ($project['status'] === 'active'): ?>
                        <div class="flex items-center px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm font-medium">
                            <div class="w-2 h-2 bg-green-500 rounded-full mr-2"></div>
                            Active
                        </div>
                    <?php elseif ($project['status'] === 'draft'): ?>
                        <div class="flex items-center px-3 py-1 bg-orange-100 text-orange-700 rounded-full text-sm font-medium">
                            <div class="w-2 h-2 bg-orange-500 rounded-full mr-2"></div>
                            Draft
                        </div>
                    <?php else: ?>
                        <div class="flex items-center px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-sm font-medium">
                            <div class="w-2 h-2 bg-gray-400 rounded-full mr-2"></div>
                            Archived
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($project['deadline'])): ?>
                        <span class="flex items-center">
                            <i class="fas fa-calendar mr-2 text-gray-400"></i>
                            Deadline: <strong><?= date('d M Y', strtotime($project['deadline'])) ?></strong>
                        </span>
                    <?php endif; ?>
                    
                    <span class="flex items-center">
                        <i class="fas fa-users mr-2 text-gray-400"></i>
                        <?= $memberCount ?> anggota
                    </span>
                </div>
            </div>
            
            <div class="mt-4">
                <div class="flex items-center justify-between text-sm text-gray-500 mb-2">
                    <span>Progress</span>
                    <span><?= $done ?>/<?= $total ?> selesai • <?= $pct ?>%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="h-2 rounded-full transition-all" style="width:<?= $pct ?>%; background-color: #2563eb;"></div>
                </div>
            </div>
        </div>

        <!-- Kanban Board -->
        <div class="space-y-6">
            <!-- Board Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <h3 class="text-lg font-semibold text-gray-900">Board</h3>
                    
                    <?php if ($reviewCount > 0): ?>
                        <a href="?id=<?= $id ?>&status=review"
                           class="inline-flex items-center px-3 py-1 bg-yellow-100 text-yellow-700 text-sm font-medium rounded-full hover:bg-yellow-200">
                            <i class="fas fa-search mr-1"></i>
                            Review: <?= $reviewCount ?>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($revisionCount > 0): ?>
                        <a href="?id=<?= $id ?>&status=revision"
                           class="inline-flex items-center px-3 py-1 bg-red-100 text-red-700 text-sm font-medium rounded-full hover:bg-red-200">
                            <i class="fas fa-undo mr-1"></i>
                            Revisi: <?= $revisionCount ?>
                        </a>
                    <?php endif; ?>
                </div>
                
                <div class="flex items-center space-x-3">
                    <?php if ($filterCount > 0): ?>
                        <a href="<?= url('projects/'.$id) ?>"
                           class="px-3 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                            <i class="fas fa-times mr-1"></i> Reset
                        </a>
                    <?php endif; ?>
                    
                    <button onclick="document.getElementById('filterModal').classList.remove('hidden')"
                            class="px-3 py-2 text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-medium">
                        <i class="fas fa-sliders mr-2"></i> Filter
                        <?php if ($filterCount > 0): ?>
                            <span class="ml-1 w-5 h-5 text-white text-xs rounded-full flex items-center justify-center font-bold" style="background-color: #2563eb;"><?= $filterCount ?></span>
                        <?php endif; ?>
                    </button>
                </div>
            </div>

            <!-- If filtering by review/revision — show as list -->
            <?php if (in_array($filterStatus, ['review','revision'], true)): ?>
            <?php $filteredTasks = getProjectTasks($id, ['status' => $filterStatus]); ?>
            <div class="bg-white border border-gray-200 shadow-sm rounded-xl overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100">
                    <h4 class="text-sm font-semibold text-gray-800">
                        <?= $filterStatus === 'review' ? 'Menunggu Review' : 'Perlu Revisi' ?>
                        <span class="text-gray-400 font-normal">(<?= count($filteredTasks) ?>)</span>
                    </h4>
                </div>
                <?php if (empty($filteredTasks)): ?>
                    <div class="py-10 text-center text-gray-400 text-sm">Tidak ada tugas.</div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th class="px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase text-left">Judul</th>
                                <th class="px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase text-left">Penanggung Jawab</th>
                                <th class="px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase text-left">Reviewer</th>
                                <th class="px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase text-left">Deadline</th>
                                <th class="px-4 py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php foreach ($filteredTasks as $t): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium text-gray-800"><?= htmlspecialchars($t['title']) ?></td>
                                <td class="px-4 py-3 text-gray-500"><?= htmlspecialchars($t['assignee_name'] ?? '—') ?></td>
                                <td class="px-4 py-3 text-gray-500"><?= htmlspecialchars($t['reviewer_name'] ?? '—') ?></td>
                                <td class="px-4 py-3 text-gray-500 text-xs"><?= !empty($t['deadline']) ? date('d M Y', strtotime($t['deadline'])) : '—' ?></td>
                                <td class="px-4 py-3">
                                    <a href="<?= url('projects/'.$id.'/tasks/'.(int)$t['id']) ?>"
                                       class="text-xs px-3 py-1.5 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded-lg">Detail</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <?php else: ?>
            <!-- Kanban Columns -->
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                <!-- Open Column -->
                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold text-gray-900">Open</span>
                            <span class="px-2 py-1 bg-gray-100 text-gray-600 text-xs rounded-full font-medium"><?= count($kanbanCols['open']['tasks']) ?></span>
                        </div>
                    </div>
                    <div class="p-3 space-y-3 min-h-[300px]">
                        <?php if (empty($kanbanCols['open']['tasks'])): ?>
                            <div class="flex flex-col items-center justify-center h-32 text-gray-400">
                                <i class="fas fa-list-check text-2xl mb-2"></i>
                                <p class="text-xs">Tidak ada tugas</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($kanbanCols['open']['tasks'] as $t): ?>
                                <?php renderProjectTaskCard($t, $id, 'bg-gray-50 hover:bg-gray-100 border border-gray-200'); ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- In Progress Column -->
                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-200" style="background-color: #2563eb;">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold text-white">In Progress</span>
                            <span class="px-2 py-1 bg-white/20 text-white text-xs rounded-full font-medium"><?= count($kanbanCols['in_progress']['tasks']) ?></span>
                        </div>
                    </div>
                    <div class="p-3 space-y-3 min-h-[300px]">
                        <?php if (empty($kanbanCols['in_progress']['tasks'])): ?>
                            <div class="flex flex-col items-center justify-center h-32 text-gray-400">
                                <i class="fas fa-tasks text-2xl mb-2"></i>
                                <p class="text-xs">Tidak ada tugas</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($kanbanCols['in_progress']['tasks'] as $t): ?>
                                <?php renderProjectTaskCard($t, $id, 'bg-blue-50 hover:bg-blue-100 border border-blue-200'); ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Review Column -->
                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-200" style="background-color: #f59e0b;">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold text-white">Review</span>
                            <span class="px-2 py-1 bg-white/20 text-white text-xs rounded-full font-medium"><?= $reviewCount ?></span>
                        </div>
                    </div>
                    <div class="p-3 space-y-3 min-h-[300px]">
                        <?php if ($reviewCount === 0): ?>
                            <div class="flex flex-col items-center justify-center h-32 text-gray-400">
                                <i class="fas fa-search text-2xl mb-2"></i>
                                <p class="text-xs">Tidak ada review</p>
                            </div>
                        <?php else: ?>
                            <?php 
                            $reviewTasks = getProjectTasks($id, ['status' => 'review']);
                            foreach ($reviewTasks as $t): 
                            ?>
                                <?php renderProjectTaskCard($t, $id, 'bg-yellow-50 hover:bg-yellow-100 border border-yellow-200'); ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Done Column -->
                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-200" style="background-color: #10b981;">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold text-white">Done</span>
                            <span class="px-2 py-1 bg-white/20 text-white text-xs rounded-full font-medium"><?= count($kanbanCols['done']['tasks']) ?></span>
                        </div>
                    </div>
                    <div class="p-3 space-y-3 min-h-[300px]">
                        <?php if (empty($kanbanCols['done']['tasks'])): ?>
                            <div class="flex flex-col items-center justify-center h-32 text-gray-400">
                                <i class="fas fa-check text-2xl mb-2"></i>
                                <p class="text-xs">Belum ada yang selesai</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($kanbanCols['done']['tasks'] as $t): ?>
                                <?php renderProjectTaskCard($t, $id, 'bg-green-50 hover:bg-green-100 border border-green-200'); ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Filter Modal -->
<div id="filterModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800">Filter Tugas</h3>
            <button onclick="document.getElementById('filterModal').classList.add('hidden')"
                    class="w-7 h-7 flex items-center justify-center text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="GET" action="<?= url('projects/'.$id) ?>" class="p-5 space-y-4">
            <div class="flex flex-col gap-1.5">
                <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Status</label>
                <select name="status" class="h-9 px-3 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Semua Status</option>
                    <?php foreach (TASK_STATUS_LABELS as $v => $l): ?>
                        <option value="<?= $v ?>" <?= $filterStatus === $v ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex flex-col gap-1.5">
                <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Prioritas</label>
                <select name="priority" class="h-9 px-3 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Semua Prioritas</option>
                    <?php foreach (['low'=>'Rendah','medium'=>'Sedang','high'=>'Tinggi'] as $v=>$l): ?>
                        <option value="<?= $v ?>" <?= $filterPriority === $v ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (!empty($labels)): ?>
            <div class="flex flex-col gap-1.5">
                <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Label</label>
                <select name="label" class="h-9 px-3 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Semua Label</option>
                    <?php foreach ($labels as $lbl): ?>
                        <option value="<?= htmlspecialchars($lbl) ?>" <?= $filterLabel === $lbl ? 'selected' : '' ?>><?= htmlspecialchars($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <?php if (isProjectOwner($id, $userId) && !empty($members)): ?>
            <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Penanggung Jawab</label>
                <select name="assigned_to" class="h-9 px-3 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Semua Anggota</option>
                    <?php foreach ($members as $m): ?>
                        <option value="<?= (int)$m['id'] ?>" <?= $filterAssignee == $m['id'] ? 'selected' : '' ?>><?= htmlspecialchars($m['username']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="flex-1 h-10 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg text-sm">Terapkan</button>
                <a href="<?= url('projects/'.$id) ?>" class="flex-1 h-10 flex items-center justify-center border border-gray-300 text-gray-600 hover:bg-gray-50 rounded-lg text-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Invite Modal -->
<?php if (isProjectOwner($id, $userId)): ?>
<div id="inviteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-lg max-w-md w-full mx-4">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">
                <i class="fa-solid fa-user-plus text-indigo-600 mr-2"></i>
                Undang Anggota
            </h3>
        </div>
        <div class="p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Link Invitation</label>
                <div class="flex items-center gap-2">
                    <input type="text" id="inviteLink" readonly
                           value="Loading..." 
                           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm bg-gray-50">
                    <button id="copyLinkBtn" class="px-3 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">
                        <i class="fa-solid fa-copy"></i>
                    </button>
                </div>
                <p class="text-xs text-gray-500 mt-1">
                    <i class="fa-solid fa-clock mr-1"></i>
                    Link berlaku 3 hari, maksimal 10 penggunaan
                </p>
            </div>
            
            <div class="pt-2 border-t border-gray-200">
                <div class="flex gap-3">
                    <button id="generateNewLink" class="flex-1 px-4 py-2 bg-gray-100 text-gray-700 text-sm rounded-lg hover:bg-gray-200">
                        <i class="fa-solid fa-refresh mr-1.5"></i>
                        Generate Baru
                    </button>
                    <button onclick="closeInviteModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 text-sm rounded-lg hover:bg-gray-50">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.getElementById('filterModal')?.addEventListener('click', function(e) {
    if (e.target === this) this.classList.add('hidden');
});

<?php if (isProjectOwner($id, $userId)): ?>
// Invite Modal
document.getElementById('inviteBtn')?.addEventListener('click', function() {
    document.getElementById('inviteModal').classList.remove('hidden');
    generateInviteLink();
});

document.getElementById('inviteModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeInviteModal();
});

function closeInviteModal() {
    document.getElementById('inviteModal').classList.add('hidden');
}

function generateInviteLink() {
    fetch('<?= url("api/project/".$id."/invite") ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            csrf_token: '<?= csrfToken() ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('inviteLink').value = data.url;
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Gagal membuat link invitation');
    });
}

document.getElementById('copyLinkBtn')?.addEventListener('click', function() {
    const linkInput = document.getElementById('inviteLink');
    linkInput.select();
    document.execCommand('copy');
    
    const btn = this;
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-check"></i>';
    btn.classList.add('bg-green-600');
    btn.classList.remove('bg-indigo-600');
    
    setTimeout(() => {
        btn.innerHTML = originalHTML;
        btn.classList.remove('bg-green-600');
        btn.classList.add('bg-indigo-600');
    }, 1500);
});

document.getElementById('generateNewLink')?.addEventListener('click', generateInviteLink);
<?php endif; ?>
</script>

<?php
$content = ob_get_clean();
renderSidebarLayout('dashboard', $content, htmlspecialchars($project['name']) . ' - TaskHub');
?>
