<?php
require_once __DIR__ . '/../../../assets/helpers/libs.php';
require_once __DIR__ . '/../../../assets/helpers/functions.php';
require_once __DIR__ . '/../../../assets/helpers/auth_helpers.php';
require_once __DIR__ . '/../../../assets/helpers/ui_helpers.php';
require_once __DIR__ . '/../../../assets/helpers/project_helpers.php';
require_once __DIR__ . '/../../../assets/helpers/task_helpers.php';
require_once __DIR__ . '/../../../components/layouts/sidebar_layout.php';

requireActiveUser();

$projectId = (int)($_GET['project_id'] ?? 0);
$taskId    = (int)($_GET['task_id']    ?? 0);
if (!$projectId || !$taskId) { header('Location: '.url('dashboard')); exit; }

requireProjectAccess($projectId);

$task = getTaskById($taskId);
if (!$task || (int)($task['project_id'] ?? 0) !== $projectId) {
    header('Location: '.url('projects/'.$projectId)); exit;
}

$project    = getProject($projectId);
$userId     = (int)$_SESSION['user']['id'];
$owner      = isProjectOwner($project['id'], $userId);
$isOwner    = isProjectOwner($projectId, $userId);
$isAssignee = (int)($task['assigned_to'] ?? 0) === $userId;
$isReviewer = isTaskReviewer($task, $userId);
$status     = $task['status'] ?? 'open';
$redir      = url('projects/'.$projectId.'/tasks/'.$taskId);

$flashAlert = $_SESSION['flash_alert'] ?? null;
unset($_SESSION['flash_alert']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'approve_review') {
        $r = approveTaskReview($taskId, $userId);
    } elseif ($action === 'reject_review') {
        $r = rejectTaskReview($taskId, $userId, trim($_POST['review_feedback'] ?? ''));
    } elseif ($action === 'submit_review') {
        $r = submitTaskForReview($taskId, $userId);
    } elseif ($action === 'change_status') {
        $r = changeTaskStatus($taskId, $_POST['new_status'] ?? '', $userId);
    } elseif ($action === 'update_assignment') {
        $r = updateTaskAssignment(
            $taskId,
            (int)($_POST['assigned_to'] ?? 0),
            (int)($_POST['reviewer_id'] ?? 0),
            $userId
        );
    } elseif ($action === 'update_admin_attachment' && ($owner || $isOwner || (int)($task['user_id'] ?? 0) === $userId)) {
        $file = $_FILES['attachment'] ?? null;
        if ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
            $saved = saveFileSecure($file);
            if ($saved === false) { $r = ['success' => false, 'message' => 'File tidak valid.']; }
            else {
                $old = $task['attachment'] ?? '';
                $stmt = $conn->prepare('UPDATE tasks SET attachment=? WHERE id=?');
                $stmt->bind_param('si', $saved, $taskId); $stmt->execute(); $stmt->close();
                if ($old) deleteFile($old);
                $r = ['success' => true, 'message' => 'Lampiran diperbarui.'];
            }
        } else { $r = ['success' => false, 'message' => 'Tidak ada file dipilih.']; }
    } elseif ($action === 'update_work_attachment' && ($isAssignee || $owner || $isOwner)) {
        if (!in_array($status, ['in_progress', 'revision'], true)) {
            $r = ['success' => false, 'message' => 'Hanya bisa upload saat in_progress atau revision.'];
        } else {
            $file = $_FILES['work_attachment'] ?? null;
            if ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
                $saved = saveFileSecure($file);
                if ($saved === false) { $r = ['success' => false, 'message' => 'File tidak valid.']; }
                else {
                    $old = $task['work_attachment'] ?? '';
                    $stmt = $conn->prepare('UPDATE tasks SET work_attachment=? WHERE id=?');
                    $stmt->bind_param('si', $saved, $taskId); $stmt->execute(); $stmt->close();
                    if ($old) deleteFile($old);
                    $r = ['success' => true, 'message' => 'Hasil kerja diperbarui.'];
                }
            } else { $r = ['success' => false, 'message' => 'Tidak ada file dipilih.']; }
        }
    } elseif ($action === 'update_task' && ($owner || $isOwner || (int)($task['user_id'] ?? 0) === $userId)) {
        $r = updateProjectTask($taskId, $_POST, $_FILES['attachment'] ?? null);
    } else {
        $r = ['success' => false, 'message' => 'Aksi tidak dikenal.'];
    }

    $_SESSION['flash_alert'] = ['icon' => ($r['success'] ?? false) ? 'success' : 'error', 'title' => $r['message'] ?? ''];
    header('Location: '.$redir); exit;
}

