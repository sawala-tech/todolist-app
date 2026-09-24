<?php
require_once __DIR__ . '/../../../assets/helpers/libs.php';
require_once __DIR__ . '/../../../assets/helpers/functions.php';
require_once __DIR__ . '/../../../assets/helpers/auth_helpers.php';
require_once __DIR__ . '/../../../assets/helpers/invitation_helpers.php';

// Redirect already-logged-in active users
if (isset($_SESSION['user']) && ($_SESSION['user']['status'] ?? '') === 'active') {
    header('Location: ' . url('dashboard'));
    exit;
}

$rawToken   = trim($_GET['token'] ?? '');
$step       = 'verify';      // verify | reset | done | error
$errorMsg   = '';
$successMsg = '';

// ------------------------------------------------------------------
// Validate token on every request
// ------------------------------------------------------------------
$validation = validateInvitationToken($rawToken);
if (!$validation['valid']) {
    $step     = 'error';
    $errorMsg = $validation['message'];
}

// ------------------------------------------------------------------
// POST: Step 1 — verify email + temp password
// ------------------------------------------------------------------
if ($step === 'verify' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_step'] ?? '') === 'verify') {
    verifyCsrf();

    $submittedEmail = trim($_POST['email'] ?? '');
    $tempPassword   = $_POST['temp_password'] ?? '';

    $user = $validation['user'];

    // Check email
    if (strtolower($submittedEmail) !== strtolower($user['email'])) {
        $errorMsg = 'Email atau password sementara tidak cocok.';
    } elseif (!verifyPassword($tempPassword, $user['stored_password'])) {
        $errorMsg = 'Email atau password sementara tidak cocok.';
    } else {
        // Credentials verified — move to password reset step
        $step = 'reset';
        // Store verified state in session temporarily (token still required in form)
        $_SESSION['inv_verified'] = [
            'user_id'    => $user['id'],
            'token'      => $rawToken,
            'expires'    => time() + 600,  // 10-minute window for form submit
        ];
    }
}

// ------------------------------------------------------------------
// POST: Step 2 — set new password + activate
// ------------------------------------------------------------------
if ($step !== 'error' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_step'] ?? '') === 'reset') {
    verifyCsrf();

    $verified = $_SESSION['inv_verified'] ?? null;

    if (
        !$verified
        || $verified['token'] !== $rawToken
        || $verified['expires'] < time()
    ) {
        $step     = 'error';
        $errorMsg = 'Sesi verifikasi habis. Silakan ulangi dari awal.';
    } else {
        $newPassword     = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Step 1 already verified email + temp password — call step-2 directly
        $result = activateInvitationStep2(
            (int) $verified['user_id'],
            (int) $validation['invitation']['id'],
            $validation['invitation']['project_id'] ? (int) $validation['invitation']['project_id'] : null,
            (int) $validation['invitation']['created_by'],
            $newPassword,
            $confirmPassword
        );

        if ($result['success']) {
            unset($_SESSION['inv_verified']);
            $step       = 'done';
            $successMsg = $result['message'];
        } else {
            $step     = 'reset';
            $errorMsg = $result['message'];
        }
    }
}

// If step is still 'verify' and it was a GET with a valid token, show step 1
if ($step === 'verify' && $_SERVER['REQUEST_METHOD'] === 'GET' && $validation['valid']) {
    // Show verify form
}

// If we are in reset state after step-1 POST success, show reset form
if ($step === 'reset' && !isset($_SESSION['inv_verified'])) {
    // Shouldn't happen normally, but fall back
    $step = 'verify';
}

include components('templates/header');
?>

