<?php
require_once __DIR__ . '/../../assets/helpers/libs.php';
require_once __DIR__ . '/../../assets/helpers/functions.php';
require_once __DIR__ . '/../../assets/helpers/auth_helpers.php';
require_once __DIR__ . '/../../assets/helpers/project_helpers.php';
require_once __DIR__ . '/../../assets/helpers/ui_helpers.php';
require_once __DIR__ . '/../../assets/helpers/task_helpers.php';
require_once __DIR__ . '/../../components/layouts/sidebar_layout.php';

requireActiveUser();

$userId = (int)$_SESSION['user']['id'];
$username = $_SESSION['user']['username'] ?? 'User';

// Handle project actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verifyCsrf();
    
    if ($_POST['action'] === 'change_status') {
        $projectId = (int)$_POST['project_id'];
        $newStatus = $_POST['status'];
        
        // Validate status
        if (!in_array($newStatus, ['draft', 'active', 'archived'], true)) {
            echo json_encode(['success' => false, 'message' => 'Status tidak valid']);
            exit;
        }
        
        // Check if user is project owner
        if (!isProjectOwner($projectId, $userId)) {
            echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki akses untuk mengubah project ini']);
            exit;
        }
        
        // Update project status
        global $conn;
        $stmt = $conn->prepare('UPDATE projects SET status = ? WHERE id = ?');
        $stmt->bind_param('si', $newStatus, $projectId);
        $success = $stmt->execute();
        $stmt->close();
        
        echo json_encode([
            'success' => $success,
            'message' => $success ? "Status project berhasil diubah menjadi {$newStatus}" : 'Gagal mengubah status project'
        ]);
        exit;
    }
    
    if ($_POST['action'] === 'delete_project') {
        $projectId = (int)$_POST['project_id'];
        
        // Check if user is project owner
        if (!isProjectOwner($projectId, $userId)) {
            echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki akses untuk menghapus project ini']);
            exit;
        }
        
        // Delete project (cascade delete will handle tasks and members)
        global $conn;
        $stmt = $conn->prepare('DELETE FROM projects WHERE id = ?');
        $stmt->bind_param('i', $projectId);
        $success = $stmt->execute();
        $stmt->close();
        
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Project berhasil dihapus' : 'Gagal menghapus project'
        ]);
        exit;
    }
}

$userProjects = getProjectsForUser($userId);

// Handle search
$searchQuery = $_GET['search'] ?? '';
if ($searchQuery) {
    $userProjects = array_filter($userProjects, function ($project) use ($searchQuery) {
        return stripos($project['name'], $searchQuery) !== false ||
            stripos($project['description'], $searchQuery) !== false;
    });
}

// Start content buffering
ob_start();
?>

