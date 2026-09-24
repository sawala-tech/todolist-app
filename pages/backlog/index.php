<?php
require_once __DIR__ . '/../../assets/helpers/libs.php';
require_once __DIR__ . '/../../assets/helpers/functions.php';
require_once __DIR__ . '/../../assets/helpers/auth_helpers.php';
require_once __DIR__ . '/../../components/layouts/sidebar_layout.php';

requireActiveUser();

$userId = (int)$_SESSION['user']['id'];

// Handle quick add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_add'])) {
    verifyCsrf();
    
    $title = trim($_POST['title'] ?? '');
    
    if ($title !== '') {
        // Create minimal personal task for brain dump
        $stmt = $conn->prepare(
            'INSERT INTO tasks (title, description, deadline, priority, label, status, user_id, project_id, attachment)
             VALUES (?, "", NULL, "medium", "", "open", ?, NULL, "")'
        );
        $stmt->bind_param('si', $title, $userId);
        
        if ($stmt->execute()) {
        $_SESSION['flash_success'] = 'Tugas berhasil ditambahkan ke backlog!';
        }
        $stmt->close();
    }
    
    header('Location: '. url('backlog'));
    exit;
}

// Get personal tasks (tasks without project_id) for backlog
global $conn;
$stmt = $conn->prepare(
    "SELECT t.*, u.username AS assigned_username
     FROM tasks t
     LEFT JOIN users u ON u.id = t.assigned_to
     WHERE t.user_id = ? AND t.project_id IS NULL
     ORDER BY t.deadline ASC, t.id DESC"
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$backlogTasks = [];
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $backlogTasks[] = $row;
}
$res->free();
$stmt->close();

// Handle task status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verifyCsrfToken();
    
    if ($_POST['action'] === 'start_task' && isset($_POST['task_id'])) {
        $taskId = (int)$_POST['task_id'];
        
        // Update personal task to in_progress
        $stmt = $conn->prepare('UPDATE tasks SET status = "in_progress" WHERE id = ? AND user_id = ?');
        $stmt->bind_param('ii', $taskId, $userId);
        $success = $stmt->execute();
        $stmt->close();
        
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Tugas dimulai!' : 'Gagal memulai tugas.'
        ]);
        exit;
    }
    
    if ($_POST['action'] === 'complete_task' && isset($_POST['task_id'])) {
        $taskId = (int)$_POST['task_id'];
        
        // Update personal task to done
        $stmt = $conn->prepare('UPDATE tasks SET status = "done" WHERE id = ? AND user_id = ?');
        $stmt->bind_param('ii', $taskId, $userId);
        $success = $stmt->execute();
        $stmt->close();
        
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Tugas selesai!' : 'Gagal menyelesaikan tugas.'
        ]);
        exit;
    }
}

// Start content buffering
ob_start();

// Show flash messages
if (isset($_SESSION['flash_success'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: 'success',
        title: '<?= addslashes($_SESSION['flash_success']) ?>',
        timer: 3000,
        showConfirmButton: false
    });
});
</script>
<?php 
unset($_SESSION['flash_success']);
endif;
?>

<div class="p-4 md:p-6">
    <!-- Header -->
    <div class="flex items-start justify-between gap-4 mb-5">
        <div>
            <h1 class="text-xl font-semibold text-gray-900 mb-1">Backlog</h1>
            <p class="text-sm text-gray-600">Simpan draf tugas pribadi sebelum mulai mengerjakannya.</p>
        </div>
    </div>

    <!-- Quick Add Form -->
    <div class="mb-6">
        <form method="POST" action="" class="flex items-center gap-3">
            <?= csrfField() ?>
            <input type="hidden" name="quick_add" value="1">
            <div class="flex-1">
                <input type="text" 
                       name="title" 
                       placeholder="Tambah tugas..."
                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                       required>
            </div>
            <button type="submit" 
                    class="px-4 py-2 text-sm text-white rounded-md hover:opacity-90 transition-opacity" 
                    style="background-color: #2563eb;">
                <i class="fas fa-plus mr-1"></i>
                Tambah
            </button>
        </form>
    </div>

    <!-- Backlog Tasks -->
    <?php if (empty($backlogTasks)): ?>
        <div class="text-center py-8">
            <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                <i class="fas fa-tasks text-lg text-gray-400"></i>
            </div>
            <h3 class="text-base font-medium text-gray-900 mb-2">Belum ada item backlog</h3>
            <p class="text-sm text-gray-500">Gunakan form di atas untuk menambahkan item pertama Anda</p>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-md border border-gray-200 divide-y divide-gray-200 overflow-hidden">
            <?php foreach ($backlogTasks as $task): ?>
                        <a href="<?= url('backlog/task/'.(int)$task['id']) ?>" class="block p-4 hover:bg-gray-50 transition-colors">
                    <h4 class="text-sm font-medium text-gray-900">
                        <?= htmlspecialchars($task['title']) ?>
                    </h4>
                    <?php if (!empty($task['description'])): ?>
                        <p class="text-xs text-gray-500 mt-0.5 line-clamp-1">
                            <?= htmlspecialchars($task['description']) ?>
                        </p>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function startTask(taskId) {
    Swal.fire({
        title: 'Mulai Tugas?',
        text: 'Tugas akan dipindahkan ke status Sedang Dikerjakan',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#2563eb',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Ya, mulai!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('', {
                action: 'start_task',
                task_id: taskId,
                csrf_token: '<?= $_SESSION['csrf_token'] ?>'
            })
            .done(function(response) {
                const result = JSON.parse(response);
                if (result.success) {
                    Swal.fire('Berhasil!', result.message, 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error!', result.message, 'error');
                }
            });
        }
    });
}

function completeTask(taskId) {
    Swal.fire({
        title: 'Selesaikan Tugas?',
        text: 'Tugas akan ditandai sebagai selesai',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Ya, selesai!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('', {
                action: 'complete_task',
                task_id: taskId,
                csrf_token: '<?= $_SESSION['csrf_token'] ?>'
            })
            .done(function(response) {
                const result = JSON.parse(response);
                if (result.success) {
                    Swal.fire('Berhasil!', result.message, 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error!', result.message, 'error');
                }
            });
        }
    });
}
</script>

<?php
// Helper function for priority colors
function getPriorityBrandColor($priority) {
    switch ($priority) {
        case 'high': return 'bg-red-100 text-red-800';
        case 'medium': return 'bg-orange-100 text-orange-800';
        case 'low': return 'bg-green-100 text-green-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

$content = ob_get_clean();
renderSidebarLayout('backlog', $content, 'Backlog - TaskHub');
?>
