<?php
require_once __DIR__ . '/../../assets/helpers/libs.php';

$currentPath  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$hideNavbar   = strpos($currentPath, '/auth/') !== false || $currentPath === '/auth';
$isActiveUser = isset($_SESSION['user']) && ($_SESSION['user']['status'] ?? 'active') === 'active';
$username     = $_SESSION['user']['username'] ?? '';

// ── Nav links ──
$navLinks = [];
if ($isActiveUser) {
    $navLinks = [
        ['label' => 'Dashboard', 'url' => url('dashboard'), 'pattern' => '#^/dashboard#'],
    ];
}

if (!function_exists('isNavActive')) {
    function isNavActive(string $pattern): bool {
        global $currentPath;
        return (bool) preg_match($pattern, $currentPath);
    }
}
?>

<header class="fixed top-0 w-full z-40 bg-white border-b border-gray-200 <?= $hideNavbar ? 'hidden' : '' ?>">
    <div class="flex items-center justify-between h-14 px-4 md:px-6">

        <!-- Logo + horizontal nav -->
        <div class="flex items-center gap-6">
            <a href="<?= url('dashboard') ?>">
                <img src="<?= assets('images/logo.png') ?>" alt="TaskHub" class="h-7 w-auto">
            </a>
            <?php if (!empty($navLinks)): ?>
            <nav class="hidden md:flex items-center gap-1 text-sm">
                <?php foreach ($navLinks as $link): ?>
                    <a href="<?= $link['url'] ?>"
                       class="px-3 py-1.5 rounded-md transition <?= isNavActive($link['pattern']) ? 'bg-gray-100 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' ?>">
                        <?= htmlspecialchars($link['label']) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <?php endif; ?>
        </div>

        <!-- Right actions -->
        <div class="flex items-center gap-3">
            <?php if ($isActiveUser): ?>
            <!-- User dropdown -->
            <div class="relative">
                <button id="dropdownTrigger"
                    class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-gray-100 transition text-sm">
                    <div class="w-7 h-7 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold text-xs uppercase shrink-0">
                        <?= htmlspecialchars(mb_substr($username, 0, 1)) ?>
                    </div>
                    <span class="hidden md:block font-medium text-gray-700 max-w-[120px] truncate capitalize">
                        <?= htmlspecialchars($username) ?>
                    </span>
                    <svg class="w-4 h-4 text-gray-400 hidden md:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <!-- Dropdown menu -->
                <div id="dropdownMenu"
                     class="absolute right-0 top-full mt-1 w-44 bg-white border border-gray-200 rounded-xl shadow-lg hidden z-50 overflow-hidden">
                    <div class="px-3 py-2 border-b border-gray-100">
                        <p class="text-sm font-semibold text-gray-800 truncate capitalize"><?= htmlspecialchars($username) ?></p>
                        <p class="text-xs text-gray-400">Member</p>
                    </div>
                    <div>
                        <a href="<?= url('profile') ?>"
                           class="flex items-center gap-2 px-3 py-2.5 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-regular fa-user w-4 text-gray-400 text-xs"></i> Profil Saya
                        </a>
                        <a href="<?= url('auth/signout') ?>"
                           class="flex items-center gap-2 px-3 py-2.5 text-sm text-red-600 hover:bg-red-50 border-t border-gray-100">
                            <i class="fa-solid fa-arrow-right-from-bracket w-4 text-xs"></i> Keluar
                        </a>
                    </div>
                </div>
            </div>

            <!-- Mobile hamburger -->
            <button id="mobileMenuTrigger" class="md:hidden p-1.5 rounded-lg hover:bg-gray-100">
                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Mobile nav -->
    <?php if ($isActiveUser): ?>
    <div id="mobileMenu" class="hidden md:hidden border-t border-gray-100 bg-white px-4 py-2 space-y-0.5 text-sm">
        <?php foreach ($navLinks as $link): ?>
            <a href="<?= $link['url'] ?>"
               class="block px-3 py-2 rounded-lg <?= isNavActive($link['pattern']) ? 'bg-gray-100 text-gray-900 font-semibold' : 'text-gray-700 hover:bg-gray-100' ?>">
                <?= htmlspecialchars($link['label']) ?>
            </a>
        <?php endforeach; ?>
        <div class="border-t border-gray-100 pt-1 mt-1">
            <a href="<?= url('auth/signout') ?>" class="block px-3 py-2 rounded-lg text-red-600 hover:bg-red-50">Keluar</a>
        </div>
    </div>
    <?php endif; ?>
</header>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Dropdown
    const trigger = document.getElementById('dropdownTrigger');
    const menu    = document.getElementById('dropdownMenu');
    if (trigger && menu) {
        trigger.addEventListener('click', function (e) {
            e.stopPropagation();
            menu.classList.toggle('hidden');
        });
        document.addEventListener('click', function () {
            menu.classList.add('hidden');
        });
    }
    // Mobile menu
    const mTrigger = document.getElementById('mobileMenuTrigger');
    const mMenu    = document.getElementById('mobileMenu');
    if (mTrigger && mMenu) {
        mTrigger.addEventListener('click', function () {
            mMenu.classList.toggle('hidden');
        });
    }
});
</script>