<div class="p-6">
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-gray-900 mb-1">Dashboard</h1>
        <p class="text-sm text-gray-600">Kelola dan pantau semua project Anda</p>
    </div>

    <!-- Search and Create -->
    <div class="flex items-center justify-between mb-6">
        <div class="flex-1 max-w-sm">
            <form method="GET" class="relative">
                <input
                    type="text"
                    name="search"
                    value="<?= htmlspecialchars($searchQuery) ?>"
                    placeholder="Cari project..."
                    class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
            </form>
        </div>

        <a href="<?= url('projects/create') ?>" class="ml-4 bg-brand-blue text-white px-4 py-2 text-sm rounded-md hover:opacity-90 transition-opacity flex items-center">
            <i class="fas fa-plus mr-2 text-xs"></i>
            Buat Project
        </a>
    </div>

    <!-- Project Cards -->
    <?php if (empty($userProjects)): ?>
        <div class="text-center py-12">
            <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                <i class="fas fa-folder-open text-lg text-gray-400"></i>
            </div>
            <h3 class="text-base font-medium text-gray-900 mb-2">
                <?= $searchQuery ? 'Project tidak ditemukan' : 'Belum ada project' ?>
            </h3>
            <p class="text-sm text-gray-500 mb-4 max-w-xs mx-auto">
                <?= $searchQuery ? 'Coba kata kunci yang berbeda' : 'Buat project pertama Anda untuk mulai berkolaborasi' ?>
            </p>
            <?php if (!$searchQuery): ?>
                <a href="<?= url('projects/create') ?>" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 transition-colors">
                    <i class="fas fa-plus mr-2 text-xs"></i>
                    Buat Project
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            <?php foreach ($userProjects as $project): ?>
                <?php
                $taskCount = getTaskCountByProject($project['id']);
                $memberCount = getProjectMemberCount($project['id']);
                ?>
                <div class="group bg-white rounded-lg border border-gray-200 hover:border-gray-300 hover:shadow-md transition-all duration-200 overflow-hidden">
                    <!-- Card Header -->
                    <div class="p-3">
                        <div class="flex items-start justify-between mb-4">
                            <!-- Status Badge -->
                            <div class="flex items-center gap-3">
                                <?php if ($project['status'] === 'active'): ?>
                                    <div class="flex items-center px-1.5 py-0.5 bg-green-100 text-green-700 rounded-full text-xs font-medium">
                                        <div class="w-1 h-1 bg-green-500 rounded-full mr-1"></div>
                                        Aktif
                                    </div>
                                <?php elseif ($project['status'] === 'draft'): ?>
                                    <div class="flex items-center px-1.5 py-0.5 bg-orange-100 text-orange-700 rounded-full text-xs font-medium">
                                        <div class="w-1 h-1 bg-orange-500 rounded-full mr-1"></div>
                                        Draft
                                    </div>
                                <?php else: ?>
                                    <div class="flex items-center px-1.5 py-0.5 bg-gray-100 text-gray-600 rounded-full text-xs font-medium">
                                        <div class="w-1 h-1 bg-gray-400 rounded-full mr-1"></div>
                                        Diarsipkan
                                    </div>
                                <?php endif; ?>

                                <!-- Stats Row -->
                                <div class="flex items-center">
                                    <div class="w-3 h-3 rounded bg-blue-100 flex items-center justify-center mr-1 p-2.5">
                                        <i class="fas fa-tasks text-xs text-blue-600"></i>
                                    </div>
                                    <span class="font-medium text-xs text-gray-700"><?= $taskCount ?></span>
                                </div>

                                <div class="flex items-center">
                                    <div class="w-3 h-3 rounded bg-purple-100 flex items-center justify-center mr-1 p-2.5">
                                        <i class="fas fa-users text-xs text-purple-600"></i>
                                    </div>
                                    <span class="font-medium text-xs text-gray-700"><?= $memberCount ?></span>
                                </div>
                            </div>

                            <!-- Settings -->
                            <div class="relative opacity-0 group-hover:opacity-100 transition-opacity">
                                <button onclick="toggleProjectSettings(<?= $project['id'] ?>)" class="size-5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded flex items-center justify-center">
                                    <i class="fas fa-ellipsis-h text-xs"></i>
                                </button>

                                <div id="projectSettings<?= $project['id'] ?>" class="hidden absolute right-0 top-full mt-1 z-10 bg-white rounded-md shadow-lg border border-gray-200 py-1 min-w-[160px]">
                                    <a href="/projects/<?= $project['id'] ?>/edit" class="flex items-center px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50">
                                        <i class="fas fa-edit w-3 mr-2 text-gray-400"></i>
                                        Edit
                                    </a>
                                    <?php if ($project['status'] === 'active'): ?>
                                        <button onclick="changeProjectStatus(<?= $project['id'] ?>, 'draft')" class="w-full flex items-center px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50">
                                            <i class="fas fa-file-alt w-3 mr-2 text-gray-400"></i>
                                            Jadikan Draft
                                        </button>
                                    <?php else: ?>
                                        <button onclick="changeProjectStatus(<?= $project['id'] ?>, 'active')" class="w-full flex items-center px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50">
                                            <i class="fas fa-play w-3 mr-2 text-gray-400"></i>
                                            Aktifkan
                                        </button>
                                    <?php endif; ?>
                                    <hr class="my-1">
                                    <button onclick="deleteProject(<?= $project['id'] ?>)" class="w-full flex items-center px-3 py-1.5 text-xs text-red-600 hover:bg-red-50">
                                        <i class="fas fa-trash w-3 mr-2 text-red-400"></i>
                                        Hapus
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Project Title -->
                        <h3 class="font-semibold text-gray-900 text-sm mb-1.5 leading-tight">
                            <a href="/projects/<?= $project['id'] ?>" class="hover:text-blue-600 transition-colors">
                                <?= htmlspecialchars($project['name']) ?>
                            </a>
                        </h3>

                        <!-- Description -->
                        <p class="text-gray-600 text-xs leading-relaxed mb-2 line-clamp-2" style="min-height: 2rem;">
                            <?= $project['description'] ? htmlspecialchars($project['description']) : '<span class="text-gray-400 italic">Tidak ada deskripsi</span>' ?>
                        </p>

                    </div>

                    <!-- Card Footer - CTA -->
                    <div class="px-3 py-2 bg-gray-50 border-t border-gray-100">
                        <a href="<?= url('projects/'.$project['id']) ?>"
                            class="flex items-center justify-center w-full py-1.5 px-3 text-xs font-medium text-blue-600 hover:text-blue-700 hover:bg-blue-50 rounded-md transition-colors">
                            <i class="fas fa-arrow-right mr-1 text-xs"></i>
                            Buka Project
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    function toggleProjectSettings(projectId) {
        // Hide all other dropdowns
        $('[id^="projectSettings"]').addClass('hidden');

        // Toggle current dropdown
        $(`#projectSettings${projectId}`).toggleClass('hidden');
    }

    function changeProjectStatus(projectId, status) {
        Swal.fire({
            title: 'Konfirmasi',
            text: `Ubah status project menjadi ${status}?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ya, ubah!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading
                Swal.fire({
                    title: 'Mengubah status...',
                    text: 'Mohon tunggu',
                    icon: 'info',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading()
                    }
                });
                
                // API call to change project status
                $.post('', {
                    action: 'change_status',
                    project_id: projectId,
                    status: status,
                    csrf_token: '<?= $_SESSION['csrf_token'] ?>'
                })
                .done(function(response) {
                    try {
                        const result = JSON.parse(response);
                        if (result.success) {
                            // Success: show result then reload
                            Swal.fire({
                                title: 'Berhasil!', 
                                text: result.message, 
                                icon: 'success',
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            // Failed: show error, no reload
                            Swal.fire('Error!', result.message, 'error');
                        }
                    } catch(e) {
                        Swal.fire('Error!', 'Respon server tidak valid', 'error');
                    }
                })
                .fail(function(xhr, status, error) {
                    Swal.fire('Error!', 'Gagal menghubungi server: ' + error, 'error');
                });
            }
        });
    }

    function deleteProject(projectId) {
        Swal.fire({
            title: 'Hapus Project?',
            text: 'Project dan semua tugas di dalamnya akan dihapus permanen!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading
                Swal.fire({
                    title: 'Menghapus project...',
                    text: 'Mohon tunggu',
                    icon: 'info',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading()
                    }
                });
                
                // API call to delete project
                $.post('', {
                    action: 'delete_project',
                    project_id: projectId,
                    csrf_token: '<?= $_SESSION['csrf_token'] ?>'
                })
                .done(function(response) {
                    try {
                        const result = JSON.parse(response);
                        if (result.success) {
                            // Success: show result then reload
                            Swal.fire({
                                title: 'Terhapus!', 
                                text: result.message, 
                                icon: 'success',
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            // Failed: show error, no reload
                            Swal.fire('Error!', result.message, 'error');
                        }
                    } catch(e) {
                        Swal.fire('Error!', 'Respon server tidak valid', 'error');
                    }
                })
                .fail(function(xhr, status, error) {
                    Swal.fire('Error!', 'Gagal menghubungi server: ' + error, 'error');
                });
            }
        });
    }

    // Hide dropdowns when clicking outside
    $(document).click(function(e) {
        if (!$(e.target).closest('.relative').length) {
            $('[id^="projectSettings"]').addClass('hidden');
        }
    });
</script>

<?php
$content = ob_get_clean();

// Render with sidebar layout
renderSidebarLayout('dashboard', $content, 'Dashboard - TaskHub');
?>
