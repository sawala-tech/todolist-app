<?php
/**
 * project_helpers.php
 *
 * Project CRUD, membership management, and authorization helpers for TaskHub v2.
 * Requires $conn (functions.php) and auth_helpers.php.
 */

// ----------------------------------------------------------------
// Project CRUD
// ----------------------------------------------------------------

/**
 * Create a new project. Returns project ID on success, false on failure.
 */
function createProject(
    string  $name,
    string  $description,
    string  $status,
    int     $ownerId,
    ?string $startDate = null,
    ?string $deadline = null
) {
    global $conn;

    $name        = trim($name);
    $description = trim($description);
    $status      = in_array($status, ['draft', 'active', 'archived'], true) ? $status : 'draft';

    if ($name === '') {
        return false;
    }

    $startDate = ($startDate !== null && $startDate !== '') ? date('Y-m-d', strtotime($startDate)) : null;
    $deadline  = ($deadline  !== null && $deadline  !== '') ? date('Y-m-d', strtotime($deadline))  : null;

    $stmt = $conn->prepare(
        'INSERT INTO projects (name, description, start_date, deadline, status, owner_id)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('sssssi', $name, $description, $startDate, $deadline, $status, $ownerId);
    if (!$stmt->execute()) {
        $stmt->close();
        return false;
    }
    $id = (int) $conn->insert_id;
    $stmt->close();

    return $id;
}

/**
 * Update an existing project.
 */
function updateProject(
    int     $id,
    string  $name,
    string  $description,
    ?string $startDate,
    ?string $deadline,
    string  $status
): array {
    global $conn;

    $name        = trim($name);
    $description = trim($description);
    $status      = in_array($status, ['draft', 'active', 'archived'], true) ? $status : 'draft';

    if ($name === '') {
        return ['success' => false, 'message' => 'Nama project tidak boleh kosong.'];
    }

    $startDate = ($startDate !== null && $startDate !== '') ? date('Y-m-d', strtotime($startDate)) : null;
    $deadline  = ($deadline  !== null && $deadline  !== '') ? date('Y-m-d', strtotime($deadline))  : null;

    $stmt = $conn->prepare(
        'UPDATE projects
         SET name = ?, description = ?, start_date = ?, deadline = ?, status = ?
         WHERE id = ?'
    );
    $stmt->bind_param('sssssi', $name, $description, $startDate, $deadline, $status, $id);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok
        ? ['success' => true, 'message' => 'Project berhasil diperbarui.']
        : ['success' => false, 'message' => 'Gagal memperbarui project.'];
}

/**
 * Get a single project by ID.
 */
function getProject(int $id): ?array
{
    global $conn;
    $stmt = $conn->prepare(
        'SELECT p.*, u.username AS owner_name
         FROM projects p
         LEFT JOIN users u ON u.id = p.owner_id
         WHERE p.id = ?'
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Get all projects (admin view).
 */
function getAllProjects(): array
{
    global $conn;
    $sql = "SELECT p.*, u.username AS owner_name,
                   (SELECT COUNT(*) FROM project_members pm WHERE pm.project_id = p.id) AS member_count,
                   (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id) AS task_count
            FROM projects p
            LEFT JOIN users u ON u.id = p.owner_id
            ORDER BY p.created_at DESC";
    $result   = $conn->query($sql);
    $projects = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $projects[] = $row;
        }
    }
    return $projects;
}

/**
 * Get projects accessible by a specific user (member or owner).
 */
function getProjectsForUser(int $userId): array
{
    global $conn;
    $stmt = $conn->prepare(
        "SELECT p.*,
                (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id) AS task_count,
                (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status = 'done') AS done_count
         FROM projects p
         WHERE p.status != 'archived'
           AND (p.owner_id = ? OR p.id IN (SELECT project_id FROM project_members WHERE user_id = ?))
         ORDER BY p.created_at DESC"
    );
    $stmt->bind_param('ii', $userId, $userId);
    $stmt->execute();
    $rows = [];
    $res  = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $res->free();
    $stmt->close();
    return $rows;
}

// ----------------------------------------------------------------
// Membership
// ----------------------------------------------------------------

/**
 * Add a user to a project.
 * Rejects pending users (unless they get added via invitation flow).
 */
function addProjectMember(int $projectId, int $userId, int $addedBy): array
{
    global $conn;

    // Verify user exists and is active
    $stmt = $conn->prepare('SELECT status FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        return ['success' => false, 'message' => 'User tidak ditemukan.'];
    }
    if ($user['status'] !== 'active') {
        return ['success' => false, 'message' => 'Hanya user aktif yang dapat ditambahkan sebagai member.'];
    }

    // Verify project exists
    $stmtP = $conn->prepare('SELECT id FROM projects WHERE id = ? LIMIT 1');
    $stmtP->bind_param('i', $projectId);
    $stmtP->execute();
    if ($stmtP->get_result()->num_rows === 0) {
        $stmtP->close();
        return ['success' => false, 'message' => 'Project tidak ditemukan.'];
    }
    $stmtP->close();

    $now  = date('Y-m-d H:i:s');
    $stmt = $conn->prepare(
        'INSERT IGNORE INTO project_members (project_id, user_id, added_by, created_at) VALUES (?, ?, ?, ?)'
    );
    $stmt->bind_param('iiis', $projectId, $userId, $addedBy, $now);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected === 0) {
        return ['success' => false, 'message' => 'User sudah menjadi member project ini.'];
    }
    return ['success' => true, 'message' => 'Member berhasil ditambahkan.'];
}

