<?php
require_once __DIR__ . '/../../assets/helpers/libs.php';
require_once __DIR__ . '/../../assets/helpers/functions.php';

checkAdmin('dashboard');

$tasks = getAllTasksWithUsers();
$users = getAllUsersWithRole();

$totalTasks = count($tasks);
$totalUsers = count($users);
$totalAdmins = 0;
$statusCount = [
    'open' => 0,
    'in_progress' => 0,
    'done' => 0,
];

foreach ($users as $user) {
    if (($user['role_name'] ?? '') === 'admin') {
        $totalAdmins++;
    }
}

foreach ($tasks as $task) {
    $status = $task['status'] ?? '';
    if (isset($statusCount[$status])) {
        $statusCount[$status]++;
    }
}

include components('templates/header');
?>

<main class="mt-[6.4rem] space-y-6">
    <section class="relative p-6 overflow-hidden text-white bg-green-500 shadow-lg bg-gradient-to-r from-blue-900 via-blue-700 to-indigo-900">
        <div class="relative z-10">
            <p class="text-xs tracking-[0.18em] uppercase text-blue-200">Admin Panel</p>
            <h2 class="mt-2 text-2xl font-bold md:text-3xl">Dashboard Kontrol Data</h2>
            <p class="mt-2 text-sm text-blue-100">Kelola seluruh task dan user dalam satu halaman dengan ringkasan yang lebih jelas.</p>
        </div>
        <div class="absolute w-40 h-40 rounded-full -right-10 -top-10 bg-indigo-300/15"></div>
        <div class="absolute w-24 h-24 rounded-full right-20 bottom-4 bg-blue-300/15"></div>
    </section>

    <section class="grid grid-cols-1 gap-4 p-4 pb-0 md:grid-cols-3">
        <div class="p-5 bg-white border border-gray-100 shadow-sm rounded-xl">
            <p class="text-xs tracking-wide text-gray-500 uppercase">Total Task</p>
            <h3 class="mt-2 text-3xl font-bold text-gray-800"><?= $totalTasks ?></h3>
        </div>
        <div class="p-5 bg-white border border-gray-100 shadow-sm rounded-xl">
            <p class="text-xs tracking-wide text-gray-500 uppercase">Total User</p>
            <h3 class="mt-2 text-3xl font-bold text-gray-800"><?= $totalUsers ?></h3>
        </div>
        <div class="p-5 bg-white border border-gray-100 shadow-sm rounded-xl">
            <p class="text-xs tracking-wide text-gray-500 uppercase">Admin Aktif</p>
            <h3 class="mt-2 text-3xl font-bold text-gray-800"><?= $totalAdmins ?></h3>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-6 p-4 pt-0 xl:grid-cols-12">
        <div class="p-4 bg-white border border-gray-100 shadow-md xl:col-span-8 rounded-xl">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">Task List</h3>
                <div class="flex flex-wrap gap-2 text-xs">
                    <span class="px-2.5 py-1 rounded-full bg-teal-100 text-teal-700">Open: <?= $statusCount['open'] ?></span>
                    <span class="px-2.5 py-1 rounded-full bg-blue-100 text-blue-700">In Progress: <?= $statusCount['in_progress'] ?></span>
                    <span class="px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700">Done: <?= $statusCount['done'] ?></span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm text-left">
                    <thead class="border-b bg-gray-50">
                    <tr>
                        <th class="px-3 py-2">ID</th>
                        <th class="px-3 py-2">User</th>
                        <th class="px-3 py-2">Title</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Deadline</th>
                        <th class="px-3 py-2">Attachment</th>
                    </tr>
                    </thead>
                    <tbody>
                        <?php if (count($tasks) === 0): ?>
                            <tr>
                                <td colspan="6" class="px-3 py-3 text-gray-500">Belum ada data tugas.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tasks as $task): ?>
                                <tr class="border-b last:border-0">
                                    <td class="px-3 py-2"><?= (int) $task['id'] ?></td>
                                    <td class="px-3 py-2"><?= htmlspecialchars($task['username'] ?? '-') ?></td>
                                    <td class="px-3 py-2"><?= htmlspecialchars($task['title'] ?: '-') ?></td>
                                    <td class="px-3 py-2">
                                        <?php
                                        $status = $task['status'] ?: '-';
                                        $statusClass = 'bg-gray-100 text-gray-700';
                                        if ($status === 'open') {
                                            $statusClass = 'bg-teal-100 text-teal-700';
                                        } elseif ($status === 'in_progress') {
                                            $statusClass = 'bg-blue-100 text-blue-700';
                                        } elseif ($status === 'done') {
                                            $statusClass = 'bg-emerald-100 text-emerald-700';
                                        }
                                        ?>
                                        <span class="px-2.5 py-1 text-xs rounded-full <?= $statusClass ?>"><?= htmlspecialchars($status) ?></span>
                                    </td>
                                    <td class="px-3 py-2"><?= !empty($task['deadline']) ? date('d M Y', strtotime($task['deadline'])) : '-' ?></td>
                                    <td class="px-3 py-2">
                                        <?php if (!empty($task['attachment'])): ?>
                                            <a class="text-blue-500 hover:underline" target="_blank" href="<?= assets('public/' . $task['attachment']) ?>"><?= htmlspecialchars(basename($task['attachment'])) ?></a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="p-4 bg-white border border-gray-100 shadow-md xl:col-span-4 rounded-xl">
            <h3 class="mb-4 text-lg font-semibold">User List</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm text-left">
                    <thead class="border-b bg-gray-50">
                    <tr>
                        <th class="px-3 py-2">ID</th>
                        <th class="px-3 py-2">Username</th>
                        <th class="px-3 py-2">Role</th>
                    </tr>
                    </thead>
                    <tbody>
                        <?php if (count($users) === 0): ?>
                            <tr>
                                <td colspan="3" class="px-3 py-3 text-gray-500">Belum ada data pengguna.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr class="border-b last:border-0">
                                    <td class="px-3 py-2"><?= (int) $user['id'] ?></td>
                                    <td class="px-3 py-2"><?= htmlspecialchars($user['username']) ?></td>
                                    <td class="px-3 py-2">
                                        <?php $role = $user['role_name'] ?: '-'; ?>
                                        <span class="px-2.5 py-1 text-xs rounded-full <?= $role === 'admin' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-700' ?>"><?= htmlspecialchars($role) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</main>

<?php include components('templates/footer'); ?>