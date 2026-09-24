<?php
require_once __DIR__ . '/../../../assets/helpers/libs.php';
require_once __DIR__ . '/../../../assets/helpers/functions.php';
require_once __DIR__ . '/../../../assets/helpers/auth_helpers.php';
require_once __DIR__ . '/../../../assets/helpers/project_helpers.php';
require_once __DIR__ . '/../../../components/layouts/sidebar_layout.php';

requireActiveUser();

$userId = (int)$_SESSION['user']['id'];
$taskId = (int)($_GET['id'] ?? 0);

if ($taskId <= 0) {
    header('Location: ' . url('backlog'));
    exit;
}

$stmt = $conn->prepare(
    'SELECT * FROM tasks WHERE id = ? AND user_id = ? AND project_id IS NULL'
);
$stmt->bind_param('ii', $taskId, $userId);
$stmt->execute();
$task = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$task) {
    header('Location: ' . url('backlog'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if (isset($_POST['add_to_project'])) {
        $projectId = (int)($_POST['project_id'] ?? 0);

        $stmt = $conn->prepare(
            'SELECT p.id FROM projects p
             WHERE p.id = ? AND (
                 p.owner_id = ?
                 OR EXISTS (
                     SELECT 1 FROM project_members pm
                     WHERE pm.project_id = p.id AND pm.user_id = ?
                 )
             )'
        );
        $stmt->bind_param('iii', $projectId, $userId, $userId);
        $stmt->execute();
        $project = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$project) {
            $error = 'Project tidak ditemukan atau Anda bukan anggota project ini.';
        } else {
            $stmt = $conn->prepare(
                'UPDATE tasks SET project_id = ? WHERE id = ? AND user_id = ?'
            );
            $stmt->bind_param('iii', $projectId, $taskId, $userId);
            $ok = $stmt->execute();
            $stmt->close();

            if ($ok) {
                $_SESSION['flash_alert'] = [
                    'icon' => 'success',
                    'title' => 'Tugas berhasil dipindahkan ke project.',
                ];
                header('Location: ' . url('projects/' . $projectId));
                exit;
            }

            $error = 'Gagal memindahkan tugas ke project.';
        }
    } else {
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $deadline    = !empty($_POST['deadline']) ? date('Y-m-d', strtotime($_POST['deadline'])) : null;
        $priority    = in_array($_POST['priority'] ?? '', ['low', 'medium', 'high'], true)
            ? $_POST['priority']
            : 'medium';
        $label       = trim($_POST['label'] ?? '');

        if ($title === '') {
            $error = 'Judul tugas tidak boleh kosong.';
        } else {
            $stmt = $conn->prepare(
                'UPDATE tasks
                 SET title = ?, description = ?, deadline = ?, priority = ?, label = ?
                 WHERE id = ? AND user_id = ? AND project_id IS NULL'
            );
            $stmt->bind_param('sssssii', $title, $description, $deadline, $priority, $label, $taskId, $userId);
            $ok = $stmt->execute();
            $stmt->close();

            if ($ok) {
                $_SESSION['flash_success'] = 'Tugas berhasil diperbarui!';
                header('Location: ' . url('backlog'));
                exit;
            }

            $error = 'Gagal memperbarui tugas: ' . $conn->error;
        }
    }
}

$userProjects  = getProjectsForUser($userId);
$activeProjects = array_filter(
    $userProjects,
    fn($project) => in_array($project['status'], ['active', 'draft'], true)
);

$pageTitle    = $task['title'];
$breadcrumbs  = [
    ['label' => 'Dashboard', 'url' => url('dashboard')],
    ['label' => 'Backlog', 'url' => url('backlog')],
    ['label' => 'Tugas'],
];
$headerActions = '';

ob_start();
?>

<main>
    <?php include __DIR__ . '/../../../components/partials/page_header.php'; ?>

    <div class="p-4 md:p-6">
        <div class="max-w-2xl space-y-5">
            <?php if ($error): ?>
            <div class="px-3 py-2 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                <i class="fas fa-circle-exclamation mr-1"></i><?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-5">
                <form method="POST" class="space-y-5">
                    <?= csrfField() ?>

                    <div class="flex flex-col gap-1.5">
                        <label for="title" class="text-sm font-semibold text-gray-700">
                            Judul Tugas <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="title" name="title" required
                               value="<?= htmlspecialchars($task['title']) ?>"
                               placeholder="Judul tugas"
                               class="h-10 px-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="description" class="text-sm font-semibold text-gray-700">Deskripsi</label>
                        <textarea id="description" name="description" rows="3"
                                  placeholder="Deskripsi tugas (opsional)"
                                  class="px-3 py-2 border border-gray-300 rounded-lg text-sm resize-none focus:outline-none focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($task['description'] ?? '') ?></textarea>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="deadline" class="text-sm font-semibold text-gray-700">Tenggat</label>
                        <input type="date" id="deadline" name="deadline"
                               value="<?= htmlspecialchars($task['deadline'] ?? '') ?>"
                               class="h-10 px-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="flex flex-col gap-1.5">
                            <label for="priority" class="text-sm font-semibold text-gray-700">Prioritas</label>
                            <select id="priority" name="priority"
                                    class="h-10 px-3 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="low" <?= ($task['priority'] ?? 'medium') === 'low' ? 'selected' : '' ?>>Rendah</option>
                                <option value="medium" <?= ($task['priority'] ?? 'medium') === 'medium' ? 'selected' : '' ?>>Sedang</option>
                                <option value="high" <?= ($task['priority'] ?? 'medium') === 'high' ? 'selected' : '' ?>>Tinggi</option>
                            </select>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label for="label" class="text-sm font-semibold text-gray-700">Label</label>
                            <input type="text" id="label" name="label"
                                   value="<?= htmlspecialchars($task['label'] ?? '') ?>"
                                   placeholder="Contoh: Bug"
                                   class="h-10 px-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <a href="<?= url('backlog') ?>"
                           class="flex-1 h-10 flex items-center justify-center border border-gray-300 text-gray-700 hover:bg-gray-50 font-semibold rounded-lg text-sm">
                            Batal
                        </a>
                        <button type="submit"
                                class="flex-1 h-10 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg text-sm">
                            <i class="fas fa-save mr-1"></i>Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>

            <div class="bg-white border border-gray-200 shadow-sm rounded-xl overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-800">Tambahkan ke Project</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Pindahkan tugas ini ke salah satu project Anda</p>
                </div>
                <div class="p-5">
                    <?php if (empty($activeProjects)): ?>
                        <p class="text-sm text-gray-500">
                            Tidak ada project aktif.
                            <a href="<?= url('projects/create') ?>" class="text-blue-600 hover:underline">Buat project baru</a>.
                        </p>
                    <?php else: ?>
                    <form method="POST" class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <?= csrfField() ?>
                        <input type="hidden" name="add_to_project" value="1">
                        <select name="project_id" required
                                class="flex-1 h-10 px-3 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Pilih project...</option>
                            <?php foreach ($activeProjects as $project): ?>
                                <option value="<?= (int)$project['id'] ?>">
                                    <?= htmlspecialchars($project['name']) ?> (<?= htmlspecialchars($project['status']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit"
                                class="h-10 px-4 text-sm text-white bg-emerald-500 hover:bg-emerald-600 rounded-lg font-semibold whitespace-nowrap">
                            <i class="fas fa-folder-plus mr-1"></i>Tambahkan
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
$content = ob_get_clean();
renderSidebarLayout('backlog', $content, 'Detail Tugas - TaskHub');
?>
