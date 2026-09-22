<?php
require_once __DIR__ . '/../../assets/helpers/libs.php';
require_once __DIR__ . '/../../assets/helpers/functions.php';
require_once __DIR__ . '/../../assets/helpers/auth_helpers.php';
require_once __DIR__ . '/../../assets/helpers/project_helpers.php';
require_once __DIR__ . '/../../components/layouts/sidebar_layout.php';

requireActiveUser();

$userId = (int)$_SESSION['user']['id'];

function getRecentActivityForUser(int $userId, int $offset = 0, int $limit = 15): array
{
    global $conn;

    $offset = max(0, $offset);
    $limit = max(1, $limit);
    $fetchLimit = $offset + $limit + 1;
    $activities = [];

    // Project yang dibuat oleh user atau project yang diikuti.
    $stmt = $conn->prepare(
        "SELECT 'project_created' AS action_type, p.name AS target_name, p.id AS target_id,
                p.created_at AS action_time, u.username AS actor_name, p.description AS description
         FROM projects p
         JOIN users u ON u.id = p.owner_id
         WHERE p.owner_id = ? OR p.id IN (SELECT project_id FROM project_members WHERE user_id = ?)
         ORDER BY p.created_at DESC
         LIMIT {$fetchLimit}"
    );
    $stmt->bind_param('ii', $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $activities[] = $row;
    }
    $stmt->close();

    // Tugas terbaru di project user.
    $stmt = $conn->prepare(
        "SELECT 'task_created' AS action_type, t.title AS target_name, t.id AS target_id,
                t.project_id, NULL AS action_time, u.username AS actor_name, p.name AS project_name,
                t.status, t.priority
         FROM tasks t
         JOIN projects p ON p.id = t.project_id
         LEFT JOIN users u ON u.id = t.user_id
         WHERE (p.owner_id = ? OR p.id IN (SELECT project_id FROM project_members WHERE user_id = ?))
         ORDER BY t.id DESC
         LIMIT {$fetchLimit}"
    );
    $stmt->bind_param('ii', $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        // Tabel tugas lama belum memiliki created_at, jadi ID dipakai sebagai pendekatan waktu.
        $row['action_time'] = date('Y-m-d H:i:s', time() - (1000000 - $row['target_id']) * 60);
        $activities[] = $row;
    }
    $stmt->close();

    // Penambahan anggota terbaru.
    $stmt = $conn->prepare(
        "SELECT 'member_added' AS action_type,
                CONCAT(u1.username, ' → ', p.name) AS target_name,
                p.id AS target_id, pm.created_at AS action_time,
                u2.username AS actor_name, p.name AS project_name
         FROM project_members pm
         JOIN projects p ON p.id = pm.project_id
         JOIN users u1 ON u1.id = pm.user_id
         JOIN users u2 ON u2.id = pm.added_by
         WHERE p.owner_id = ? OR pm.user_id = ?
         ORDER BY pm.created_at DESC
         LIMIT {$fetchLimit}"
    );
    $stmt->bind_param('ii', $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $activities[] = $row;
    }
    $stmt->close();

    usort($activities, static function (array $a, array $b): int {
        $timeA = !empty($a['action_time']) ? strtotime($a['action_time']) : 0;
        $timeB = !empty($b['action_time']) ? strtotime($b['action_time']) : 0;
        return $timeB <=> $timeA;
    });

    $page = array_slice($activities, $offset, $limit + 1);

    return [
        'items' => array_slice($page, 0, $limit),
        'has_more' => count($page) > $limit,
    ];
}

function timeAgo(?string $datetime): string
{
    if (!$datetime) return 'Tidak diketahui';

    $time = time() - strtotime($datetime);
    if ($time < 60) return 'Baru saja';
    if ($time < 3600) return floor($time / 60) . ' menit lalu';
    if ($time < 86400) return floor($time / 3600) . ' jam lalu';
    if ($time < 2592000) return floor($time / 86400) . ' hari lalu';
    if ($time < 31536000) return floor($time / 2592000) . ' bulan lalu';
    return floor($time / 31536000) . ' tahun lalu';
}

