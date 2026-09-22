<?php
function renderSidebarLayout($currentPage = 'dashboard', $contentHtml = '', $pageTitle = 'TaskHub') {
    $userId = $_SESSION['user']['id'] ?? 0;
    $username = $_SESSION['user']['username'] ?? 'User';
    $userEmail = $_SESSION['user']['email'] ?? '';
    
    // Get first letter for avatar
    $avatarLetter = strtoupper(substr($username, 0, 1));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --orange: #eb570c;
            --blue: #2563eb;
            --green: #10b981;
        }
        .brand-orange { color: #eb570c; }
        .bg-brand-orange { background-color: #eb570c; }
        .brand-blue { color: #2563eb; }
        .bg-brand-blue { background-color: #2563eb; }
        .brand-green { color: #10b981; }
        .bg-brand-green { background-color: #10b981; }
        .nav-item-active {
            background-color: #eff6ff;
            color: #2563eb;
        }
        .nav-item:hover {
            background-color: #f9fafb;
        }
    </style>
</head>
<body class="bg-gray-50 font-sans">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div class="w-64 bg-white shadow-sm border-r border-gray-200 flex flex-col">
            <!-- Logo -->
            <div class="flex items-center p-6 border-b border-gray-200">
                <div class="flex items-center">
                    <img src="/assets/images/logo.png" alt="TaskHub" class="h-7 w-auto">
                </div>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 px-4 py-6 space-y-1">
                <a href="/dashboard" class="nav-item <?= $currentPage === 'dashboard' ? 'nav-item-active' : '' ?> flex items-center px-3 py-2 rounded text-sm font-medium">
                    <i class="fas fa-home w-5 text-center mr-3"></i>
                    Dashboard
                </a>
                
                <a href="/backlog" class="nav-item <?= $currentPage === 'backlog' ? 'nav-item-active' : '' ?> flex items-center px-3 py-2 rounded text-sm font-medium">
                    <i class="fas fa-list w-5 text-center mr-3"></i>
                    Backlog
                </a>
                
                <a href="/activity" class="nav-item <?= $currentPage === 'activity' ? 'nav-item-active' : '' ?> flex items-center px-3 py-2 rounded text-sm font-medium">
                    <i class="fas fa-clock w-5 text-center mr-3"></i>
                    Aktivitas
                </a>
            </nav>

            <!-- User Section -->
            <div class="border-t border-gray-200 p-4">
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-gray-600 rounded-full flex items-center justify-center">
                        <span class="text-white text-sm font-medium"><?= $avatarLetter ?></span>
                    </div>
                    <div class="ml-3 flex-1">
                        <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($username) ?></p>
                        <p class="text-xs text-gray-500"><?= htmlspecialchars($userEmail) ?></p>
                    </div>
                    <div class="relative">
                        <button id="userMenuBtn" class="text-gray-400 hover:text-gray-600 p-1 rounded">
                            <i class="fas fa-cog"></i>
                        </button>
                        <!-- Popover -->
                        <div id="userPopover" class="hidden absolute bottom-8 right-0 z-50 bg-white rounded-md shadow-lg border border-gray-200 py-1 min-w-[110px]">
                            <a href="/profile" class="flex items-center w-full px-3 py-2 text-xs text-gray-700 hover:bg-gray-50">
                                <i class="fas fa-user w-4 mr-2"></i>
                                Profile
                            </a>
                            <a href="/auth/signout" class="flex items-center w-full px-3 py-2 text-xs text-red-600 hover:bg-red-50">
                                <i class="fas fa-sign-out-alt w-4 mr-2"></i>
                                Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <main class="flex-1 overflow-y-auto">
                <?= $contentHtml ?>
            </main>
        </div>
    </div>

    <script>
        // User menu popover toggle
        $('#userMenuBtn').click(function(e) {
            e.stopPropagation();
            $('#userPopover').toggleClass('hidden');
        });

        // Close popover when clicking outside
        $(document).click(function(e) {
            if (!$(e.target).closest('#userMenuBtn, #userPopover').length) {
                $('#userPopover').addClass('hidden');
            }
        });
    </script>
</body>
</html>
<?php
}
?>