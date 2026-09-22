<?php
/**
 * invitation_helpers.php
 *
 * All invitation-related backend logic for TaskHub v2.
 * Requires $conn (functions.php) and auth_helpers.php to be loaded first.
 */

define('INVITATION_EXPIRY_HOURS', 24);

// ----------------------------------------------------------------
// Core invitation helpers
// ----------------------------------------------------------------

/**
 * Create a pending user and send/display the invitation.
 *
 * @param  string      $email           Unique email for the new user.
 * @param  string      $username        Username (optional; falls back to email prefix).
 * @param  string      $tempPassword    Temporary password (plaintext — will be hashed).
 * @param  int         $roleId          1 = admin, 2 = user.
 * @param  int|null    $projectId       Optional initial project membership.
 * @param  int         $createdBy       Admin user ID performing the action.
 * @return array{success: bool, message: string, dev_link?: string, user_id?: int}
 */
function createInvitation(
    string $email,
    string $username,
    string $tempPassword,
    int    $roleId,
    ?int   $projectId,
    int    $createdBy
): array {
    global $conn;

    $email    = trim(strtolower($email));
    $username = trim($username) !== '' ? trim($username) : explode('@', $email)[0];

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Format email tidak valid.'];
    }

    // Validate password strength
    if (!isStrongPassword($tempPassword)) {
        return ['success' => false, 'message' => 'Password sementara minimal 8 karakter dan mengandung huruf serta angka.'];
    }

    // Check email uniqueness (active or pending)
    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $stmt->close();
        return ['success' => false, 'message' => 'Email sudah terdaftar.'];
    }
    $stmt->close();

    // Check username uniqueness
    $stmt = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $stmt->close();
        // Auto-generate a unique username
        $username = $username . '_' . substr(md5(uniqid()), 0, 4);
    } else {
        $stmt->close();
    }

    $hashedPassword = hashPassword($tempPassword);
    $status         = 'pending';
    $mustReset      = 1;

    // Validate roleId
    $allowedRoles = [1, 2];
    if (!in_array($roleId, $allowedRoles, true)) {
        $roleId = 2;
    }

    // Insert user
    $stmt = $conn->prepare(
        'INSERT INTO users (username, email, password, role_id, status, must_reset_password)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('sssisi', $username, $email, $hashedPassword, $roleId, $status, $mustReset);
    if (!$stmt->execute()) {
        $stmt->close();
        return ['success' => false, 'message' => 'Gagal membuat user: ' . $conn->error];
    }
    $userId = (int) $conn->insert_id;
    $stmt->close();

    // Generate and store invitation token
    $tokenResult = generateInvitationToken($userId, $projectId, $createdBy);
    if (!$tokenResult['success']) {
        // Roll back user creation
        $conn->query('DELETE FROM users WHERE id = ' . $userId);
        return $tokenResult;
    }

    $rawToken = $tokenResult['raw_token'];

    // Build activation link
    $devLink = buildInviteLink($rawToken);

    // Attempt email delivery
    $delivered = deliverInvitationEmail($email, $username, $devLink);

    return [
        'success'   => true,
        'user_id'   => $userId,
        'message'   => $delivered ? 'Invitation berhasil dikirim ke ' . $email . '.' : 'User dibuat. Email tidak terkirim.',
        'dev_link'  => $devLink,     // always returned for dev fallback display
        'delivered' => $delivered,
    ];
}

/**
 * Generate a new invitation token for an existing user.
 * Revokes all previous active tokens first.
 */
