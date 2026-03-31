<?php
require_once __DIR__ . '/../../../assets/helpers/libs.php';
require_once __DIR__ . '/../../../assets/helpers/functions.php';

include components('templates/header');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $result = signin($username, $password);
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $_SESSION['user'] = $user;

        if (($user['role_name'] ?? '') === 'admin' || ((int) ($user['role_id'] ?? 0) === 1)) {
            header('Location: ' . url('admin'));
        } else {
            header('Location: ' . url('dashboard'));
        }
        exit;
    } else {
        echo "
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Oops...',
                text: 'Username atau password salah!',
            })
        </script>
        ";
    }
}
?>

<div class="flex items-center justify-center w-full h-screen">
    <div class="flex flex-col items-center justify-center w-full bg-white rounded-xl p-9 space-y-11 md:max-w-[500px] max-sm:mx-4">
        <img src="<?= assets("images/logo.png") ?>" alt="logo" class="md:w-[261.29px] md:h-[58px] w-[180.32px] h-10" />
        <form class="flex flex-col items-center justify-center w-full space-y-6" action="" method="POST">
            <h1 class="text-2xl font-semibold md:text-3xl">Masuk</h1>
            <div class="flex flex-col space-y-1.5 w-full">
                <label for="username" class="text-sm font-semibold">Username <span class="text-red-500">*</span></label>
                <input type="text" id="username" name="username" class="w-full h-12 px-4 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-emerald-500" required />
            </div>
            <div class="flex flex-col space-y-1.5 w-full">
                <label for="password" class="text-sm font-semibold">Password <span class="text-red-500">*</span></label>
                <div class="relative">
                    <input type="password" id="password" name="password" class="w-full h-12 px-4 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-emerald-500" required />
                    <button type="button" class="absolute z-40 transform -translate-y-1/2 cursor-pointer top-9 right-4" id="cta-show-hide-password">
                        <img src="<?= assets("images/icons/open-eye.svg") ?>" alt="open-eye-image" class="hidden w-6 h-6" id="open-eye">
                        <img src="<?= assets("images/icons/close-eye.svg") ?>" alt="close-eye-image" class="block w-6 h-6" id="close-eye">
                    </button>
                </div>
            </div>
            <button class="flex items-center justify-center w-full h-12 px-2 overflow-hidden rounded-md bg-emerald-500 hover:bg-emerald-700" type="submit">
                <p class="text-base font-semibold leading-snug text-center text-white">Masuk</d>
            </button>
            <div class="flex items-center justify-center w-full space-x-2">
                <p class="text-sm">Belum punya akun?</p>
                <a href="<?= url('auth/signup') ?>" class="text-sm font-semibold text-emerald-500 hover:underline">Daftar</a>
            </div>
        </form>
    </div>
</div>

<?php include components('templates/footer'); ?>