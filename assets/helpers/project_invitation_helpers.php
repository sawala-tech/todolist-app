<?php
/**
 * project_invitation_helpers.php — TaskHub v4
 *
 * Project invitation system: generate links/codes, search users, join via invitation
 */

// ────────────────────────────────────────────────────────────────
// Generate invitation codes & tokens
// ────────────────────────────────────────────────────────────────

/**
 * Generate user-friendly invitation code (8 chars: ABC123XY)
 */
function generateInviteCode(): string
{
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code = '';
    for ($i = 0; $i < 8; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

/**
 * Generate cryptographic invitation token (32 bytes = 64 hex chars)
 */
function generateInviteToken(): string
{
    return bin2hex(random_bytes(32));
}

/**
 * Create new project invitation
 * 
 * @param int $projectId
 * @param int $createdBy User ID who creates the invitation
 * @param int $maxUses Default 10, 0 = unlimited
 * @param int $hoursValid Default 72 hours (3 days)
 * @return array ['code' => string, 'token' => string, 'id' => int]
 */
function createProjectInvitation(int $projectId, int $createdBy, int $maxUses = 10, int $hoursValid = 72): array
{
    global $conn;

    $code = generateInviteCode();
    $token = generateInviteToken();
    $expiresAt = date('Y-m-d H:i:s', strtotime("+{$hoursValid} hours"));

    // Ensure unique code (very unlikely collision, but better safe)
    $stmt = $conn->prepare('SELECT id FROM project_invitations WHERE code = ? LIMIT 1');
    $stmt->bind_param('s', $code);
    $stmt->execute();
    while ($stmt->get_result()->num_rows > 0) {
        $stmt->close();
        $code = generateInviteCode();
        $stmt = $conn->prepare('SELECT id FROM project_invitations WHERE code = ? LIMIT 1');
        $stmt->bind_param('s', $code);
        $stmt->execute();
    }
    $stmt->close();

    $stmt = $conn->prepare('
        INSERT INTO project_invitations 
        (project_id, created_by, code, token, expires_at, max_uses, use_count, is_active)
        VALUES (?, ?, ?, ?, ?, ?, 0, 1)
    ');
    $stmt->bind_param('iisssi', $projectId, $createdBy, $code, $token, $expiresAt, $maxUses);
    $stmt->execute();
    $invitationId = $conn->insert_id;
    $stmt->close();

    return [
        'id' => $invitationId,
        'code' => $code,
        'token' => $token,
        'expires_at' => $expiresAt
    ];
}

// ────────────────────────────────────────────────────────────────
// Validate & use invitations
// ────────────────────────────────────────────────────────────────

/**
 * Get valid invitation by token
 * 
 * @param string $token
 * @return array|null Invitation data or null if invalid/expired
 */
function getValidInvitation(string $token): ?array
{
    global $conn;

    $stmt = $conn->prepare('
        SELECT pi.*, p.name as project_name, p.owner_id
        FROM project_invitations pi
        JOIN projects p ON pi.project_id = p.id
        WHERE pi.token = ? 
          AND pi.is_active = 1 
          AND pi.expires_at > NOW()
          AND (pi.max_uses = 0 OR pi.use_count < pi.max_uses)
    ');
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $invitation = $result->fetch_assoc();
    $stmt->close();

    return $invitation ?: null;
}

/**
 * Use invitation: add user to project and increment use_count
 * 
 * @param string $token
 * @param int $userId
 * @return bool Success
 */
function useInvitation(string $token, int $userId): bool
{
    global $conn;

    $invitation = getValidInvitation($token);
    if (!$invitation) return false;

    $projectId = (int)$invitation['project_id'];

    // Check if user is already a member or owner
    if (isProjectMember($projectId, $userId) || isProjectOwner($projectId, $userId)) {
        return true; // Already member, consider success
    }

    $conn->begin_transaction();
    try {
        // Direct INSERT to project_members
        $stmt = $conn->prepare('INSERT INTO project_members (project_id, user_id, added_by) VALUES (?, ?, ?)');
        $addedBy = (int)$invitation['created_by']; // invitation creator
        $stmt->bind_param('iii', $projectId, $userId, $addedBy);
        $stmt->execute();
        $stmt->close();

        // Increment use_count
        $stmt = $conn->prepare('UPDATE project_invitations SET use_count = use_count + 1 WHERE token = ?');
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("useInvitation error: " . $e->getMessage());
        return false;
    }
}

// ────────────────────────────────────────────────────────────────
// Project membership helpers
// ────────────────────────────────────────────────────────────────

/**
 * Check if user is project member
 */
function isProjectMember(int $projectId, int $userId): bool
{
    global $conn;
    $stmt = $conn->prepare('SELECT 1 FROM project_members WHERE project_id = ? AND user_id = ? LIMIT 1');
    $stmt->bind_param('ii', $projectId, $userId);
    $stmt->execute();
    $result = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $result;
}

// ────────────────────────────────────────────────────────────────
// User search for invitations
// ────────────────────────────────────────────────────────────────

/**
 * Search users by username or email (for invite by search)
 * Excludes users who are already members of the project
 * 
 * @param string $query
 * @param int $projectId
 * @param int $limit
 * @return array Users array
 */
function searchUsersForInvite(string $query, int $projectId, int $limit = 10): array
{
    global $conn;

    $searchTerm = '%' . $query . '%';
    $stmt = $conn->prepare('
        SELECT u.id, u.username, u.email, u.full_name
        FROM users u
        WHERE (u.username LIKE ? OR u.email LIKE ? OR u.full_name LIKE ?)
          AND u.status = "active"
          AND u.id NOT IN (
              SELECT owner_id FROM projects WHERE id = ?
              UNION
              SELECT user_id FROM project_members WHERE project_id = ?
          )
        ORDER BY u.username
        LIMIT ?
    ');
    $stmt->bind_param('ssssii', $searchTerm, $searchTerm, $searchTerm, $projectId, $projectId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $stmt->close();
    return $users;
}

// ────────────────────────────────────────────────────────────────
// Invitation management
// ────────────────────────────────────────────────────────────────

/**
 * Get project invitations (for project settings page)
 */
function getProjectInvitations(int $projectId): array
{
    global $conn;
    
    $stmt = $conn->prepare('
        SELECT pi.*, u.username as created_by_username
        FROM project_invitations pi
        LEFT JOIN users u ON pi.created_by = u.id
        WHERE pi.project_id = ?
        ORDER BY pi.created_at DESC
    ');
    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $invitations = [];
    while ($row = $result->fetch_assoc()) {
        $invitations[] = $row;
    }
    $stmt->close();
    return $invitations;
}

/**
 * Deactivate invitation
 */
function deactivateInvitation(int $invitationId, int $projectId): bool
{
    global $conn;
    
    // Security: ensure invitation belongs to the project
    $stmt = $conn->prepare('UPDATE project_invitations SET is_active = 0 WHERE id = ? AND project_id = ?');
    $stmt->bind_param('ii', $invitationId, $projectId);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

/**
 * Generate invitation URL with full domain
 */
function getInvitationUrl(string $token): string
{
    return fullUrl('invite/' . $token);
}