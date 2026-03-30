<?php

$currentPath = $_SERVER['REQUEST_URI'];
$hideNavbar = false;

if (strpos($currentPath, 'auth') !== false) {
    $hideNavbar = true;
}

$username = $_SESSION['user']['username'] ?? 'Guest';

?>

<div class="items-center justify-between bg-white md:px-[26px] md:py-[27px] p-4 fixed top-0 w-full z-40 <?= $hideNavbar ? 'hidden' : 'flex' ?>">
    <a href="<?= url('dashboard') ?>">
        <img src="<?= assets('images/logo.png') ?>" alt="logo" class="md:h-[46px] md:w-[208.96px] w-[121.21px] h-[27px]">
    </a>
    <div class="relative flex items-center space-x-4 md:space-x-5">
        <div>
            <button class="flex items-center justify-center px-3 py-2 space-x-1 text-white bg-green-500 rounded-lg md:px-5 md:py-3 hover:bg-green-700" id="addTodoTrigger">
                <img src="<?= assets('images/icons/plus.svg') ?>" alt="plus" class="w-6 h-6" />
                <span class="text-sm font-semibold md:text-base">Tambah Tugas</span>
            </button>
        </div>
        <div class="flex items-center space-x-1">
            <img src="<?= assets('images/icons/user.svg') ?>" alt="user" class="w-6 h-6" id="dropdownTrigger">
            <p class="hidden font-semibold truncate max-w-48 md:block">
                Halo, <?= $username ?>
            </p>
        </div>
        <div class="hidden w-px h-8 bg-gray-400 md:block"></div>
        <div class="hidden md:block">
            <a class="font-semibold text-red-500 cursor-pointer hover:text-red-700" href="<?= url('auth/signout') ?>">Keluar</a>
        </div>

        <!-- Drowpdow -->
        <div class="absolute right-0 hidden p-4 transition bg-white rounded-lg shadow-2xl top-10" id="dropdownMenu">
            <div class="flex flex-col space-y-5">
                <p class="font-semibold truncate max-w-48">
                    Halo, <?= $username ?>
                </p>
                <a class="font-semibold text-red-500 cursor-pointer hover:text-red-700" href="<?= url('auth/signout') ?>">Keluar</a>
            </div>
        </div>
    </div>
</div>