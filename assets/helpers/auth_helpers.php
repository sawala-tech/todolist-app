<?php
/**
 * auth_helpers.php — TaskHub v4
 *
 * Tidak ada lagi "admin sistem". Semua akses berbasis membership project
 * dan project ownership. isAdmin() dihapus.
 */

// ────────────────────────────────────────────────────────────────
// CSRF
// ────────────────────────────────────────────────────────────────

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES) . '">';
}

function verifyCsrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrfToken(), $submitted)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}

// ────────────────────────────────────────────────────────────────
// Password helpers
// ────────────────────────────────────────────────────────────────

function hashPassword(string $password): string
{
    return password_hash($password, PASSWORD_BCRYPT);
}

function verifyPassword(string $plaintext, string $storedHash): bool
{
    if (str_starts_with($storedHash, '$2y$') || str_starts_with($storedHash, '$2a$') || str_starts_with($storedHash, '$2b$')) {
        return password_verify($plaintext, $storedHash);
    }
    if (strlen($storedHash) === 64 && ctype_xdigit($storedHash)) {
        return hash_equals($storedHash, hash('sha256', $plaintext));
    }
    return false;
}

function rehashLegacyPassword(int $userId, string $plaintext): void
{
    global $conn;
    $newHash = hashPassword($plaintext);
    $stmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
    $stmt->bind_param('si', $newHash, $userId);
    $stmt->execute();
    $stmt->close();
}

function isStrongPassword(string $password): bool
{
    return strlen($password) >= 8
        && preg_match('/[a-zA-Z]/', $password)
        && preg_match('/[0-9]/', $password);
}

// ────────────────────────────────────────────────────────────────
// Session / access guards
// ────────────────────────────────────────────────────────────────

/**
 * Require logged-in active user. Redirects to signin if not.
 */
function requireActiveUser(): void
{
    if (!isset($_SESSION['user'])) {
        // Preserve current URL for redirect after login
        $currentUrl = $_SERVER['REQUEST_URI'] ?? '';
        header('Location: ' . url('auth/signin') . ($currentUrl ? '?next=' . urlencode($currentUrl) : ''));
        exit;
    }

    $status = $_SESSION['user']['status'] ?? 'active';
    if ($status === 'suspended') {
        session_destroy();
        header('Location: ' . url('auth/signin') . '?error=suspended');
        exit;
    }
}

/**
 * Check if current user is member OR owner of the project.
 * Owner has implicit access even without a project_members row.
 */
function canAccessProject(int $projectId): bool
{
    global $conn;

    if (!isset($_SESSION['user'])) return false;

    $userId = (int)$_SESSION['user']['id'];

    // Project owner always has access
    $stmt = $conn->prepare('SELECT id FROM projects WHERE id = ? AND owner_id = ? LIMIT 1');
    $stmt->bind_param('ii', $projectId, $userId);
    $stmt->execute();
    $isOwner = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    if ($isOwner) return true;

    // Check membership
    $stmt = $conn->prepare('SELECT 1 FROM project_members WHERE project_id = ? AND user_id = ? LIMIT 1');
    $stmt->bind_param('ii', $projectId, $userId);
    $stmt->execute();
    $isMember = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $isMember;
}

/**
 * Require project access; terminates with 403 if denied.
 */
function requireProjectAccess(int $projectId): void
{
    requireActiveUser();
    if (!canAccessProject($projectId)) {
        http_response_code(403);
        exit('Akses ditolak.');
    }
}

/**
 * Require task access; uses canEditTask() from task_helpers.php.
 */
function requireTaskAccess(array $task): void
{
    requireActiveUser();
    $userId = (int)($_SESSION['user']['id'] ?? 0);
    if (!canEditTask($task, $userId)) {
        http_response_code(403);
        exit('Akses ditolak.');
    }
}

// ────────────────────────────────────────────────────────────────
// Secure file upload
// ────────────────────────────────────────────────────────────────

function saveFileSecure(
    array $file,
    array $allowedExtensions = ['jpg','jpeg','png','gif','pdf','doc','docx','txt','zip'],
    array $allowedMimes = [
        'image/jpeg','image/png','image/gif',
        'application/pdf','application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain','application/zip','application/x-zip-compressed',
    ],
    int $maxBytes = 5 * 1024 * 1024
): string|null|false {
    if (!isset($file['error'])) return null;
    if ($file['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    if ($file['size'] > $maxBytes) return false;

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions, true)) return false;

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    if (!in_array($finfo->file($file['tmp_name']), $allowedMimes, true)) return false;

    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file['name']));
    $safeName = preg_replace('/\.{2,}/', '.', $safeName);
    $fileName = time() . '_' . $safeName;
    $targetFile = __DIR__ . '/../../assets/public/' . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $targetFile)) return false;
    return $fileName;
}
