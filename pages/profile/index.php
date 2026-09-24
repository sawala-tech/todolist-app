<?php
require_once __DIR__ . '/../../assets/helpers/libs.php';
require_once __DIR__ . '/../../assets/helpers/functions.php';
require_once __DIR__ . '/../../assets/helpers/auth_helpers.php';
require_once __DIR__ . '/../../assets/helpers/ui_helpers.php';
require_once __DIR__ . '/../../components/layouts/sidebar_layout.php';

requireActiveUser();

$userId   = (int)$_SESSION['user']['id'];

$errors   = [];
$success  = [];

// Load current user data
$stmt = $conn->prepare('SELECT u.*, r.name AS role_name FROM users u LEFT JOIN roles r ON r.id = u.role_id WHERE u.id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$res  = $stmt->get_result();
$user = $res->fetch_assoc();
$res->free();
$stmt->close();

if (!$user) {
    session_destroy();
    header('Location: ' . url('auth/signin'));
    exit;
}

// ── POST ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // --- Update profile info ---
    if ($action === 'update_profile') {
        $newUsername = trim($_POST['username'] ?? '');
        $newEmail    = trim(strtolower($_POST['email'] ?? ''));

        if ($newUsername === '') {
            $errors['profile'] = 'Username tidak boleh kosong.';
        } elseif (strlen($newUsername) < 3) {
            $errors['profile'] = 'Username minimal 3 karakter.';
        } else {
            // Check username uniqueness (exclude self)
            $stmtChk = $conn->prepare('SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1');
            $stmtChk->bind_param('si', $newUsername, $userId);
            $stmtChk->execute();
            $dupUser = $stmtChk->get_result()->num_rows > 0;
            $stmtChk->close();

            if ($dupUser) {
                $errors['profile'] = 'Username sudah digunakan.';
            } elseif ($newEmail !== '' && !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $errors['profile'] = 'Format email tidak valid.';
            } else {
                // Check email uniqueness (exclude self)
                $emailConflict = false;
                if ($newEmail !== '') {
                    $stmtE = $conn->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
                    $stmtE->bind_param('si', $newEmail, $userId);
                    $stmtE->execute();
                    $emailConflict = $stmtE->get_result()->num_rows > 0;
                    $stmtE->close();
                }

                if ($emailConflict) {
                    $errors['profile'] = 'Email sudah digunakan akun lain.';
                } else {
                    $emailVal = $newEmail !== '' ? $newEmail : null;
                    $stmtUpd  = $conn->prepare('UPDATE users SET username = ?, email = ? WHERE id = ?');
                    $stmtUpd->bind_param('ssi', $newUsername, $emailVal, $userId);
                    $stmtUpd->execute();
                    $stmtUpd->close();

                    // Refresh session
                    $_SESSION['user']['username'] = $newUsername;
                    $_SESSION['user']['email']    = $emailVal;
                    $success['profile'] = 'Profil berhasil diperbarui.';

                    // Reload user
                    $stmt2 = $conn->prepare('SELECT u.*, r.name AS role_name FROM users u LEFT JOIN roles r ON r.id = u.role_id WHERE u.id = ?');
                    $stmt2->bind_param('i', $userId);
                    $stmt2->execute();
                    $res2 = $stmt2->get_result();
                    $user = $res2->fetch_assoc();
                    $res2->free();
                    $stmt2->close();
                }
            }
        }
    }

    // --- Change password ---
    if ($action === 'change_password') {
        $currentPass  = $_POST['current_password'] ?? '';
        $newPass      = $_POST['new_password']      ?? '';
        $confirmPass  = $_POST['confirm_password']  ?? '';

        if ($currentPass === '' || $newPass === '' || $confirmPass === '') {
            $errors['password'] = 'Semua field password wajib diisi.';
        } elseif (!verifyPassword($currentPass, $user['password'])) {
            $errors['password'] = 'Password saat ini tidak sesuai.';
        } elseif ($newPass !== $confirmPass) {
            $errors['password'] = 'Password baru dan konfirmasi tidak cocok.';
        } elseif (!isStrongPassword($newPass)) {
            $errors['password'] = 'Password baru minimal 8 karakter dan mengandung huruf serta angka.';
        } elseif ($newPass === $currentPass) {
            $errors['password'] = 'Password baru tidak boleh sama dengan password saat ini.';
        } else {
            $newHash = hashPassword($newPass);
            $stmtPwd = $conn->prepare('UPDATE users SET password = ?, must_reset_password = 0 WHERE id = ?');
            $stmtPwd->bind_param('si', $newHash, $userId);
            $stmtPwd->execute();
            $stmtPwd->close();
            $success['password'] = 'Password berhasil diperbarui.';
        }
    }
}

$backUrl     = url('dashboard');
$pageTitle   = 'Profil Saya';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => $backUrl],
    ['label' => 'Profil'],
];
$headerActions = '';
$activeTab = (!empty($errors['password']) || !empty($success['password'])) ? 'password' : 'profile';

// Start content buffering
ob_start();
?>