<div class="flex items-center justify-center w-full min-h-screen py-8 bg-gray-50">
    <div class="w-full max-w-lg px-4">
        <div class="flex flex-col items-center p-8 space-y-4 bg-white shadow-lg rounded-2xl">
            <a href="<?= url('/') ?>">
                <img src="<?= assets('images/logo.png') ?>" alt="logo" class="h-10">
            </a>

            <?php if ($step === 'error'): ?>
                <!-- Error State -->
                <div class="flex flex-col items-center space-y-3 text-center">
                    <div class="flex items-center justify-center w-14 h-14 bg-red-100 rounded-full">
                        <svg class="w-7 h-7 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </div>
                    <h1 class="text-xl font-bold text-gray-800">Link Tidak Valid</h1>
                    <p class="text-sm text-gray-500"><?= htmlspecialchars($errorMsg) ?></p>
                    <a href="<?= url('auth/signin') ?>" class="mt-2 text-sm font-semibold text-emerald-600 hover:underline">Kembali ke Sign In</a>
                </div>

            <?php elseif ($step === 'done'): ?>
                <!-- Success State -->
                <div class="flex flex-col items-center space-y-3 text-center">
                    <div class="flex items-center justify-center w-14 h-14 bg-emerald-100 rounded-full">
                        <svg class="w-7 h-7 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <h1 class="text-xl font-bold text-gray-800">Akun Berhasil Diaktifkan!</h1>
                    <p class="text-sm text-gray-500"><?= htmlspecialchars($successMsg) ?></p>
                    <a href="<?= url('auth/signin') ?>" class="inline-flex items-center justify-center w-full h-11 mt-2 text-white rounded-lg bg-emerald-500 hover:bg-emerald-700 font-semibold text-sm">
                        Masuk Sekarang
                    </a>
                </div>

            <?php elseif ($step === 'reset'): ?>
                <!-- Step 2: Set New Password -->
                <div class="flex flex-col w-full space-y-3">
                    <div class="text-center">
                        <h1 class="text-xl font-bold text-gray-800">Buat Password Baru</h1>
                        <p class="mt-1 text-sm text-gray-500">Password minimal 8 karakter, mengandung huruf dan angka.</p>
                    </div>

                    <?php if ($errorMsg): ?>
                        <div class="px-4 py-3 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg">
                            <?= htmlspecialchars($errorMsg) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="<?= url('auth/invite') ?>?token=<?= urlencode($rawToken) ?>" class="flex flex-col space-y-4">
                        <?= csrfField() ?>
                        <input type="hidden" name="_step" value="reset">
                        <div class="flex flex-col space-y-1.5">
                            <label class="text-sm font-semibold">Password Baru <span class="text-red-500">*</span></label>
                            <input type="password" name="new_password" required autocomplete="new-password"
                                   class="h-11 px-4 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                   placeholder="Min. 8 karakter, huruf + angka">
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <label class="text-sm font-semibold">Konfirmasi Password <span class="text-red-500">*</span></label>
                            <input type="password" name="confirm_password" required autocomplete="new-password"
                                   class="h-11 px-4 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                   placeholder="Ulangi password baru">
                        </div>
                        <button type="submit" class="h-11 w-full bg-emerald-500 hover:bg-emerald-700 text-white font-semibold rounded-lg text-sm">
                            Aktifkan Akun
                        </button>
                    </form>
                </div>

            <?php else: ?>
                <!-- Step 1: Verify Email + Temp Password -->
                <div class="flex flex-col w-full space-y-4">
                    <div class="text-center">
                        <h1 class="text-xl font-bold text-gray-800">Aktivasi Akun</h1>
                        <p class="mt-1 text-sm text-gray-500">Masukkan email dan password sementara yang diberikan admin.</p>
                    </div>

                    <?php if ($errorMsg): ?>
                        <div class="px-4 py-3 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg">
                            <?= htmlspecialchars($errorMsg) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="<?= url('auth/invite') ?>?token=<?= urlencode($rawToken) ?>" class="flex flex-col space-y-4">
                        <?= csrfField() ?>
                        <input type="hidden" name="_step" value="verify">
                        <div class="flex flex-col space-y-1.5">
                            <label class="text-sm font-semibold">Email <span class="text-red-500">*</span></label>
                            <input type="email" name="email" required autocomplete="email"
                                   class="h-11 px-4 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                   placeholder="Masukkan email Anda">
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <label class="text-sm font-semibold">Password Sementara <span class="text-red-500">*</span></label>
                            <input type="password" name="temp_password" required autocomplete="current-password"
                                   class="h-11 px-4 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                   placeholder="Password dari admin">
                        </div>
                        <button type="submit" class="h-11 w-full bg-emerald-500 hover:bg-emerald-700 text-white font-semibold rounded-lg text-sm">
                            Lanjutkan
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include components('templates/footer'); ?>