$activityOffset = max(0, (int)($_GET['offset'] ?? 0));
$activityLimit  = 15;
$activityPage   = getRecentActivityForUser($userId, $activityOffset, $activityLimit);
$activities     = $activityPage['items'];
$hasMore        = $activityPage['has_more'];

// Permintaan parsial dipakai oleh tombol "Muat lebih banyak" agar item baru ditambahkan
// tanpa menggambar ulang seluruh layout halaman.
if (($_GET['partial'] ?? '') === '1') {
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Activity-Has-More: ' . ($hasMore ? '1' : '0'));
    header('X-Activity-Count: ' . count($activities));

    foreach ($activities as $activity) {
        include __DIR__ . '/../../components/partials/activity_item.php';
    }
    exit;
}

ob_start();
?>

<div class="p-4 md:p-6">
    <div class="mb-5">
        <h1 class="text-xl font-semibold text-gray-900 mb-1">Aktivitas</h1>
        <p class="text-sm text-gray-600">Log aktivitas project dan tugas Anda</p>
    </div>

    <?php if (empty($activities) && $activityOffset === 0): ?>
        <div class="text-center py-8">
            <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                <i class="fas fa-clock text-lg text-gray-400"></i>
            </div>
            <h3 class="text-base font-medium text-gray-900 mb-1">Belum ada aktivitas</h3>
            <p class="text-sm text-gray-500">Aktivitas project dan tugas akan muncul di sini</p>
        </div>
    <?php elseif (empty($activities)): ?>
        <div class="bg-white border border-gray-200 rounded-lg p-6 text-center text-sm text-gray-500">
            Tidak ada aktivitas lainnya.
        </div>
    <?php else: ?>
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <div id="activityFeed" class="divide-y divide-gray-200">
                <?php foreach ($activities as $activity): ?>
                    <?php include __DIR__ . '/../../components/partials/activity_item.php'; ?>
                <?php endforeach; ?>
            </div>

            <?php if ($hasMore): ?>
            <div id="activityLoadMore" class="px-4 py-3 bg-gray-50 border-t border-gray-200 text-center">
                <button id="loadMoreActivities" type="button"
                        data-offset="<?= $activityOffset + count($activities) ?>"
                        data-endpoint="<?= htmlspecialchars(url('activity')) ?>"
                        class="text-xs font-semibold text-blue-600 hover:text-blue-700 disabled:opacity-50">
                    Muat lebih banyak aktivitas
                </button>
            </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($hasMore && !empty($activities)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const button = document.getElementById('loadMoreActivities');
    const feed = document.getElementById('activityFeed');
    if (!button || !feed) return;

    let offset = Number(button.dataset.offset || 0);
    const endpoint = button.dataset.endpoint;

    button.addEventListener('click', async function () {
        button.disabled = true;
        button.textContent = 'Memuat...';

        try {
            const response = await fetch(endpoint + '?partial=1&offset=' + offset, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!response.ok) throw new Error('Gagal memuat aktivitas.');

            const html = await response.text();
            const count = Number(response.headers.get('X-Activity-Count') || 0);
            feed.insertAdjacentHTML('beforeend', html);
            offset += count;

            if (response.headers.get('X-Activity-Has-More') !== '1' || count === 0) {
                document.getElementById('activityLoadMore')?.remove();
                return;
            }

            button.disabled = false;
            button.textContent = 'Muat lebih banyak aktivitas';
        } catch (error) {
            button.disabled = false;
            button.textContent = 'Muat lebih banyak aktivitas';
            if (window.Swal) {
                Swal.fire({ icon: 'error', title: error.message, timer: 2200, showConfirmButton: false });
            } else {
                alert(error.message);
            }
        }
    });
});
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
renderSidebarLayout('activity', $content, 'Aktivitas - TaskHub');
?>
