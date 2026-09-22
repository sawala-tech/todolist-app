<?php
require_once __DIR__ . '/../../../../assets/helpers/libs.php';
require_once __DIR__ . '/../../../../assets/helpers/functions.php';
require_once __DIR__ . '/../../../../assets/helpers/auth_helpers.php';
require_once __DIR__ . '/../../../../assets/helpers/ui_helpers.php';
require_once __DIR__ . '/../../../../assets/helpers/project_helpers.php';
require_once __DIR__ . '/../../../../assets/helpers/task_helpers.php';
require_once __DIR__ . '/../../../../components/layouts/sidebar_layout.php';

requireActiveUser();

$projectId = (int)($_GET['project_id'] ?? ($_POST['project_id'] ?? 0));
if (!$projectId) { header('Location: '.url('dashboard')); exit; }

requireProjectAccess($projectId);

$project = getProject($projectId);
if (!$project) { header('Location: '.url('dashboard')); exit; }

$userId  = (int)$_SESSION['user']['id'];
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $data   = array_merge($_POST, ['project_id' => $projectId]);
    unset($data['assigned_to'], $data['reviewer_id']);
    $result = createProjectTask($data, $userId, $_FILES['attachment'] ?? null);
    if ($result['success']) {
        $_SESSION['flash_alert'] = ['icon' => 'success', 'title' => $result['message']];
        header('Location: '.url('projects/'.$projectId));
        exit;
    }
    $error = $result['message'];
}

$priorityOptions = ['low' => 'Rendah', 'medium' => 'Sedang', 'high' => 'Tinggi'];
$pageTitle   = 'Buat Tugas Baru';
$breadcrumbs = [
    ['label' => 'Dashboard',    'url' => url('dashboard')],
    ['label' => 'Project'],
    ['label' => $project['name'], 'url' => url('projects/'.$projectId)],
    ['label' => 'Buat Tugas'],
];
$headerActions = '';
// Start content buffering
ob_start();
?>
<main>
    <?php include __DIR__ . '/../../../../components/partials/page_header.php'; ?>
    <div class="max-w-2xl px-4 md:px-6 py-6">
        <?php if ($error): ?>
        <div class="mb-4 px-4 py-3 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg">
            <i class="fa-solid fa-circle-exclamation mr-1.5"></i><?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>
        <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-6">
            <form method="POST" enctype="multipart/form-data"
                  action="<?= url('projects/'.$projectId.'/tasks/create') ?>" class="space-y-5">
                <?= csrfField() ?>
                <input type="hidden" name="project_id" value="<?= $projectId ?>">
                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-semibold text-gray-700">Judul <span class="text-red-500">*</span></label>
                    <input type="text" name="title" required value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                           placeholder="Masukkan judul tugas"
                           class="h-10 px-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-semibold text-gray-700">Deskripsi</label>
                    <textarea name="description" rows="3" placeholder="Deskripsi tugas (opsional)"
                              class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-semibold text-gray-700">Deadline</label>
                        <input type="date" name="deadline" value="<?= htmlspecialchars($_POST['deadline'] ?? '') ?>"
                               class="h-10 px-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-semibold text-gray-700">Prioritas</label>
                        <select name="priority" class="h-10 px-3 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <?php foreach ($priorityOptions as $val => $lbl): ?>
                                <option value="<?= $val ?>" <?= ($_POST['priority'] ?? 'medium') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-semibold text-gray-700">Label</label>
                    <input type="text" name="label" value="<?= htmlspecialchars($_POST['label'] ?? '') ?>"
                           placeholder="Contoh: Frontend, Bug, Design"
                           class="h-10 px-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="px-3 py-2 text-xs text-gray-500 bg-gray-50 border border-gray-200 rounded-lg">
                    Tugas baru dibuat berstatus Open. Penanggung jawab dan reviewer dapat diatur kemudian dari halaman detail tugas.
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-semibold text-gray-700">Lampiran <span class="text-gray-400 font-normal text-xs">(opsional)</span></label>
                    <label for="attachInput" class="flex items-center gap-3 px-4 py-3 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition group">
                        <i class="fa-solid fa-paperclip text-gray-400 group-hover:text-blue-500"></i>
                        <div>
                            <p class="text-sm text-gray-600 group-hover:text-blue-600" id="attachLabel">Klik untuk pilih file</p>
                            <p class="text-xs text-gray-400">JPG, PNG, PDF, DOC, ZIP — maks. 5MB</p>
                        </div>
                        <input type="file" id="attachInput" name="attachment" class="hidden" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.txt,.zip">
                    </label>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="submit" class="flex-1 h-10 bg-emerald-500 hover:bg-emerald-600 text-white font-semibold rounded-lg text-sm">Buat Tugas</button>
                    <a href="<?= url('projects/'.$projectId) ?>" class="flex-1 h-10 flex items-center justify-center bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 font-semibold rounded-lg text-sm">Batal</a>
                </div>
            </form>
        </div>
    </div>
</main>
<script>
document.getElementById('attachInput')?.addEventListener('change', function () {
    const lbl = document.getElementById('attachLabel');
    if (lbl) lbl.textContent = this.files.length ? this.files[0].name : 'Klik untuk pilih file';
});
</script>
<?php
$content = ob_get_clean();
renderSidebarLayout('dashboard', $content, 'Buat Tugas - TaskHub');
?>
