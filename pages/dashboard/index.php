<?php
require_once __DIR__ . '/../../assets/helpers/libs.php';
require_once __DIR__ . '/../../assets/helpers/functions.php';

checkLogin('auth/signin');

if (isAdmin()) {
    header('Location: ' . url('admin'));
    exit;
}

$flashAlert = $_SESSION['flash_alert'] ?? null;
unset($_SESSION['flash_alert']);

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    header('Content-Type: application/json');
    $id = $_GET['id'] ?? null;

    if ($id && deleteTask($id)) {
        $_SESSION['flash_alert'] = [
            'title' => 'Berhasil menghapus tugas!',
            'icon' => 'success',
        ];
        echo json_encode(['success' => true]);
    } else {
        $_SESSION['flash_alert'] = [
            'title' => 'Gagal menghapus tugas!',
            'icon' => 'error',
        ];
        http_response_code(400);
        echo json_encode(['success' => false]);
    }

    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === "POST" && isset($_POST['_method']) && $_POST['_method'] === "PUT") {
    $method = "PUT";
}


if ($method === 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $deadline = $_POST['deadline'];
    $attachment = $_FILES['attachment'] ?? ['error' => UPLOAD_ERR_NO_FILE];
    $status = $_POST['status'];

    if (addTask($title, $description, $deadline, $attachment, $status)) {
        $_SESSION['flash_alert'] = [
            'title' => 'Berhasil menambahkan tugas!',
            'icon' => 'success',
        ];
        header('Location: ' . url('dashboard'));
        exit;
    } else {
        $_SESSION['flash_alert'] = [
            'title' => 'Gagal menambahkan tugas!',
            'icon' => 'error',
        ];
        header('Location: ' . url('dashboard'));
        exit;
    }
}