<main>
    <?php include __DIR__ . '/../../components/partials/page_header.php'; ?>

    <div class="p-4 md:p-6">
        <div class="max-w-2xl">

            <div class="mb-5 flex border-b border-gray-200 overflow-hidden" id="profileTabs">
                <button type="button"
                        onclick="switchTab('profile')"
                        id="tab-profile"
                        class="tab-btn px-5 py-3 text-sm font-medium border-b-2 transition-colors
                               <?= $activeTab === 'profile'
                                   ? 'border-blue-600 text-blue-600'
                                   : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                    Profil
                </button>
                <button type="button"
                        onclick="switchTab('password')"
                        id="tab-password"
                        class="tab-btn px-5 py-3 text-sm font-medium border-b-2 transition-colors
                               <?= $activeTab === 'password'
                                   ? 'border-blue-600 text-blue-600'
                                   : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                    Ganti Password
                </button>
            </div>

            <!-- Profile information -->
            <section id="panel-profile" class="bg-white border border-gray-200 shadow-sm rounded-xl p-5 <?= $activeTab !== 'profile' ? 'hidden' : '' ?>">
                <div class="flex items-center justify-between gap-4 pb-4 border-b border-gray-100">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-semibold text-sm select-none">
                            <?= strtoupper(mb_substr($user['username'], 0, 1)) ?>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($user['username']) ?></p>
                            <p class="text-xs text-gray-400"><?= htmlspecialchars($user['email'] ?? '—') ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <?= userStatusBadge($user['status'] ?? 'active') ?>
                        <?= roleBadge($user['role_name'] ?? 'user') ?>
                    </div>
                </div>

                <?php if (!empty($user['activated_at'])): ?>
                <p class="mt-4 text-xs text-gray-400">
                    <i class="fa-regular fa-calendar mr-1"></i>
                    Bergabung <?= date('d M Y', strtotime($user['activated_at'])) ?>
                </p>
                <?php endif; ?>

                <?php if (!empty($errors['profile'])): ?>
                <div class="mt-4 px-3 py-2 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg">
                    <i class="fa-solid fa-circle-exclamation mr-1.5"></i><?= htmlspecialchars($errors['profile']) ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($success['profile'])): ?>
                <div class="mt-4 px-3 py-2 text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg">
                    <i class="fa-solid fa-circle-check mr-1.5"></i><?= htmlspecialchars($success['profile']) ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="<?= url('profile') ?>" class="pt-5 space-y-5">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_profile">

                    <div class="flex flex-col gap-1.5">
                        <label for="username" class="text-sm font-semibold text-gray-700">
                            Username <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="username" name="username" required
                               value="<?= htmlspecialchars($user['username']) ?>"
                               class="w-full h-10 px-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="email" class="text-sm font-semibold text-gray-700">Email</label>
                        <input type="email" id="email" name="email"
                               value="<?= htmlspecialchars($user['email'] ?? '') ?>"
                               placeholder="Kosongkan jika tidak ingin mengubah"
                               class="w-full h-10 px-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="text-xs text-gray-400">Email digunakan untuk login dan menerima invitation.</p>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="submit"
                                class="h-10 px-5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg text-sm transition-colors">
                            Simpan
                        </button>
                    </div>
                </form>
            </section>

            <!-- Change password -->
            <section id="panel-password" class="bg-white border border-gray-200 shadow-sm rounded-xl p-5 <?= $activeTab !== 'password' ? 'hidden' : '' ?>">
                <div class="mb-5">
                    <h2 class="text-base font-semibold text-gray-900">Ganti Password</h2>
                    <p class="mt-1 text-sm text-gray-500">Perbarui password akun Anda secara berkala.</p>
                </div>

                <?php if (!empty($errors['password'])): ?>
                <div class="mb-4 px-3 py-2 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg">
                    <i class="fa-solid fa-circle-exclamation mr-1.5"></i><?= htmlspecialchars($errors['password']) ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($success['password'])): ?>
                <div class="mb-4 px-3 py-2 text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg">
                    <i class="fa-solid fa-circle-check mr-1.5"></i><?= htmlspecialchars($success['password']) ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="<?= url('profile') ?>" class="space-y-5">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="change_password">

                    <div class="flex flex-col gap-1.5">
                        <label for="currentPass" class="text-sm font-semibold text-gray-700">
                            Password Saat Ini <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="password" name="current_password" id="currentPass" required
                                   autocomplete="current-password"
                                   class="w-full h-10 px-3 pr-10 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <button type="button" onclick="togglePass('currentPass', this)"
                                    class="absolute right-3 top-1/2 translate-y-[-50%] text-gray-400 hover:text-gray-600">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="newPass" class="text-sm font-semibold text-gray-700">
                            Password Baru <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="password" name="new_password" id="newPass" required
                                   autocomplete="new-password"
                                   class="w-full h-10 px-3 pr-10 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <button type="button" onclick="togglePass('newPass', this)"
                                    class="absolute right-3 top-1/2 translate-y-[-50%] text-gray-400 hover:text-gray-600">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                        <p class="text-xs text-gray-400">Minimal 8 karakter, mengandung huruf dan angka.</p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="confirmPass" class="text-sm font-semibold text-gray-700">
                            Konfirmasi Password Baru <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="password" name="confirm_password" id="confirmPass" required
                                   autocomplete="new-password"
                                   class="w-full h-10 px-3 pr-10 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <button type="button" onclick="togglePass('confirmPass', this)"
                                    class="absolute right-3 top-1/2 translate-y-[-50%] text-gray-400 hover:text-gray-600">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="submit"
                                class="h-10 px-5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg text-sm transition-colors">
                            Ganti Password
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</main>

<script>
function switchTab(tab) {
    ['profile', 'password'].forEach(function (name) {
        var button = document.getElementById('tab-' + name);
        var panel  = document.getElementById('panel-' + name);
        var active = name === tab;

        button.classList.toggle('border-blue-600', active);
        button.classList.toggle('text-blue-600', active);
        button.classList.toggle('border-transparent', !active);
        button.classList.toggle('text-gray-500', !active);
        button.classList.toggle('hover:text-gray-700', !active);
        panel.classList.toggle('hidden', !active);
    });
}

function togglePass(inputId, btn) {
    var input = document.getElementById(inputId);
    var icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}
</script>

<?php
$content = ob_get_clean();
renderSidebarLayout('dashboard', $content, 'Profile - TaskHub');
?>
