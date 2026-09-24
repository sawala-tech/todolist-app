<?php
require_once __DIR__ . '/../../../assets/helpers/libs.php';
require_once __DIR__ . '/../../../assets/helpers/functions.php';
require_once __DIR__ . '/../../../assets/helpers/auth_helpers.php';

if (isset($_SESSION['user']) && ($_SESSION['user']['status'] ?? '') === 'active') {
    header('Location: ' . url('dashboard')); exit;
}

$errors      = [];
$inviteToken = trim($_GET['invite'] ?? $_POST['invite_token'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $username = trim($_POST['username'] ?? '');
    $email    = trim(strtolower($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($username === '' || strlen($username) < 3)
        $errors[] = 'Username minimal 3 karakter.';
    elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username))
        $errors[] = 'Username hanya huruf, angka, dan underscore.';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Format email tidak valid.';

    if (!isStrongPassword($password))
        $errors[] = 'Password minimal 8 karakter, mengandung huruf dan angka.';
    elseif ($password !== $confirm)
        $errors[] = 'Konfirmasi password tidak cocok.';

    if (empty($errors)) {
        $s = $conn->prepare('SELECT id FROM users WHERE username=? LIMIT 1');
        $s->bind_param('s', $username); $s->execute();
        if ($s->get_result()->num_rows > 0) $errors[] = 'Username sudah digunakan.';
        $s->close();
    }
    if (empty($errors)) {
        $s = $conn->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
        $s->bind_param('s', $email); $s->execute();
        if ($s->get_result()->num_rows > 0) $errors[] = 'Email sudah digunakan.';
        $s->close();
    }

    if (empty($errors)) {
        $hash = hashPassword($password);
        $roleId = 2; $status = 'active';
        $stmt = $conn->prepare('INSERT INTO users (username,email,password,role_id,status,activated_at) VALUES (?,?,?,?,?,NOW())');
        $stmt->bind_param('sssss', $username, $email, $hash, $roleId, $status);
        if ($stmt->execute()) {
            $userId = (int)$conn->insert_id;
            $stmt->close();
            $_SESSION['user'] = [
                'id' => $userId, 'username' => $username, 'email' => $email,
                'role_id' => $roleId, 'role_name' => 'user', 'status' => 'active',
            ];
            // If came from invite, go back to invite link
            if ($inviteToken !== '') {
                header('Location: ' . url('invite/'.urlencode($inviteToken))); exit;
            }
            header('Location: ' . url('dashboard')); exit;
        }
        $errors[] = 'Gagal membuat akun, coba lagi.';
        $stmt->close();
    }
}

include components('templates/header');
?>
<div class="flex items-center justify-center min-h-screen bg-gray-50 py-10">
    <div class="w-full max-w-md mx-4">
        <div class="bg-white rounded-2xl shadow-lg p-8 space-y-6">
            <div class="text-center">
                <a href="<?= url('/') ?>"><img src="<?= assets('images/logo.png') ?>" alt="TaskHub" class="h-9 mx-auto mb-4"></a>
                <h1 class="text-2xl font-bold text-gray-900">Buat Akun</h1>
                <?php if ($inviteToken): ?>
                <p class="text-sm text-blue-600 mt-1 font-medium">Kamu diundang ke sebuah project!</p>
                <?php else: ?>
                <p class="text-sm text-gray-500 mt-1">Gratis, tidak perlu kartu kredit.</p>
                <?php endif; ?>
            </div>

            <?php if (!empty($errors)): ?>
            <div class="px-4 py-3 bg-red-50 border border-red-200 rounded-lg space-y-1">
                <?php foreach ($errors as $e): ?>
                    <p class="text-sm text-red-700"><i class="fa-solid fa-circle-exclamation mr-1.5"></i><?= htmlspecialchars($e) ?></p>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="<?= url('auth/signup') ?>" class="space-y-4">
                <?= csrfField() ?>
                <input type="hidden" name="invite_token" value="<?= htmlspecialchars($inviteToken) ?>">

                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-semibold text-gray-700">Username <span class="text-red-500">*</span></label>
                    <input type="text" name="username" required autocomplete="username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           placeholder="contoh: johndoe"
                           class="h-11 px-4 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-400">Huruf, angka, underscore. Min. 3 karakter.</p>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-semibold text-gray-700">Email <span class="text-red-500">*</span></label>
                    <input type="email" name="email" required autocomplete="email"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           placeholder="kamu@contoh.com"
                           class="h-11 px-4 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-semibold text-gray-700">Password <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input type="password" name="password" id="pwdField" required autocomplete="new-password"
                               placeholder="Min. 8 karakter, huruf + angka"
                               class="w-full h-11 px-4 pr-11 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <button type="button" onclick="togglePass('pwdField',this)"
                                class="absolute right-3 top-1/2 translate-y-[-50%] text-gray-400 hover:text-gray-600 transform">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-semibold text-gray-700">Konfirmasi Password <span class="text-red-500">*</span></label>
                    <input type="password" name="confirm_password" required autocomplete="new-password"
                           placeholder="Ulangi password"
                           class="h-11 px-4 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <button type="submit" class="w-full h-11 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg text-sm transition">
                    Buat Akun
                </button>
            </form>

            <p class="text-center text-sm text-gray-500">
                Sudah punya akun?
                <a href="<?= url('auth/signin').($inviteToken ? '?invite='.urlencode($inviteToken) : '') ?>"
                   class="font-semibold text-blue-600 hover:underline">Masuk</a>
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
