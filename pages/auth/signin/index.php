<?php
require_once __DIR__ . '/../../../assets/helpers/libs.php';
require_once __DIR__ . '/../../../assets/helpers/functions.php';
require_once __DIR__ . '/../../../assets/helpers/auth_helpers.php';

if (isset($_SESSION['user']) && ($_SESSION['user']['status'] ?? '') === 'active') {
    header('Location: ' . url('dashboard')); exit;
}

$error       = '';
$inviteToken = trim($_GET['invite'] ?? $_POST['invite_token'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['username'] ?? '');
    $password   = $_POST['password'] ?? '';

    if ($identifier === '' || $password === '') {
        $error = 'Username/email dan password tidak boleh kosong.';
    } else {
        $stmt = $conn->prepare(
            "SELECT u.*, r.name AS role_name FROM users u
             LEFT JOIN roles r ON r.id = u.role_id
             WHERE (u.username = ? OR u.email = ?) LIMIT 1"
        );
        $stmt->bind_param('ss', $identifier, $identifier);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            $error = 'Username/email atau password salah.';
        } elseif (($user['status'] ?? '') === 'suspended') {
            $error = 'Akun kamu ditangguhkan.';
        } elseif (!verifyPassword($password, $user['password'])) {
            $error = 'Username/email atau password salah.';
        } else {
            // Rehash legacy SHA-256
            if (strlen($user['password']) === 64 && ctype_xdigit($user['password'])) {
                rehashLegacyPassword((int)$user['id'], $password);
            }

            $_SESSION['user'] = [
                'id'        => $user['id'],
                'username'  => $user['username'],
                'email'     => $user['email'] ?? null,
                'role_id'   => $user['role_id'],
                'role_name' => $user['role_name'] ?? 'user',
                'status'    => $user['status'] ?? 'active',
            ];

            // If came from invite link
            if ($inviteToken !== '') {
                header('Location: ' . url('invite/'.urlencode($inviteToken))); exit;
            }

            header('Location: ' . url('dashboard')); exit;
        }
    }
}

include components('templates/header');
?>
<div class="flex items-center justify-center min-h-screen bg-gray-50">
    <div class="w-full max-w-md mx-4">
        <div class="bg-white rounded-2xl shadow-lg p-8 space-y-6">
            <div class="text-center">
                <a href="<?= url('/') ?>"><img src="<?= assets('images/logo.png') ?>" alt="TaskHub" class="h-9 mx-auto mb-4"></a>
                <h1 class="text-2xl font-bold text-gray-900">Masuk</h1>
                <?php if ($inviteToken): ?>
                <p class="text-sm text-blue-600 mt-1 font-medium">Masuk untuk bergabung ke project.</p>
                <?php endif; ?>
            </div>

            <?php if ($error): ?>
            <div class="px-4 py-3 bg-red-50 border border-red-200 rounded-lg">
                <p class="text-sm text-red-700"><i class="fa-solid fa-circle-exclamation mr-1.5"></i><?= htmlspecialchars($error) ?></p>
            </div>
            <?php endif; ?>

            <form method="POST" action="<?= url('auth/signin') ?>" class="space-y-4">
                <?= csrfField() ?>
                <input type="hidden" name="invite_token" value="<?= htmlspecialchars($inviteToken) ?>">

                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-semibold text-gray-700">Username atau Email</label>
                    <input type="text" name="username" required autocomplete="username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           class="h-11 px-4 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-semibold text-gray-700">Password</label>
                    <div class="relative">
                        <input type="password" name="password" id="pwdField" required autocomplete="current-password"
                               class="w-full h-11 px-4 pr-11 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <button type="button" onclick="togglePass('pwdField',this)"
                                class="absolute right-3 top-1/2 translate-y-[-50%] text-gray-400 hover:text-gray-600 transform">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="w-full h-11 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg text-sm transition">
                    Masuk
                </button>
            </form>

            <p class="text-center text-sm text-gray-500">
                Belum punya akun?
                <a href="<?= url('auth/signup').($inviteToken ? '?invite='.urlencode($inviteToken) : '') ?>"
                   class="font-semibold text-blue-600 hover:underline">Daftar gratis</a>
            </p>
        </div>
    </div>
</div>
<script>
function togglePass(id, btn) {
    const f = document.getElementById(id), i = btn.querySelector('i');
    f.type = f.type === 'password' ? 'text' : 'password';
    i.classList.toggle('fa-eye'); i.classList.toggle('fa-eye-slash');
}
</script>
<?php include components('templates/footer'); ?>