function generateInvitationToken(int $userId, ?int $projectId, int $createdBy): array
{
    global $conn;

    // Revoke all existing active invitations for this user
    revokeAllActiveInvitations($userId);

    // Generate cryptographically secure token
    $rawToken  = bin2hex(random_bytes(32));   // 64-char hex
    $tokenHash = hash('sha256', $rawToken);
    $expiresAt = date('Y-m-d H:i:s', time() + INVITATION_EXPIRY_HOURS * 3600);

    $stmt = $conn->prepare(
        'INSERT INTO user_invitations (user_id, project_id, token_hash, expires_at, created_by)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('iissi', $userId, $projectId, $tokenHash, $expiresAt, $createdBy);
    if (!$stmt->execute()) {
        $stmt->close();
        return ['success' => false, 'message' => 'Gagal membuat token invitation.'];
    }
    $stmt->close();

    return ['success' => true, 'raw_token' => $rawToken];
}

/**
 * Revoke all active (not yet used/revoked/expired) invitations for a user.
 */
function revokeAllActiveInvitations(int $userId): void
{
    global $conn;
    $now  = date('Y-m-d H:i:s');
    $stmt = $conn->prepare(
        'UPDATE user_invitations
         SET revoked_at = ?
         WHERE user_id = ? AND used_at IS NULL AND revoked_at IS NULL'
    );
    $stmt->bind_param('si', $now, $userId);
    $stmt->execute();
    $stmt->close();
}

/**
 * Resend an invitation (revoke old tokens, generate a new one, re-deliver).
 * Only allowed for pending users.
 */
function resendInvitation(int $userId, int $adminId): array
{
    global $conn;

    // Verify user exists and is pending
    $stmt = $conn->prepare('SELECT email, username, status FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return ['success' => false, 'message' => 'User tidak ditemukan.'];
    }
    if ($row['status'] !== 'pending') {
        return ['success' => false, 'message' => 'Hanya user pending yang dapat di-resend invitation.'];
    }

    // Get current project_id from the latest invitation (if any)
    $stmtP = $conn->prepare(
        'SELECT project_id FROM user_invitations WHERE user_id = ? ORDER BY id DESC LIMIT 1'
    );
    $stmtP->bind_param('i', $userId);
    $stmtP->execute();
    $lastInv   = $stmtP->get_result()->fetch_assoc();
    $stmtP->close();
    $projectId = $lastInv ? $lastInv['project_id'] : null;

    $tokenResult = generateInvitationToken($userId, $projectId, $adminId);
    if (!$tokenResult['success']) {
        return $tokenResult;
    }

    $rawToken = $tokenResult['raw_token'];
    $devLink  = buildInviteLink($rawToken);
    $delivered = deliverInvitationEmail($row['email'], $row['username'], $devLink);

    return [
        'success'   => true,
        'message'   => $delivered ? 'Invitation baru dikirim ke ' . $row['email'] . '.' : 'Token baru dibuat. Email tidak terkirim.',
        'dev_link'  => $devLink,
        'delivered' => $delivered,
    ];
}

/**
 * Revoke all active invitations for a pending user (admin action).
 */
function revokeInvitation(int $userId): array
{
    global $conn;

    $stmt = $conn->prepare('SELECT status FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return ['success' => false, 'message' => 'User tidak ditemukan.'];
    }

    revokeAllActiveInvitations($userId);
    return ['success' => true, 'message' => 'Invitation berhasil direvoke.'];
}

// ----------------------------------------------------------------
// Invitation validation & activation
// ----------------------------------------------------------------

/**
 * Look up and validate an invitation token.
 * Returns the invitation row + user row if valid, or an error.
 *
 * @return array{valid: bool, message?: string, invitation?: array, user?: array}
 */
function validateInvitationToken(string $rawToken): array
{
    global $conn;

    if (strlen($rawToken) !== 64 || !ctype_xdigit($rawToken)) {
        return ['valid' => false, 'message' => 'Link invitation tidak valid.'];
    }

    $tokenHash = hash('sha256', $rawToken);
    $now       = date('Y-m-d H:i:s');

    $stmt = $conn->prepare(
        'SELECT i.*, u.email, u.username, u.status, u.password AS stored_password
         FROM user_invitations i
         JOIN users u ON u.id = i.user_id
         WHERE i.token_hash = ?
         LIMIT 1'
    );
    $stmt->bind_param('s', $tokenHash);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return ['valid' => false, 'message' => 'Link invitation tidak ditemukan atau sudah tidak berlaku.'];
    }

    if ($row['used_at'] !== null) {
        return ['valid' => false, 'message' => 'Invitation ini sudah digunakan.'];
    }

    if ($row['revoked_at'] !== null) {
        return ['valid' => false, 'message' => 'Invitation ini sudah dicabut oleh admin.'];
    }

    if ($row['expires_at'] < $now) {
        return ['valid' => false, 'message' => 'Invitation sudah expired. Minta admin untuk mengirim ulang.'];
    }

    if ($row['status'] !== 'pending') {
        return ['valid' => false, 'message' => 'Akun ini sudah aktif atau ditangguhkan.'];
    }

    return [
        'valid'      => true,
        'invitation' => $row,
        'user'       => [
            'id'              => $row['user_id'],
            'email'           => $row['email'],
            'username'        => $row['username'],
            'status'          => $row['status'],
            'stored_password' => $row['stored_password'],
        ],
    ];
}