/**
 * Remove a user from a project.
 */
function removeProjectMember(int $projectId, int $userId): array
{
    global $conn;

    $stmt = $conn->prepare('DELETE FROM project_members WHERE project_id = ? AND user_id = ?');
    $stmt->bind_param('ii', $projectId, $userId);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    return $affected > 0
        ? ['success' => true, 'message' => 'Member berhasil dihapus.']
        : ['success' => false, 'message' => 'Member tidak ditemukan di project ini.'];
}

/**
 * Get all members of a project with their user details.
 */
function getProjectMembers(int $projectId): array
{
    global $conn;
    $stmt = $conn->prepare(
        'SELECT u.id, u.username, u.email, u.status, r.name AS role_name,
                pm.created_at AS joined_at, ab.username AS added_by_name
         FROM project_members pm
         JOIN users u ON u.id = pm.user_id
         LEFT JOIN roles r ON r.id = u.role_id
         LEFT JOIN users ab ON ab.id = pm.added_by
         WHERE pm.project_id = ?
         ORDER BY pm.created_at ASC'
    );
    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $rows = [];
    $res  = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $res->free();
    $stmt->close();
    return $rows;
}

/**
 * Get everyone who can be selected as an assignee or reviewer.
 * The owner has implicit project access, so include the owner even when
 * there is no project_members row for them.
 */
function getProjectAssignableUsers(int $projectId): array
{
    global $conn;

    $stmt = $conn->prepare(
        'SELECT u.id, u.username, u.email, u.status,
                CASE WHEN u.id = p.owner_id THEN "owner" ELSE "member" END AS membership_role
         FROM projects p
         JOIN users u ON u.id = p.owner_id
                      OR EXISTS (
                          SELECT 1 FROM project_members pm
                          WHERE pm.project_id = p.id AND pm.user_id = u.id
                      )
         WHERE p.id = ? AND u.status = "active"
         ORDER BY (u.id = p.owner_id) DESC, u.username ASC'
    );
    $stmt->bind_param('i', $projectId);
    $stmt->execute();

    $rows = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $res->free();
    $stmt->close();

    return $rows;
}

/**
 * Get active users who are NOT yet members of a project.
 * Used to populate the "add member" dropdown.
 */
function getEligibleUsersForProject(int $projectId): array
{
    global $conn;
    $stmt = $conn->prepare(
        "SELECT u.id, u.username, u.email
         FROM users u
         JOIN roles r ON r.id = u.role_id
         WHERE u.status = 'active'
           AND r.name = 'user'
           AND u.id NOT IN (
               SELECT user_id FROM project_members WHERE project_id = ?
           )
         ORDER BY u.username ASC"
    );
    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $rows = [];
    $res  = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $res->free();
    $stmt->close();
    return $rows;
}

// ----------------------------------------------------------------
// Project task stats helper
// ----------------------------------------------------------------

/**
 * Get task counts per status for a project.
 */
function getProjectTaskStats(int $projectId): array
{
    global $conn;
    $stmt = $conn->prepare(
        'SELECT status, COUNT(*) AS cnt FROM tasks WHERE project_id = ? GROUP BY status'
    );
    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $res   = $stmt->get_result();
    $stats = [];
    while ($row = $res->fetch_assoc()) {
        $stats[$row['status']] = (int) $row['cnt'];
    }
    $res->free();
    $stmt->close();
    return $stats;
}

/**
 * Get task count for a project.
 */
function getTaskCountByProject(int $projectId): int
{
    global $conn;
    
    $stmt = $conn->prepare('SELECT COUNT(*) as count FROM tasks WHERE project_id = ?');
    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return (int)$result['count'];
}

/**
 * Get member count for a project (including owner).
 */
function getProjectMemberCount(int $projectId): int
{
    global $conn;

    // Owner has implicit access and may or may not also have a membership row.
    // UNION keeps the owner from being counted twice while ensuring an owner-only
    // project still reports one member.
    $stmt = $conn->prepare(
        'SELECT COUNT(*) AS count FROM (
             SELECT owner_id AS user_id FROM projects WHERE id = ?
             UNION
             SELECT user_id FROM project_members WHERE project_id = ?
         ) AS project_users'
    );
    $stmt->bind_param('ii', $projectId, $projectId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return (int)$result['count'];
}