$task               = getTaskById($taskId);
$status             = $task['status'] ?? 'open';
$isAssignee         = (int)($task['assigned_to'] ?? 0) === $userId;
$isReviewer         = isTaskReviewer($task, $userId);
$availableTransitions = getAvailableTransitions($task, $userId);
$members            = getProjectAssignableUsers($projectId);

// Dedicated CTA statuses — excluded from generic loop
$dedicatedStatuses = [];
if ($isAssignee) {
    if ($status === 'open')        $dedicatedStatuses[] = 'in_progress';
    if ($status === 'in_progress') $dedicatedStatuses[] = $task['reviewer_id'] ? 'review' : 'done';
    if ($status === 'revision')    $dedicatedStatuses[] = 'in_progress';
}
if ($isReviewer && $status === 'review') $dedicatedStatuses = ['done', 'revision'];
if (($owner || $isOwner) && $status === 'review') $dedicatedStatuses = ['done', 'revision'];

$genericTransitions = array_filter($availableTransitions, fn($t) => !in_array($t, $dedicatedStatuses, true));

$pageTitle   = $task['title'];
$breadcrumbs = [
    ['label' => 'Dashboard',             'url' => url('dashboard')],
    ['label' => 'Project'],
    ['label' => $project['name'] ?? '-', 'url' => url('projects/'.$projectId)],
    ['label' => mb_strimwidth($task['title'], 0, 40, '…')],
];
$headerActions = taskStatusBadge($status, 'md');

// Start content buffering
ob_start();

function fileInputHtml(string $name, string $inputId): string {
    return '<label for="'.$inputId.'" class="flex items-center gap-3 px-4 py-3 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition group">
        <i class="fa-solid fa-paperclip text-gray-400 group-hover:text-blue-500"></i>
        <div>
            <p class="text-sm text-gray-600 group-hover:text-blue-600 file-label-'.$inputId.'">Klik untuk pilih file</p>
            <p class="text-xs text-gray-400">JPG, PNG, PDF, DOC, ZIP — maks. 5MB</p>
        </div>
        <input type="file" id="'.$inputId.'" name="'.$name.'" class="hidden">
    </label>
    <script>document.getElementById("'.$inputId.'")?.addEventListener("change",function(){const l=document.querySelector(".file-label-'.$inputId.'");if(l)l.textContent=this.files.length?this.files[0].name:"Klik untuk pilih file";});</script>';
}
?>

<?php if ($flashAlert): ?>
<script>document.addEventListener('DOMContentLoaded',function(){Swal.fire({icon:<?=json_encode($flashAlert['icon'])?>,title:<?=json_encode($flashAlert['title'])?>,timer:3000,showConfirmButton:false});});</script>
<?php endif; ?>