/**
 * Activate a user account via invitation.
 *
 * Steps (inside a single transaction):
 *   1. Validate token.
 *   2. Verify email + temp password.
 *   3. Set new password, mark user active.
 *   4. Mark token used.
 *   5. Add to project if invitation has project_id.
 *
 * @param  string  $rawToken      From URL.
 * @param  string  $email         Submitted by user.
 * @param  string  $tempPassword  Submitted by user (must match stored hash).
 * @param  string  $newPassword   New password chosen by user.
 * @param  string  $confirmPassword
 * @return array{success: bool, message: string, user_id?: int}
 */
function activateInvitation(
    string $rawToken,
    string $email,
    string $tempPassword,
    string $newPassword,
    string $confirmPassword
): array {
    global $conn;

    $validation = validateInvitationToken($rawToken);
    if (!$validation['valid']) {
        return ['success' => false, 'message' => $validation['message']];
    }

    $invitation = $validation['invitation'];
    $user       = $validation['user'];

    // Check email match (case-insensitive)
    if (strtolower(trim($email)) !== strtolower($user['email'])) {
        return ['success' => false, 'message' => 'Email atau password sementara tidak cocok.'];
    }

    // Verify temp password against stored hash
    if (!verifyPassword($tempPassword, $user['stored_password'])) {
        return ['success' => false, 'message' => 'Email atau password sementara tidak cocok.'];
    }

    // Password confirmation
    if ($newPassword !== $confirmPassword) {
        return ['success' => false, 'message' => 'Password baru dan konfirmasi tidak cocok.'];
    }

    // Strength check
    if (!isStrongPassword($newPassword)) {
        return ['success' => false, 'message' => 'Password baru minimal 8 karakter dan mengandung huruf serta angka.'];
    }

    // New password must differ from temp password
    if ($newPassword === $tempPassword) {
        return ['success' => false, 'message' => 'Password baru tidak boleh sama dengan password sementara.'];
    }

    $newHash      = hashPassword($newPassword);
    $now          = date('Y-m-d H:i:s');
    $userId       = (int) $user['id'];
    $invitationId = (int) $invitation['id'];
    $projectId    = $invitation['project_id'] ? (int) $invitation['project_id'] : null;
    $createdBy    = (int) $invitation['created_by'];

    // Run everything in a transaction
    $conn->begin_transaction();
    try {
        // Update user
        $stmt = $conn->prepare(
            'UPDATE users
             SET password = ?, status = "active", must_reset_password = 0, activated_at = ?
             WHERE id = ? AND status = "pending"'
        );
        $stmt->bind_param('ssi', $newHash, $now, $userId);
        $stmt->execute();
        if ($stmt->affected_rows !== 1) {
            throw new RuntimeException('User sudah aktif atau tidak ditemukan.');
        }
        $stmt->close();

        // Mark invitation used
        $stmt = $conn->prepare('UPDATE user_invitations SET used_at = ? WHERE id = ?');
        $stmt->bind_param('si', $now, $invitationId);
        $stmt->execute();
        $stmt->close();

        // Auto-add to project if specified
        if ($projectId !== null) {
            $stmt = $conn->prepare(
                'INSERT IGNORE INTO project_members (project_id, user_id, added_by, created_at)
                 VALUES (?, ?, ?, ?)'
            );
            $stmt->bind_param('iiis', $projectId, $userId, $createdBy, $now);
            $stmt->execute();
            $stmt->close();
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        return ['success' => false, 'message' => 'Aktivasi gagal: ' . $e->getMessage()];
    }

    return ['success' => true, 'message' => 'Akun berhasil diaktifkan! Silakan login.', 'user_id' => $userId];
}

// ----------------------------------------------------------------
// Delivery
// ----------------------------------------------------------------

/**
 * Build the full activation URL for a raw token.
 */
function buildInviteLink(string $rawToken): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . url('auth/invite') . '?token=' . urlencode($rawToken);
}