if ($method === 'PUT') {
    $id = $_POST['id'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $deadline = $_POST['deadline'];
    $attachment = $_FILES['attachment'] ?? ['error' => UPLOAD_ERR_NO_FILE];
    $replaceAttachment = isset($_POST['replace_attachment']) && $_POST['replace_attachment'] === '1';
    $status = $_POST['status'];

    if (updateTask($id, $title, $description, $deadline, $attachment, $status, $replaceAttachment)) {
        $_SESSION['flash_alert'] = [
            'title' => 'Berhasil mengubah tugas!',
            'icon' => 'success',
        ];
        header('Location: ' . url('dashboard'));
        exit;
    } else {
        $_SESSION['flash_alert'] = [
            'title' => 'Gagal mengubah tugas!',
            'icon' => 'error',
        ];
        header('Location: ' . url('dashboard'));
        exit;
    }
}

$tasks = getTasks();
include components('templates/header');

if ($flashAlert && isset($flashAlert['title'], $flashAlert['icon'])) {
    $alertTitle = json_encode((string) $flashAlert['title']);
    $alertIcon = json_encode((string) $flashAlert['icon']);
    echo "<script>Swal.fire({title: $alertTitle, icon: $alertIcon});</script>";
}

// Task Statuses & Initial Count
$statuses = [
    'open' => ['title' => 'Open', 'color' => 'bg-teal-500', 'bg' => 'bg-blue-100', 'icon' => 'sun.svg'],
    'in_progress' => ['title' => 'In Progress', 'color' => 'bg-blue-500', 'bg' => 'bg-gray-100', 'icon' => 'sync.svg'],
    'done' => ['title' => 'Done', 'color' => 'bg-purple-500', 'bg' => 'bg-blue-100', 'icon' => 'checked.svg']
];

// Count tasks per status
$taskCount = array_fill_keys(array_keys($statuses), 0);
foreach ($tasks as $task) {
    $taskCount[$task['status']]++;
}

?>

<main class="mt-[6.4rem]">
    <!-- Filter -->
<div class="max-sm:flex max-sm:w-full max-sm:justify-center max-sm:overflow-x-auto max-sm:gap-3 max-sm:pb-1 max-sm:[-ms-overflow-style:none] max-sm:[scrollbar-width:none] max-sm:[&::-webkit-scrollbar]:hidden md:grid md:grid-cols-3 md:gap-0">
        <?php foreach ($statuses as $key => $status): ?>
            <div class="flex items-center space-x-2 text-white md:px-6 md:py-4 max-sm:rounded-full pr-[12.5px] p-2 max-sm:w-max max-sm:shrink-0 <?= $status['color'] ?>" id="filterTodoTrigger" data-type="<?= $key ?>">
                <img src="<?= assets('images/icons/' . $status['icon']) ?>" alt="icon" class="w-5 h-5 md:w-6 md:h-6" />
                <h3 class="text-sm font-semibold md:text-lg"><?= $status['title'] ?> <span class="max-sm:hidden">(<?= $taskCount[$key] ?>)</span></h3>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Task List -->
    <div class="grid grid-cols-3">
        <?php foreach ($statuses as $key => $status): ?>
            <div class="flex flex-col h-full min-h-[calc(100vh-11rem)] space-y-4 p-4 max-sm:col-span-3 md:<?= $status['bg'] ?>" id="todoListWrapper" data-type="<?= $key ?>">
                <?php if ($taskCount[$key] === 0): ?>
                    <!-- Empty State -->
                    <div class="flex flex-col items-center justify-center h-full text-center text-gray-400">
                        <img src="<?= assets('images/icons/edit.svg') ?>" alt="edit" class="w-20 h-20 mb-4" />
                        <h4 class="font-semibold">Belum ada tugas</h4>
                        <p class="max-sm:max-w-72">Segera tambahkan tugas baru kamu sekarang!</p>
                    </div>
                <?php else: ?>
                    <!-- Task Cards -->
                    <?php foreach ($tasks as $task): ?>
                        <?php if ($task['status'] === $key): ?>
                            <?php
                            $safeTitle = trim((string) ($task['title'] ?? ''));
                            $safeDescription = trim((string) ($task['description'] ?? ''));
                            $safeAttachment = trim((string) ($task['attachment'] ?? ''));
                            $safeDeadline = trim((string) ($task['deadline'] ?? ''));
                            $formattedDeadline = $safeDeadline !== '' && strtotime($safeDeadline) !== false
                                ? date("d M Y", strtotime($safeDeadline))
                                : '-';
                            ?>
                            <div class="flex flex-col w-full p-4 space-y-2 bg-white rounded-lg shadow-md">
                                <div class="flex flex-col space-y-2">
                                    <h4 class="font-semibold"><?= $safeTitle !== '' ? htmlspecialchars($safeTitle) : '-' ?></h4>
                                    <p class="text-gray-600 line-clamp-2"><?= $safeDescription !== '' ? htmlspecialchars($safeDescription) : '-' ?></p>
                                    <div class="flex items-center space-x-2">
                                        <img src="<?= assets('images/icons/files.svg') ?>" alt="files" class="w-5 h-5" />
                                        <?php if ($safeAttachment !== ''): ?>
                                            <a href="<?= htmlspecialchars(assets("public/" . $safeAttachment)) ?>" target="_blank" class="text-blue-500 truncate hover:underline max-w-96">
                                                <?= htmlspecialchars(basename($safeAttachment)) ?>
                                            </a>
                                        <?php else: ?>
                                            <p class="text-gray-600">-</p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <img src="<?= assets('images/icons/clock.svg') ?>" alt="clock" class="w-5 h-5" />
                                        <p>Tenggat Waktu: <?= $formattedDeadline ?></p>
                                    </div>
                                </div>
                                <div class="flex space-x-2">
                                    <button
                                        id="deleteTodoTrigger"
                                        class=" text-[#E53E3E] font-semibold hover:text-red-700"
                                        data-id="<?= htmlspecialchars($task['id']); ?>"
                                        data-title="<?= htmlspecialchars($task['title']); ?>"
                                        data-description="<?= htmlspecialchars($task['description']); ?>"
                                        data-attachment="<?= htmlspecialchars($task['attachment']); ?>"
                                        data-deadline="<?= htmlspecialchars($task['deadline']); ?>"
                                        data-status="<?= htmlspecialchars($task['status']); ?>">
                                        Hapus
                                    </button>
                                    <div class="w-px h-full bg-[#CBD5E0]"></div>
                                    <button
                                        id="editTodoTrigger"
                                        class=" text-[#3182CE] font-semibold hover:text-blue-700"
                                        data-id="<?= htmlspecialchars($task['id']); ?>"
                                        data-title="<?= htmlspecialchars($task['title']); ?>"
                                        data-description="<?= htmlspecialchars($task['description']); ?>"
                                        data-attachment="<?= htmlspecialchars($task['attachment']); ?>"
                                        data-deadline="<?= htmlspecialchars($task['deadline']); ?>"
                                        data-status="<?= htmlspecialchars($task['status']); ?>">
                                        Edit
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Add Todo Modal -->
    <div id="addTodoModal" class="fixed inset-0 z-50 items-center justify-center hidden bg-gray-900 bg-opacity-50">
        <div class="w-full md:max-w-[650px] p-6 bg-white rounded-2xl shadow-lg flex flex-col space-y-6 max-sm:mx-4">
            <h2 class="mb-2 text-xl font-bold">Tugas Baru</h2>
            <form class="flex flex-col space-y-4" action="<?= url('dashboard') ?>" method="POST" enctype="multipart/form-data">
                <div class="flex gap-2 md:items-center max-sm:flex-col">
                    <label class="text-sm font-semibold md:w-1/4">Nama Tugas</label>
                    <input type="text" class="md:w-3/4 h-10 px-3 py-2.5 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-emerald-500" placeholder="Judul Tugas" name="title" />
                </div>
                <div class="flex gap-2 md:items-center max-sm:flex-col">
                    <label class="text-sm font-semibold md:w-1/4">Deskripsi</label>
                    <textarea class="md:w-3/4 min-h-20 px-3 py-2.5 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-emerald-500" placeholder="Deskripsi Tugas" name="description"></textarea>
                </div>
                <div class="flex gap-2 md:items-center max-sm:flex-col">
                    <label class="text-sm font-semibold md:w-1/4">Tenggat Waktu</label>
                    <div class="relative md:w-3/4">
                        <input id="datepicker-format" datepicker datepicker-format="yyyy-mm-dd" type="text" class="w-full h-10 px-3 py-2.5 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-emerald-500 cursor-pointer" placeholder="Pilih Tanggal" name="deadline" autocomplete="off">
                        <div class="absolute inset-y-0 flex items-center pointer-events-none end-4 ps-3">
                            <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M20 4a2 2 0 0 0-2-2h-2V1a1 1 0 0 0-2 0v1h-3V1a1 1 0 0 0-2 0v1H6V1a1 1 0 0 0-2 0v1H2a2 2 0 0 0-2 2v2h20V4ZM0 18a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8H0v10Zm5-8h10a1 1 0 0 1 0 2H5a1 1 0 0 1 0-2Z" />
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="flex gap-2 md:items-center max-sm:flex-col">
                    <label class="text-sm font-semibold md:w-1/4">Lampiran/File</label>
                    <input type="file" class="border border-gray-300 rounded-md md:w-3/4 focus:outline-none focus:ring-2 focus:ring-emerald-500" name="attachment" />
                </div>
                <div class="flex gap-2 md:items-center max-sm:flex-col">
                    <label class="md:w-1/4">Status</label>
                    <select class="md:w-3/4 h-10 px-3 py-2.5 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-emerald-500 text-sm" name="status">
                        <option value="open">Open</option>
                        <option value="in_progress">In Progress</option>
                        <option value="done">Done</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <button class="px-4 py-2 mt-4 text-white rounded-md bg-emerald-500 hover:bg-emerald-700" type="submit">Simpan</button>
                    <button id="closeAddTodoModal" class="px-4 py-2 mt-4 text-black bg-white border border-gray-400 rounded-md hover:bg-gray-100" type="button">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Todo Modal -->
    <div id="editTodoModal" class="fixed inset-0 z-50 items-center justify-center hidden bg-gray-900 bg-opacity-50">
        <div class="w-full md:max-w-[650px] p-6 bg-white rounded-2xl shadow-lg flex flex-col space-y-6 max-sm:mx-4">
            <h2 class="mb-2 text-xl font-bold">Edit Tugas</h2>
            <form class="flex flex-col space-y-4" action="<?= url('dashboard') ?>" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="_method" value="PUT" />
                <input type="hidden" name="id" id="editTodoId" />
                <input type="hidden" name="replace_attachment" id="editReplaceAttachment" value="0" />
                <div class="flex gap-2 md:items-center max-sm:flex-col">
                    <label class="text-sm font-semibold md:w-1/4">Nama Tugas</label>
                    <input type="text" class="md:w-3/4 h-10 px-3 py-2.5 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-emerald-500" placeholder="Judul Tugas" name="title" />
                </div>
                <div class="flex gap-2 md:items-center max-sm:flex-col">
                    <label class="text-sm font-semibold md:w-1/4">Deskripsi</label>
                    <textarea class="md:w-3/4 min-h-20 px-3 py-2.5 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-emerald-500" placeholder="Deskripsi Tugas" name="description"></textarea>
                </div>
                <div class="flex gap-2 md:items-center max-sm:flex-col">
                    <label class="text-sm font-semibold md:w-1/4">Tenggat Waktu</label>
                    <div class="relative md:w-3/4">
                        <input id="datepicker-formate" datepicker datepicker-formate="yyyy-mm-dd" type="text" class="w-full h-10 px-3 py-2.5 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-emerald-500 cursor-pointer" placeholder="Pilih Tanggal" name="deadline" autocomplete="off">
                        <div class="absolute inset-y-0 flex items-center pointer-events-none end-4 ps-3">
                            <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M20 4a2 2 0 0 0-2-2h-2V1a1 1 0 0 0-2 0v1h-3V1a1 1 0 0 0-2 0v1H6V1a1 1 0 0 0-2 0v1H2a2 2 0 0 0-2 2v2h20V4ZM0 18a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8H0v10Zm5-8h10a1 1 0 0 1 0 2H5a1 1 0 0 1 0-2Z" />
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="flex gap-2 md:items-center max-sm:flex-col">
                    <label class="text-sm font-semibold md:w-1/4">Lampiran/File</label>
                    <input type="file" class="border border-gray-300 rounded-md md:w-3/4 focus:outline-none focus:ring-2 focus:ring-emerald-500" name="attachment" />
                </div>
                <div class="flex gap-2 max-sm:flex-col">
                    <div class="md:w-1/4"></div>
                    <p class="text-sm text-gray-500 md:w-3/4">
                        File saat ini:
                        <a id="editCurrentAttachment" href="#" target="_blank" class="text-blue-500 hover:underline">-</a>
                    </p>
                </div>
                <div class="flex gap-2 md:items-center max-sm:flex-col">
                    <label class="md:w-1/4">Status</label>
                    <select class="md:w-3/4 h-10 px-3 py-2.5 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-emerald-500 text-sm" name="status">
                        <option value="open">Open</option>
                        <option value="in_progress">In Progress</option>
                        <option value="done">Done</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <button class="px-4 py-2 mt-4 text-white rounded-md bg-emerald-500 hover:bg-emerald-700" type="submit">Simpan</button>
                    <button id="closeEditTodoModal" class="px-4 py-2 mt-4 text-black bg-white border border-gray-400 rounded-md hover:bg-gray-100" type="button">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Todo Modal -->
    <div id="deleteTodoModal" class="fixed inset-0 z-50 items-center justify-center hidden bg-gray-900 bg-opacity-50">
        <div class="w-full md:max-w-[650px] p-6 bg-white rounded-2xl shadow-lg flex flex-col space-y-6 max-sm:mx-4">
            <h2 class="mb-2 text-xl font-bold">Hapus Tugas</h2>
            <p class="text-gray-600">Apakah Anda yakin ingin menghapus tugas ini?</p>
            <div class="flex flex-col p-4 space-y-2 shadow rounded-xl">
                <h4 class="font-semibold" id="modal-title"></h4>
                <p class="text-gray-600 line-clamp-2" id="modal-description"></p>
                <div class="flex items-center gap-2">
                    <img src="<?= assets('images/icons/files.svg') ?>" alt="files" class="w-5 h-5" />
                    <a id="modal-attachment" href="#" target="_blank" class="text-blue-500 truncate hover:underline max-w-96"></a>
                </div>
                <div class="flex items-center gap-2">
                    <img src="<?= assets('images/icons/clock.svg') ?>" alt="clock" class="w-5 h-5" />
                    <p id="modal-deadline"></p>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <button class="px-4 py-2 mt-4 text-white bg-red-500 rounded-md hover:bg-red-700" id="confirmDeleteTodoTrigger">Ya, Hapus</button>
                <button id="closeDeleteTodoModal" class="px-4 py-2 mt-4 text-black bg-white border border-gray-400 rounded-md hover:bg-gray-100" type="button">Batal</button>
            </div>
        </div>
    </div>
</main>

<?php include components('templates/footer'); ?>