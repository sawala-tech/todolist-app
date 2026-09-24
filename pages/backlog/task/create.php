<?php
require_once __DIR__ . '/../../../assets/helpers/libs.php';
require_once __DIR__ . '/../../../assets/helpers/functions.php';
require_once __DIR__ . '/../../../assets/helpers/auth_helpers.php';
require_once __DIR__ . '/../../../components/layouts/sidebar_layout.php';

requireActiveUser();

$userId = (int)$_SESSION['user']['id'];
$error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

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
            'INSERT INTO tasks (title, description, deadline, priority, label, status, user_id, project_id, attachment)
             VALUES (?, ?, ?, ?, ?, "open", ?, NULL, "")'
        );
        $stmt->bind_param('sssssi', $title, $description, $deadline, $priority, $label, $userId);

        if ($stmt->execute()) {
            $_SESSION['flash_success'] = 'Tugas pribadi berhasil dibuat!';
            $stmt->close();
            header('Location: ' . url('backlog'));
            exit;
        }

        $error = 'Gagal membuat tugas: ' . $conn->error;
        $stmt->close();
    }
}

$pageTitle    = 'Buat Tugas';
$pageSubtitle = 'Tambahkan tugas pribadi ke backlog Anda';
$breadcrumbs  = [
    ['label' => 'Dashboard', 'url' => url('dashboard')],
    ['label' => 'Backlog', 'url' => url('backlog')],
    ['label' => 'Buat Tugas'],
];
$headerActions = '';

ob_start();
?>

<main>
    <?php include __DIR__ . '/../../../components/partials/page_header.php'; ?>

    <div class="p-4 md:p-6">
        <div class="max-w-2xl bg-white border border-gray-200 shadow-sm rounded-xl p-5">
            <?php if ($error): ?>
            <div class="mb-4 px-3 py-2 bg-red-50 border border-red-200 rounded-lg">
                <p class="text-sm text-red-700">
                    <i class="fas fa-circle-exclamation mr-1"></i><?= htmlspecialchars($error) ?>
                </p>
            </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                <?= csrfField() ?>

                <div class="flex flex-col gap-1.5">
                    <label for="title" class="text-sm font-semibold text-gray-700">
                        Judul Tugas <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="title" name="title" required
                           value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                           placeholder="Masukkan judul tugas"
                           class="h-10 px-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="description" class="text-sm font-semibold text-gray-700">Deskripsi</label>
                    <textarea id="description" name="description" rows="3"
                              placeholder="Deskripsi tugas (opsional)"
                              class="px-3 py-2 border border-gray-300 rounded-lg text-sm resize-none focus:outline-none focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div class="flex flex-col gap-1.5">
                        <label for="priority" class="text-sm font-semibold text-gray-700">Prioritas</label>
                        <select id="priority" name="priority"
                                class="h-10 px-3 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="low" <?= ($_POST['priority'] ?? 'medium') === 'low' ? 'selected' : '' ?>>Rendah</option>
                            <option value="medium" <?= ($_POST['priority'] ?? 'medium') === 'medium' ? 'selected' : '' ?>>Sedang</option>
                            <option value="high" <?= ($_POST['priority'] ?? 'medium') === 'high' ? 'selected' : '' ?>>Tinggi</option>
                        </select>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="deadline" class="text-sm font-semibold text-gray-700">Tenggat</label>
                        <input type="date" id="deadline" name="deadline"
                               value="<?= htmlspecialchars($_POST['deadline'] ?? '') ?>"
                               class="h-10 px-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="label" class="text-sm font-semibold text-gray-700">Label</label>
                        <input type="text" id="label" name="label"
                               value="<?= htmlspecialchars($_POST['label'] ?? '') ?>"
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
                        <i class="fas fa-plus mr-1"></i>Buat Tugas
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<?php
$content = ob_get_clean();
renderSidebarLayout('backlog', $content, 'Buat Tugas - TaskHub');
?>