/**
 * Attempt to deliver invitation via SMTP/mail, using environment configuration.
 *
 * Configuration (set in environment or .env.php):
 *   MAIL_FROM, MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD
 *
 * Falls back to PHP mail() when MAIL_HOST is not set.
 * Returns false (silently) when no mail function is available — admin sees dev_link instead.
 *
 * NOTE: Token is NOT logged. Only the activation URL (which contains the raw token)
 * is sent to the recipient's email.
 */
function deliverInvitationEmail(string $toEmail, string $toName, string $activationLink): bool
{
    // Load optional .env.php if it exists (not in repository)
    $envFile = __DIR__ . '/../../.env.php';
    if (file_exists($envFile)) {
        include_once $envFile;
    }

    $mailFrom    = getenv('MAIL_FROM')     ?: (defined('MAIL_FROM')     ? MAIL_FROM     : '');
    $mailHost    = getenv('MAIL_HOST')     ?: (defined('MAIL_HOST')     ? MAIL_HOST     : '');
    $mailPort    = getenv('MAIL_PORT')     ?: (defined('MAIL_PORT')     ? MAIL_PORT     : 587);
    $mailUser    = getenv('MAIL_USERNAME') ?: (defined('MAIL_USERNAME') ? MAIL_USERNAME : '');
    $mailPass    = getenv('MAIL_PASSWORD') ?: (defined('MAIL_PASSWORD') ? MAIL_PASSWORD : '');

    $subject = 'Undangan TaskHub — Aktifkan Akun Anda';
    $body    = "Halo {$toName},\n\n"
             . "Anda diundang untuk bergabung ke TaskHub.\n\n"
             . "Klik link berikut untuk mengaktifkan akun Anda (berlaku " . INVITATION_EXPIRY_HOURS . " jam):\n"
             . $activationLink . "\n\n"
             . "Jika Anda tidak merasa mendaftar, abaikan email ini.\n\n"
             . "Terima kasih,\nTim TaskHub";

    // SMTP via PHPMailer — only if available
    if ($mailHost !== '' && class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        try {
            $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host       = $mailHost;
            $mailer->SMTPAuth   = true;
            $mailer->Username   = $mailUser;
            $mailer->Password   = $mailPass;
            $mailer->SMTPSecure = (int) $mailPort === 465 ? 'ssl' : 'tls';
            $mailer->Port       = (int) $mailPort;
            $mailer->setFrom($mailFrom ?: $mailUser, 'TaskHub');
            $mailer->addAddress($toEmail, $toName);
            $mailer->Subject = $subject;
            $mailer->Body    = $body;
            $mailer->send();
            return true;
        } catch (Throwable $e) {
            error_log('TaskHub mail error: ' . $e->getMessage());
            return false;
        }
    }

    // Fallback: PHP mail()
    if (function_exists('mail') && $mailFrom !== '') {
        $headers = 'From: TaskHub <' . $mailFrom . ">\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8";
        return @mail($toEmail, $subject, $body, $headers);
    }

    // No delivery method configured — admin must use the dev_link
    return false;
}

// ----------------------------------------------------------------
// Query helpers used by UI
// ----------------------------------------------------------------