<main>
    <?php include __DIR__ . '/../../../components/partials/page_header.php'; ?>

    <div class="p-4 md:p-6">
        <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">

            <!-- LEFT -->
            <div class="xl:col-span-8 space-y-5">

                <!-- Detail + Edit -->
                <div class="bg-white border border-gray-200 shadow-sm rounded-xl overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                        <h3 class="font-semibold text-gray-800 text-sm">Detail Tugas</h3>
                        <?php if ($owner || $isOwner || (int)($task['user_id'] ?? 0) === $userId): ?>
                        <button onclick="document.getElementById('editTaskForm').classList.toggle('hidden')"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg">
                            <i class="fa-solid fa-pen-to-square"></i> Edit
                        </button>
                        <?php endif; ?>
                    </div>
                    <div class="p-5 space-y-4">
                        <div class="grid grid-cols-2 gap-4 text-sm md:grid-cols-3">
                            <div>
                                <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Prioritas</p>
                                <?= taskPriorityBadge($task['priority'] ?? 'medium', 'md') ?>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Label</p>
                                <p class="font-medium text-gray-700"><?= htmlspecialchars($task['label'] ?? '—') ?></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Deadline</p>
                                <p class="font-medium text-gray-700"><?= !empty($task['deadline']) ? date('d M Y', strtotime($task['deadline'])) : '—' ?></p>
                            </div>
                        </div>
                        <div class="pt-4 border-t border-gray-100">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Penugasan Saat Ini</p>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div class="flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-3">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-600">
                                        <i class="fa-solid fa-user text-sm"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs text-gray-400">Penanggung Jawab</p>
                                        <p class="truncate text-sm font-semibold text-gray-700"><?= htmlspecialchars($task['assignee_name'] ?? 'Belum ditugaskan') ?></p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 rounded-lg border border-purple-100 bg-purple-50 px-3 py-3">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-purple-100 text-purple-600">
                                        <i class="fa-solid fa-magnifying-glass text-sm"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs text-purple-500">Reviewer</p>
                                        <p class="truncate text-sm font-semibold text-purple-700"><?= htmlspecialchars($task['reviewer_name'] ?? 'Tanpa reviewer') ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php if (!empty($task['description'])): ?>
                        <div>
                            <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Deskripsi</p>
                            <div class="text-sm text-gray-700 whitespace-pre-line leading-relaxed"><?= nl2br(htmlspecialchars($task['description'])) ?></div>
                        </div>
                        <?php endif; ?>

                        <?php if ($owner || $isOwner || (int)($task['user_id'] ?? 0) === $userId): ?>
                        <form id="editTaskForm" method="POST" enctype="multipart/form-data"
                              action="<?= $redir ?>" class="hidden pt-4 border-t border-gray-100 grid grid-cols-1 gap-4 md:grid-cols-2">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="update_task">
                            <input type="hidden" name="replace_attachment" value="1">
                            <div class="md:col-span-2">
                                <label class="text-xs font-semibold text-gray-600 block mb-1">Judul *</label>
                                <input type="text" name="title" required value="<?= htmlspecialchars($task['title']) ?>"
                                    class="w-full h-9 px-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div class="md:col-span-2">
                                <label class="text-xs font-semibold text-gray-600 block mb-1">Deskripsi</label>
                                <textarea name="description" rows="3"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none resize-none"><?= htmlspecialchars($task['description'] ?? '') ?></textarea>
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-gray-600 block mb-1">Deadline</label>
                                <input type="date" name="deadline" value="<?= htmlspecialchars($task['deadline'] ?? '') ?>"
                                    class="w-full h-9 px-3 border border-gray-300 rounded-lg text-sm focus:outline-none">
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-gray-600 block mb-1">Prioritas</label>
                                <select name="priority" class="w-full h-9 px-3 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none">
                                    <?php foreach (['low'=>'Rendah','medium'=>'Sedang','high'=>'Tinggi'] as $v=>$l): ?>
                                        <option value="<?= $v ?>" <?= $task['priority'] === $v ? 'selected' : '' ?>><?= $l ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-gray-600 block mb-1">Label</label>
                                <input type="text" name="label" value="<?= htmlspecialchars($task['label'] ?? '') ?>"
                                    class="w-full h-9 px-3 border border-gray-300 rounded-lg text-sm focus:outline-none">
                            </div>
                            <?php if ($owner || $isOwner): ?>
                            <div>
                                <label class="text-xs font-semibold text-gray-600 block mb-1">Status</label>
                                <select name="status" class="w-full h-9 px-3 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none">
                                    <?php foreach (TASK_STATUS_LABELS as $v=>$l): ?>
                                        <option value="<?= $v ?>" <?= $task['status'] === $v ? 'selected' : '' ?>><?= $l ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            <div class="md:col-span-2 flex gap-2 pt-1">
                                <button type="submit" class="px-4 py-2 text-sm font-semibold bg-emerald-500 hover:bg-emerald-600 text-white rounded-lg">Simpan</button>
                                <button type="button" onclick="document.getElementById('editTaskForm').classList.add('hidden')"
                                    class="px-4 py-2 text-sm border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50">Batal</button>
                            </div>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Assignment -->
                <div class="bg-white border border-gray-200 shadow-sm rounded-xl overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100">
                        <h3 class="font-semibold text-gray-800 text-sm">Penugasan</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Atur siapa yang mengerjakan dan memeriksa tugas ini.</p>
                    </div>
                    <form method="POST" action="<?= $redir ?>" class="p-5 space-y-4">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="update_assignment">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label for="assignedTo" class="text-xs font-semibold text-gray-600 block mb-1">Penanggung Jawab</label>
                                <select id="assignedTo" name="assigned_to" class="w-full h-9 px-3 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="0">— Belum ditugaskan —</option>
                                    <?php foreach ($members as $member): ?>
                                        <option value="<?= (int)$member['id'] ?>" <?= (int)($task['assigned_to'] ?? 0) === (int)$member['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($member['username']) ?><?= $member['membership_role'] === 'owner' ? ' (Owner)' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="reviewerId" class="text-xs font-semibold text-gray-600 block mb-1">Reviewer</label>
                                <select id="reviewerId" name="reviewer_id" class="w-full h-9 px-3 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="0">— Tanpa reviewer —</option>
                                    <?php foreach ($members as $member): ?>
                                        <option value="<?= (int)$member['id'] ?>" <?= (int)($task['reviewer_id'] ?? 0) === (int)$member['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($member['username']) ?><?= $member['membership_role'] === 'owner' ? ' (Owner)' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <p class="text-xs text-gray-400">Penanggung jawab mengerjakan tugas. Reviewer memeriksa hasil sebelum tugas ditandai selesai.</p>
                        <div class="flex justify-end">
                            <button type="submit" class="h-9 px-4 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg">
                                Simpan Penugasan
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Lampiran Admin -->
                <div class="bg-white border border-gray-200 shadow-sm rounded-xl overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
                        <div>
                            <h3 class="font-semibold text-gray-800 text-sm">Lampiran dari Creator</h3>
                            <p class="text-xs text-gray-400">Brief, instruksi, atau referensi</p>
                        </div>
                        <?php if ($owner || $isOwner || (int)($task['user_id'] ?? 0) === $userId): ?>
                        <button onclick="document.getElementById('adminAttachForm').classList.toggle('hidden')"
                                class="text-xs font-medium text-blue-600 hover:underline">Ganti</button>
                        <?php endif; ?>
                    </div>
                    <div class="px-5 py-4 space-y-3">
                        <?php if (!empty($task['attachment'])): ?>
                            <a href="<?= htmlspecialchars(assets('public/'.$task['attachment'])) ?>" target="_blank"
                               class="inline-flex items-center gap-2 text-sm text-blue-600 hover:underline">
                                <i class="fa-solid fa-paperclip"></i> <?= htmlspecialchars(basename($task['attachment'])) ?>
                            </a>
                        <?php else: ?>
                            <p class="text-sm text-gray-400 italic">Belum ada lampiran.</p>
                        <?php endif; ?>
                        <?php if ($owner || $isOwner || (int)($task['user_id'] ?? 0) === $userId): ?>
                        <form id="adminAttachForm" method="POST" enctype="multipart/form-data" action="<?= $redir ?>" class="hidden pt-2 border-t border-gray-100 space-y-2">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="update_admin_attachment">
                            <?= fileInputHtml('attachment', 'adminFileInput') ?>
                            <button type="submit" class="w-full h-9 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg">Simpan</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Hasil Kerja -->
                <div class="bg-white border border-gray-200 shadow-sm rounded-xl overflow-hidden">
                    <div class="px-5 py-3 border-b border-gray-100">
                        <h3 class="font-semibold text-gray-800 text-sm">Hasil Kerja</h3>
                        <p class="text-xs text-gray-400">File yang diunggah saat pengerjaan tugas</p>
                    </div>
                    <div class="px-5 py-4 space-y-3">
                        <?php if (!empty($task['work_attachment'])): ?>
                            <a href="<?= htmlspecialchars(assets('public/'.$task['work_attachment'])) ?>" target="_blank"
                               class="inline-flex items-center gap-2 text-sm text-blue-600 hover:underline">
                                <i class="fa-solid fa-paperclip"></i> <?= htmlspecialchars(basename($task['work_attachment'])) ?>
                            </a>
                        <?php else: ?>
                            <p class="text-sm text-gray-400 italic">Belum ada hasil kerja.</p>
                        <?php endif; ?>
                        <?php if (($isAssignee || $owner || $isOwner) && in_array($status, ['in_progress','revision'], true)): ?>
                        <form method="POST" enctype="multipart/form-data" action="<?= $redir ?>" class="pt-2 border-t border-gray-100 space-y-2">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="update_work_attachment">
                            <?= fileInputHtml('work_attachment', 'workFileInput') ?>
                            <button type="submit" class="w-full h-9 bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-semibold rounded-lg">
                                <?= !empty($task['work_attachment']) ? 'Ganti' : 'Unggah' ?> Hasil Kerja
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Feedback Revisi -->
                <?php if (!empty($task['review_feedback'])): ?>
                <div class="bg-red-50 border border-red-200 rounded-xl p-5">
                    <p class="text-sm font-semibold text-red-700 mb-2">Catatan Revisi</p>
                    <p class="text-sm text-red-700 whitespace-pre-line leading-relaxed"><?= nl2br(htmlspecialchars($task['review_feedback'])) ?></p>
                    <?php if (!empty($task['reviewed_by_name'])): ?>
                        <p class="text-xs text-red-400 mt-2">— <?= htmlspecialchars($task['reviewed_by_name']) ?>, <?= !empty($task['reviewed_at']) ? date('d M Y H:i', strtotime($task['reviewed_at'])) : '' ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- RIGHT SIDEBAR -->
            <div class="xl:col-span-4 space-y-4">

                <!-- Dedicated CTAs -->
                <?php if ($isAssignee && $status === 'open'): ?>
                <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-5">
                    <p class="text-sm font-semibold text-gray-800 mb-1">Siap dikerjakan?</p>
                    <p class="text-xs text-gray-500 mb-4">Ubah status ke sedang dikerjakan untuk memulai.</p>
                    <form method="POST" action="<?= $redir ?>">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="change_status">
                        <input type="hidden" name="new_status" value="in_progress">
                        <button type="submit" class="w-full h-10 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg text-sm">Mulai Kerjakan</button>
                    </form>
                </div>

                <?php elseif ($isAssignee && $status === 'in_progress'): ?>
                <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-5">
                    <p class="text-sm font-semibold text-gray-800 mb-1">Sudah selesai?</p>
                    <?php if (!empty($task['reviewer_id'])): ?>
                        <p class="text-xs text-gray-500 mb-4">Kirim ke reviewer untuk diperiksa.</p>
                        <form method="POST" action="<?= $redir ?>">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="submit_review">
                            <button type="submit" class="w-full h-10 bg-yellow-500 hover:bg-yellow-600 text-white font-semibold rounded-lg text-sm">
                                Kirim untuk Review <i class="fa-solid fa-paper-plane ml-1 text-xs"></i>
                            </button>
                        </form>
                    <?php else: ?>
                        <p class="text-xs text-gray-500 mb-4">Tugas ini tidak memiliki reviewer — bisa langsung ditandai selesai.</p>
                        <form method="POST" action="<?= $redir ?>">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="change_status">
                            <input type="hidden" name="new_status" value="done">
                            <button type="submit" class="w-full h-10 bg-emerald-500 hover:bg-emerald-600 text-white font-semibold rounded-lg text-sm">
                                <i class="fa-solid fa-check mr-1"></i> Tandai Selesai
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <?php elseif ($isAssignee && $status === 'review'): ?>
                <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-5 text-center">
                    <i class="fa-regular fa-clock text-3xl text-yellow-500 mb-2 block"></i>
                    <p class="text-sm font-semibold text-yellow-800">Menunggu Review</p>
                    <p class="text-xs text-yellow-600 mt-1">Reviewer sedang memeriksa tugas ini.</p>
                </div>

                <?php elseif ($isAssignee && $status === 'revision'): ?>
                <div class="bg-red-50 border border-red-200 rounded-xl p-5">
                    <p class="text-sm font-semibold text-red-700 mb-1">Tugas Dikembalikan</p>
                    <p class="text-xs text-red-600 mb-4">Perbaiki sesuai catatan, lalu kirim kembali.</p>
                    <form method="POST" action="<?= $redir ?>">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="change_status">
                        <input type="hidden" name="new_status" value="in_progress">
                        <button type="submit" class="w-full h-10 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg text-sm">Kerjakan Ulang</button>
                    </form>
                </div>

                <?php elseif ($isAssignee && $status === 'done'): ?>
                <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-5 text-center">
                    <i class="fa-solid fa-circle-check text-3xl text-emerald-500 mb-2 block"></i>
                    <p class="text-sm font-semibold text-emerald-800">Tugas Selesai</p>
                </div>
                <?php endif; ?>

                <!-- Review panel (reviewer / owner / admin) -->
                <?php if (($isReviewer || $owner || $isOwner) && $status === 'review'): ?>
                <div class="bg-white border border-yellow-200 shadow-sm rounded-xl overflow-hidden">
                    <div class="px-5 py-3 bg-yellow-50 border-b border-yellow-100">
                        <p class="text-sm font-semibold text-yellow-800">Panel Review</p>
                        <p class="text-xs text-yellow-600">Periksa hasil kerja lalu setujui atau kembalikan.</p>
                    </div>
                    <div class="p-5 space-y-4">
                        <form method="POST" action="<?= $redir ?>">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="approve_review">
                            <button type="submit" class="w-full h-10 bg-emerald-500 hover:bg-emerald-600 text-white font-semibold rounded-lg text-sm">
                                <i class="fa-solid fa-check mr-1"></i> Setujui — Tandai Selesai
                            </button>
                        </form>
                        <div class="relative flex items-center">
                            <div class="flex-grow border-t border-gray-200"></div>
                            <span class="px-2 text-xs text-gray-400">atau</span>
                            <div class="flex-grow border-t border-gray-200"></div>
                        </div>
                        <form method="POST" action="<?= $redir ?>">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="reject_review">
                            <label class="text-xs font-semibold text-gray-600 block mb-1.5">Catatan Revisi <span class="text-red-500">*</span></label>
                            <textarea name="review_feedback" rows="3" required
                                placeholder="Jelaskan apa yang perlu diperbaiki..."
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-400 resize-none mb-3"></textarea>
                            <button type="submit" class="w-full h-10 bg-red-500 hover:bg-red-600 text-white font-semibold rounded-lg text-sm">
                                <i class="fa-solid fa-rotate-left mr-1"></i> Kembalikan untuk Revisi
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Generic transitions (admin/owner, non-review, non-dedicated) -->
                <?php if (($owner || $isOwner) && $status !== 'review' && !empty($genericTransitions)): ?>
                <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-5">
                    <p class="text-sm font-semibold text-gray-800 mb-3">Ubah Status</p>
                    <div class="flex flex-col gap-2">
                        <?php foreach ($genericTransitions as $next): ?>
                        <form method="POST" action="<?= $redir ?>">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="change_status">
                            <input type="hidden" name="new_status" value="<?= htmlspecialchars($next) ?>">
                            <button type="submit" class="w-full h-9 text-sm font-medium border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-700 text-left px-3">
                                <i class="fa-solid fa-arrow-right text-xs mr-1"></i>
                                <?= htmlspecialchars(TASK_STATUS_LABELS[$next] ?? $next) ?>
                            </button>
                        </form>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Meta -->
                <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 space-y-2 text-xs text-gray-500">
                    <div class="flex justify-between"><span class="text-gray-400">ID Tugas</span><span class="font-medium text-gray-700">#<?= (int)$task['id'] ?></span></div>
                    <div class="flex justify-between"><span class="text-gray-400">Dibuat oleh</span><span class="font-medium text-gray-700"><?= htmlspecialchars($task['creator_name'] ?? '—') ?></span></div>
                    <?php if (!empty($task['reviewed_at'])): ?>
                    <div class="pt-2 border-t border-gray-100">
                        <p class="text-gray-400 mb-2">Direview oleh</p>
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-full bg-indigo-100 flex items-center justify-center shrink-0 text-indigo-600 font-bold text-xs uppercase">
                                <?= mb_substr($task['reviewed_by_name'] ?? '?', 0, 1) ?>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-700 text-xs"><?= htmlspecialchars($task['reviewed_by_name'] ?? '—') ?></p>
                                <p class="text-gray-400 text-[11px]"><?= date('d M Y, H:i', strtotime($task['reviewed_at'])) ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>
</main>
<?php
$content = ob_get_clean();
renderSidebarLayout('dashboard', $content, 'Detail Tugas - TaskHub');
?>
