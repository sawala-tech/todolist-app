<?php
/**
 * pages/invite/index.php — TaskHub v4
 *
 * Handle invitation flow: /invite/:token
 * - Not logged in → redirect to signin/signup with token preserved
 * - Already logged in → confirm before joining the project
 */

require_once __DIR__ . '/../../assets/helpers/functions.php';
require_once __DIR__ . '/../../assets/helpers/libs.php';
require_once __DIR__ . '/../../assets/helpers/project_invitation_helpers.php';

// Get token from URL: /invite/ABC123TOKEN...
$token = $_GET['token'] ?? '';
if (empty($token)) {
    header('Location: ' . url('dashboard'));
    exit;
}

// Validate invitation before showing the confirmation page.
$invitation = getValidInvitation($token);
if (!$invitation) {
    $error = 'Link undangan tidak valid atau sudah kedaluwarsa.';
    include __DIR__ . '/../../components/templates/header/index.php';
    ?>
    <main class="min-h-screen bg-gray-50 flex items-center justify-center py-12 px-4">
        <div class="max-w-md w-full space-y-8">
            <div class="text-center">
                <h2 class="text-3xl font-bold text-gray-900">Link Tidak Valid</h2>
                <p class="mt-2 text-sm text-gray-600"><?= htmlspecialchars($error) ?></p>
                <div class="mt-6">
                    <a href="<?= url('dashboard') ?>" class="text-indigo-600 hover:text-indigo-500">
                        ← Kembali ke Dashboard
                    </a>
                </div>
            </div>
        </div>
    </main>
    <?php
    include __DIR__ . '/../../components/templates/footer/index.php';
    exit;
}

// If not logged in, redirect to signin with invite_token.
if (!isset($_SESSION['user'])) {
    $signinUrl = url('auth/signin') . '?invite=' . urlencode($token);
    header('Location: ' . $signinUrl);
    exit;
}

$userId = (int)$_SESSION['user']['id'];
$view = 'confirm';
$actionError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $decision = $_POST['decision'] ?? '';

    if ($decision === 'accept') {
        if (useInvitation($token, $userId)) {
            $view = 'accepted';
        } else {
            // The invitation may have reached its usage limit after the page loaded.
            $invitation = getValidInvitation($token);
            $view = $invitation ? 'error' : 'invalid';
            $actionError = 'Undangan sudah tidak tersedia atau gagal diproses.';
        }
    } elseif ($decision === 'reject') {
        // Rejecting must not consume the shared invitation link for other users.
        $view = 'rejected';
    } else {
        $actionError = 'Pilihan konfirmasi tidak valid.';
    }
}

include __DIR__ . '/../../components/templates/header/index.php';
?>

<main class="min-h-screen bg-gray-50 flex items-center justify-center py-12 px-4">
    <div class="max-w-md w-full">
        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6 sm:p-8">
            <?php if ($view === 'accepted'): ?>
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100">
                        <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <h2 class="mt-6 text-2xl font-bold text-gray-900">Undangan Diterima</h2>
                    <p class="mt-2 text-sm text-gray-600">
                        Anda telah bergabung dengan project <strong><?= htmlspecialchars($invitation['project_name']) ?></strong>.
                    </p>
                    <div class="mt-6 space-y-3">
                        <a href="<?= url('projects/' . $invitation['project_id']) ?>"
                           class="w-full flex justify-center py-2.5 px-4 rounded-lg text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700">
                            Buka Project
                        </a>
                        <a href="<?= url('dashboard') ?>"
                           class="w-full flex justify-center py-2.5 px-4 border border-gray-300 rounded-lg text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50">
                            Ke Dashboard
                        </a>
                    </div>
                </div>
            <?php elseif ($view === 'rejected'): ?>
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-gray-100">
                        <svg class="h-6 w-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </div>
                    <h2 class="mt-6 text-2xl font-bold text-gray-900">Undangan Ditolak</h2>
                    <p class="mt-2 text-sm text-gray-600">
                        Anda tidak bergabung ke project <strong><?= htmlspecialchars($invitation['project_name']) ?></strong>.
                    </p>
                    <a href="<?= url('dashboard') ?>"
                       class="mt-6 inline-flex justify-center py-2.5 px-4 rounded-lg text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700">
                        Ke Dashboard
                    </a>
                </div>
            <?php elseif ($view === 'error' || $view === 'invalid'): ?>
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                        <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </div>
                    <h2 class="mt-6 text-2xl font-bold text-gray-900">Undangan Tidak Dapat Diproses</h2>
                    <p class="mt-2 text-sm text-gray-600"><?= htmlspecialchars($actionError) ?></p>
                    <a href="<?= url('dashboard') ?>" class="mt-6 inline-flex text-indigo-600 hover:text-indigo-500 text-sm font-medium">
                        ← Kembali ke Dashboard
                    </a>
                </div>
            <?php else: ?>
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-indigo-100">
                        <i class="fa-solid fa-user-plus text-indigo-600"></i>
                    </div>
                    <h2 class="mt-6 text-2xl font-bold text-gray-900">Undangan Project</h2>
                    <p class="mt-2 text-sm text-gray-600">
                        Anda diundang untuk bergabung ke project
                        <strong class="text-gray-900"><?= htmlspecialchars($invitation['project_name']) ?></strong>.
                    </p>
                    <p class="mt-2 text-xs text-gray-400">Pilih terima untuk bergabung atau tolak jika tidak ingin ikut.</p>

                    <?php if ($actionError): ?>
                        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-left text-sm text-red-700">
                            <?= htmlspecialchars($actionError) ?>
                        </div>
                    <?php endif; ?>

                    <div class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <form method="POST">
                            <?= csrfField() ?>
                            <input type="hidden" name="decision" value="reject">
                            <button type="submit" class="w-full py-2.5 px-4 border border-gray-300 rounded-lg text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50">
                                Tolak Undangan
                            </button>
                        </form>
                        <form method="POST">
                            <?= csrfField() ?>
                            <input type="hidden" name="decision" value="accept">
                            <button type="submit" class="w-full py-2.5 px-4 rounded-lg text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700">
                                Terima Undangan
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include __DIR__ . '/../../components/templates/footer/index.php'; ?>