/**
 * Get all users with their latest invitation status.
 * Used by admin user management page.
 */
function getUsersWithInvitationStatus(): array
{
    global $conn;

    $sql = "SELECT
                u.id, u.username, u.email, u.status, u.must_reset_password,
                u.activated_at, u.role_id,
                r.name AS role_name,
                i.id AS inv_id,
                i.expires_at,
                i.used_at,
                i.revoked_at,
                i.created_at AS inv_created_at
            FROM users u
            LEFT JOIN roles r ON r.id = u.role_id
            LEFT JOIN user_invitations i ON i.id = (
                SELECT id FROM user_invitations
                WHERE user_id = u.id
                ORDER BY created_at DESC
                LIMIT 1
            )
            ORDER BY u.id DESC";

    $result = $conn->query($sql);
    $users  = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }
    return $users;
}

/**
 * Get the latest active (non-used, non-revoked, non-expired) invitation for a user.
 * Returns null when there is no active invitation.
 */
function getActiveInvitation(int $userId): ?array
{
    global $conn;
    $now  = date('Y-m-d H:i:s');
    $stmt = $conn->prepare(
        'SELECT * FROM user_invitations
         WHERE user_id = ? AND used_at IS NULL AND revoked_at IS NULL AND expires_at > ?
         ORDER BY created_at DESC LIMIT 1'
    );
    $stmt->bind_param('is', $userId, $now);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Determine a human-readable invitation status label.
 */
function invitationStatusLabel(array $user): string
{
    if ($user['status'] === 'active') {
        return 'active';
    }
    if ($user['status'] === 'suspended') {
        return 'suspended';
    }
    // pending user
    if (!$user['inv_id']) {
        return 'no_invitation';
    }
    if ($user['used_at']) {
        return 'used'; // shouldn't happen for pending, but guard
    }
    if ($user['revoked_at']) {
        return 'revoked';
    }
    if ($user['expires_at'] < date('Y-m-d H:i:s')) {
        return 'expired';
    }
    return 'pending';
}

/**
 * Step-2 activation: set new password and mark account active.
 * Called after step-1 (email + temp password) has already been verified.
 */
function activateInvitationStep2(
    int    $userId,
    int    $invitationId,
    ?int   $projectId,
    int    $createdBy,
    string $newPassword,
    string $confirmPassword
): array {
    global $conn;

    if ($newPassword !== $confirmPassword) {
        return ['success' => false, 'message' => 'Password baru dan konfirmasi tidak cocok.'];
    }

    if (!isStrongPassword($newPassword)) {
        return ['success' => false, 'message' => 'Password baru minimal 8 karakter dan mengandung huruf serta angka.'];
    }

    $newHash = hashPassword($newPassword);
    $now     = date('Y-m-d H:i:s');

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            'UPDATE users
             SET password = ?, status = "active", must_reset_password = 0, activated_at = ?
             WHERE id = ? AND status = "pending"'
        );
        $stmt->bind_param('ssi', $newHash, $now, $userId);
        $stmt->execute();
        if ($stmt->affected_rows !== 1) {
            throw new RuntimeException('User tidak ditemukan atau sudah aktif.');
        }
        $stmt->close();

        $stmt = $conn->prepare('UPDATE user_invitations SET used_at = ? WHERE id = ?');
        $stmt->bind_param('si', $now, $invitationId);
        $stmt->execute();
        $stmt->close();

        if ($projectId !== null) {
            $stmt = $conn->prepare(
                'INSERT IGNORE INTO project_members (project_id, user_id, added_by, created_at)
                 VALUES (?, ?, ?, ?)'
            );
            $stmt->bind_param('iiis', $projectId, $userId, $createdBy, $now);
            $stmt->execute();
            $stmt->close();
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        return ['success' => false, 'message' => 'Aktivasi gagal: ' . $e->getMessage()];
    }

    return ['success' => true, 'message' => 'Akun berhasil diaktifkan! Silakan login.', 'user_id' => $userId];
}
