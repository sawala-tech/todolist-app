<?php
/**
 * pages/projects/create/index.php — TaskHub v4
 * 
 * Buat project baru (semua user bisa buat project)
 */

require_once __DIR__ . '/../../../assets/helpers/functions.php';
require_once __DIR__ . '/../../../assets/helpers/libs.php';
require_once __DIR__ . '/../../../assets/helpers/auth_helpers.php';
require_once __DIR__ . '/../../../assets/helpers/project_helpers.php';
require_once __DIR__ . '/../../../components/layouts/sidebar_layout.php';

requireActiveUser();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    if (empty($name)) {
        $error = 'Nama project tidak boleh kosong.';
    } else {
        $ownerId = (int)$_SESSION['user']['id'];
        $projectId = createProject($name, $description, $status, $ownerId);
        
        if ($projectId) {
            header('Location: ' . url('projects/' . $projectId));
            exit;
        } else {
            $error = 'Gagal membuat project. Silakan coba lagi.';
        }
    }
}

$pageTitle    = 'Buat Project';
$pageSubtitle = 'Kelola tugas dan kolaborasi dengan tim Anda';
$breadcrumbs  = [
    ['label' => 'Dashboard', 'url' => url('dashboard')],
    ['label' => 'Project'],
    ['label' => 'Buat'],
];
$headerActions = '';

// Start content buffering
ob_start();
?>

<main>
    <?php include __DIR__ . '/../../../components/partials/page_header.php'; ?>

    <div class="p-4 md:p-6">
        <div class="max-w-2xl bg-white border border-gray-200 shadow-sm rounded-xl p-5">
            <?php if ($error): ?>
            <div class="mb-4 px-3 py-2 bg-red-50 border border-red-200 rounded-lg">
                <p class="text-sm text-red-700">
                    <i class="fas fa-circle-exclamation mr-1"></i>
                    <?= htmlspecialchars($error) ?>
                </p>
            </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                <?= csrfField() ?>

                <div class="flex flex-col gap-1.5">
                    <label for="name" class="text-sm font-semibold text-gray-700">
                        Nama Project <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="name" name="name" required
                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                           placeholder="Masukkan nama project"
                           class="h-10 px-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="description" class="text-sm font-semibold text-gray-700">Deskripsi Project</label>
                    <textarea id="description" name="description" rows="3"
                              placeholder="Jelaskan tujuan dan scope project..."
                              class="px-3 py-2 border border-gray-300 rounded-lg text-sm resize-none focus:outline-none focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="status" class="text-sm font-semibold text-gray-700">Status Project</label>
                    <select id="status" name="status"
                            class="h-10 px-3 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="active" <?= ($_POST['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Aktif</option>
                        <option value="draft" <?= ($_POST['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
                    </select>
                </div>

                <div class="flex gap-3 pt-2">
                    <a href="<?= url('dashboard') ?>"
                       class="flex-1 h-10 flex items-center justify-center border border-gray-300 text-gray-700 hover:bg-gray-50 font-semibold rounded-lg text-sm">
                        Batal
                    </a>
                    <button type="submit"
                            class="flex-1 h-10 text-white font-semibold rounded-lg text-sm hover:bg-blue-700 transition-colors"
                            style="background-color: #2563eb;">
                        <i class="fas fa-plus mr-1"></i>
                        Buat Project
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<?php
$content = ob_get_clean();
renderSidebarLayout('dashboard', $content, 'Buat Project - TaskHub');
?>
