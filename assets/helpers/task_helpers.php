<?php
/**
 * task_helpers.php — TaskHub v3
 *
 * State machine, reviewer logic, backlog, self-assign.
 * Requires: $conn, auth_helpers.php, project_helpers.php
 */

// ──────────────────────────────────────────────────────────────────
// State machine
// ──────────────────────────────────────────────────────────────────

/**
 * Transitions available to assignee (no reviewer).
 * When reviewer_id IS NULL: assignee can go in_progress → done directly.
 */
const TASK_TRANSITIONS_ASSIGNEE_NO_REVIEWER = [
    'open'        => ['in_progress'],
    'in_progress' => ['done'],
    'revision'    => ['in_progress'],
];

/**
 * Transitions available to assignee when reviewer IS assigned.
 * Assignee cannot set done — reviewer must approve.
 */
const TASK_TRANSITIONS_ASSIGNEE_WITH_REVIEWER = [
    'open'        => ['in_progress'],
    'in_progress' => ['review'],
    'revision'    => ['in_progress'],
];

/**
 * Transitions available to reviewer.
 */
const TASK_TRANSITIONS_REVIEWER = [
    'review' => ['done', 'revision'],
];

/**
 * Full transitions for project owner and admin.
 */
const TASK_TRANSITIONS_ADMIN = [
    'open'        => ['in_progress', 'done'],
    'in_progress' => ['review', 'done', 'open'],
    'review'      => ['done', 'revision'],
    'revision'    => ['in_progress', 'review'],
    'done'        => ['revision', 'in_progress', 'open'],
];

/**
 * Get available transitions for $userId on a given task.
 */
function getAvailableTransitions(array $task, int $userId): array
{
    $status     = $task['status'] ?? 'open';
    $assigneeId = (int)($task['assigned_to'] ?? 0);
    $reviewerId = (int)($task['reviewer_id'] ?? 0);
    $projectId  = (int)($task['project_id'] ?? 0);

    // Admin or project owner — full control
    if (isProjectOwner($projectId, $userId)) {
        return TASK_TRANSITIONS_ADMIN[$status] ?? [];
    }

    $transitions = [];

    // Reviewer transitions
    if ($reviewerId === $userId) {
        $transitions = array_merge($transitions, TASK_TRANSITIONS_REVIEWER[$status] ?? []);
    }

    // Assignee transitions
    if ($assigneeId === $userId) {
        $map = $reviewerId > 0
            ? TASK_TRANSITIONS_ASSIGNEE_WITH_REVIEWER
            : TASK_TRANSITIONS_ASSIGNEE_NO_REVIEWER;
        $transitions = array_merge($transitions, $map[$status] ?? []);
    }

    return array_unique($transitions);
}

// ──────────────────────────────────────────────────────────────────
// Permission helpers
// ──────────────────────────────────────────────────────────────────

function isProjectOwner(int $projectId, int $userId): bool
{
    global $conn;
    $stmt = $conn->prepare('SELECT id FROM projects WHERE id = ? AND owner_id = ? LIMIT 1');
    $stmt->bind_param('ii', $projectId, $userId);
    $stmt->execute();
    $found = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $found;
}

function isTaskReviewer(array $task, int $userId): bool
{
    return (int)($task['reviewer_id'] ?? 0) === $userId;
}

function canEditTask(array $task, int $userId): bool
{
    $projectId = (int)($task['project_id'] ?? 0);
    if ($projectId && isProjectOwner($projectId, $userId)) return true;
    return (int)($task['user_id'] ?? 0) === $userId; // creator
}

function canAssignTask(array $task, int $userId): bool
{
    $projectId = (int)($task['project_id'] ?? 0);
    if ($projectId && isProjectOwner($projectId, $userId)) return true;
    return (int)($task['user_id'] ?? 0) === $userId; // creator
}

// ──────────────────────────────────────────────────────────────────
// CRUD
// ──────────────────────────────────────────────────────────────────

function getTaskById(int $taskId): ?array
{
    global $conn;
    $stmt = $conn->prepare(
        "SELECT t.*,
                u.username  AS assignee_name,
                c.username  AS creator_name,
                rv.username AS reviewer_name,
                rb.username AS reviewed_by_name
         FROM tasks t
         LEFT JOIN users u  ON u.id  = t.assigned_to
         LEFT JOIN users c  ON c.id  = t.user_id
         LEFT JOIN users rv ON rv.id = t.reviewer_id
         LEFT JOIN users rb ON rb.id = t.reviewed_by
         WHERE t.id = ?"
    );
    $stmt->bind_param('i', $taskId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Create a project task.
 * Callable by any member, admin, or project owner.
 */
function createProjectTask(array $data, int $creatorId, ?array $file = null): array
{
    global $conn;

    $title       = trim($data['title'] ?? '');
    $description = trim($data['description'] ?? '');
    $deadline    = !empty($data['deadline']) ? date('Y-m-d', strtotime($data['deadline'])) : null;
    $priority    = in_array($data['priority'] ?? '', ['low','medium','high'], true) ? $data['priority'] : 'medium';
    $label       = trim($data['label'] ?? '') ?: null;
    $projectId   = (int)($data['project_id'] ?? 0);
    $assignedTo  = !empty($data['assigned_to'])  ? (int)$data['assigned_to']  : null;
    $reviewerId  = !empty($data['reviewer_id'])   ? (int)$data['reviewer_id']  : null;
    $status      = 'open'; // always starts as open

    if ($title === '') return ['success' => false, 'message' => 'Judul tugas tidak boleh kosong.'];
    if (!$projectId)   return ['success' => false, 'message' => 'Project harus dipilih.'];

    if ($assignedTo !== null) {
        $check = validateAssignee($projectId, $assignedTo);
        if (!$check['valid']) return ['success' => false, 'message' => $check['message']];
    }
    if ($reviewerId !== null) {
        $check = validateAssignee($projectId, $reviewerId);
        if (!$check['valid']) return ['success' => false, 'message' => 'Reviewer bukan anggota project ini.'];
    }

    $attachment = '';
    if ($file && isset($file['error']) && $file['error'] !== UPLOAD_ERR_NO_FILE) {
        $saved = saveFileSecure($file);
        if ($saved === false) return ['success' => false, 'message' => 'File tidak valid atau terlalu besar.'];
        $attachment = $saved ?? '';
    }

    $stmt = $conn->prepare(
        'INSERT INTO tasks (title, description, deadline, priority, label, status, attachment,
                            user_id, project_id, assigned_to, reviewer_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param(
        'sssssssiii' . 'i',
        $title, $description, $deadline, $priority, $label, $status, $attachment,
        $creatorId, $projectId, $assignedTo, $reviewerId
    );
    if (!$stmt->execute()) {
        $stmt->close();
        return ['success' => false, 'message' => 'Gagal membuat tugas: ' . $conn->error];
    }
    $id = (int)$conn->insert_id;
    $stmt->close();

    return ['success' => true, 'id' => $id, 'message' => 'Tugas berhasil dibuat.'];
}

/**
 * Update task. Checks canEditTask permission.
 */
function updateProjectTask(int $taskId, array $data, ?array $file = null): array
{
    global $conn;

    $task = getTaskById($taskId);
    if (!$task) return ['success' => false, 'message' => 'Tugas tidak ditemukan.'];

    $userId = (int)($_SESSION['user']['id'] ?? 0);
    if (!canEditTask($task, $userId)) {
        return ['success' => false, 'message' => 'Akses ditolak.'];
    }

    $title       = trim($data['title']       ?? $task['title']);
    $description = trim($data['description'] ?? $task['description'] ?? '');
    $deadline    = !empty($data['deadline'])  ? date('Y-m-d', strtotime($data['deadline'])) : ($task['deadline'] ?? null);
    $priority    = in_array($data['priority'] ?? '', ['low','medium','high'], true) ? $data['priority'] : ($task['priority'] ?? 'medium');
    $label       = isset($data['label']) ? (trim($data['label']) ?: null) : ($task['label'] ?? null);
    $projectId   = (int)$task['project_id'];

    // Assignee — only creator/owner/admin can change
    $assignedTo = array_key_exists('assigned_to', $data) && canAssignTask($task, $userId)
        ? (!empty($data['assigned_to']) ? (int)$data['assigned_to'] : null)
        : ($task['assigned_to'] ? (int)$task['assigned_to'] : null);

    // Reviewer — only creator/owner/admin can change
    $reviewerId = array_key_exists('reviewer_id', $data) && canAssignTask($task, $userId)
        ? (!empty($data['reviewer_id']) ? (int)$data['reviewer_id'] : null)
        : ($task['reviewer_id'] ? (int)$task['reviewer_id'] : null);

    // Status — admin/owner can force, otherwise use changeTaskStatus
    $status = array_key_exists('status', $data) && isProjectOwner($projectId, $userId)
        ? $data['status']
        : $task['status'];

    if ($assignedTo !== null) {
        $check = validateAssignee($projectId, $assignedTo);
        if (!$check['valid']) return ['success' => false, 'message' => $check['message']];
    }
    if ($reviewerId !== null) {
        $check = validateAssignee($projectId, $reviewerId);
        if (!$check['valid']) return ['success' => false, 'message' => 'Reviewer bukan anggota project ini.'];
    }

    // Attachment
    $attachment = $task['attachment'] ?? '';
    if (!empty($data['replace_attachment']) && $file && isset($file['error']) && $file['error'] !== UPLOAD_ERR_NO_FILE) {
        $saved = saveFileSecure($file);
        if ($saved === false) return ['success' => false, 'message' => 'File tidak valid.'];
        if ($saved) { if ($attachment) deleteFile($attachment); $attachment = $saved; }
    }

    $stmt = $conn->prepare(
        'UPDATE tasks
         SET title=?, description=?, deadline=?, priority=?, label=?,
             status=?, attachment=?, assigned_to=?, reviewer_id=?
         WHERE id=?'
    );
    $stmt->bind_param('sssssssiii', $title, $description, $deadline, $priority, $label,
        $status, $attachment, $assignedTo, $reviewerId, $taskId);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok
        ? ['success' => true, 'message' => 'Tugas berhasil diperbarui.']
        : ['success' => false, 'message' => 'Gagal memperbarui tugas.'];
}

/**
 * Change task status with full permission + transition validation.
 */
function changeTaskStatus(int $taskId, string $newStatus, int $userId): array
{
    global $conn;

    $task = getTaskById($taskId);
    if (!$task) return ['success' => false, 'message' => 'Tugas tidak ditemukan.'];

    $available = getAvailableTransitions($task, $userId);
    if (!in_array($newStatus, $available, true)) {
        return ['success' => false, 'message' => "Transisi ke '{$newStatus}' tidak diizinkan."];
    }

    $now  = date('Y-m-d H:i:s');
    $stmt = $conn->prepare('UPDATE tasks SET status=? WHERE id=?');
    $stmt->bind_param('si', $newStatus, $taskId);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok
        ? ['success' => true, 'message' => 'Status berhasil diperbarui.']
        : ['success' => false, 'message' => 'Gagal memperbarui status.'];
}

/**
 * Approve review → done. Allowed for: reviewer, project owner, admin.
 */
function approveTaskReview(int $taskId, int $userId): array
{
    global $conn;

    $task = getTaskById($taskId);
    if (!$task || $task['status'] !== 'review') {
        return ['success' => false, 'message' => 'Tugas tidak dalam status review.'];
    }

    $projectId = (int)$task['project_id'];
    if (!isProjectOwner($projectId, $userId) && !isTaskReviewer($task, $userId)) {
        return ['success' => false, 'message' => 'Hanya reviewer, project owner, atau admin yang dapat menyetujui.'];
    }

    $now    = date('Y-m-d H:i:s');
    $status = 'done';
    $stmt   = $conn->prepare(
        'UPDATE tasks SET status=?, reviewed_by=?, reviewed_at=?, review_feedback=NULL WHERE id=?'
    );
    $stmt->bind_param('sisi', $status, $userId, $now, $taskId);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok
        ? ['success' => true, 'message' => 'Tugas disetujui dan ditandai selesai.']
        : ['success' => false, 'message' => 'Gagal mengupdate tugas.'];
}

/**
 * Reject review → revision with feedback.
 */
function rejectTaskReview(int $taskId, int $userId, string $feedback): array
{
    global $conn;

    $task = getTaskById($taskId);
    if (!$task || $task['status'] !== 'review') {
        return ['success' => false, 'message' => 'Tugas tidak dalam status review.'];
    }

    $projectId = (int)$task['project_id'];
    if (!isProjectOwner($projectId, $userId) && !isTaskReviewer($task, $userId)) {
        return ['success' => false, 'message' => 'Hanya reviewer, project owner, atau admin yang dapat mengembalikan.'];
    }

    $now      = date('Y-m-d H:i:s');
    $status   = 'revision';
    $feedback = trim($feedback);

    $stmt = $conn->prepare(
        'UPDATE tasks SET status=?, reviewed_by=?, reviewed_at=?, review_feedback=? WHERE id=?'
    );
    $stmt->bind_param('sissi', $status, $userId, $now, $feedback, $taskId);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok
        ? ['success' => true, 'message' => 'Tugas dikembalikan untuk revisi.']
        : ['success' => false, 'message' => 'Gagal mengupdate tugas.'];
}

/**
 * Submit task for review (in_progress → review).
 */
function submitTaskForReview(int $taskId, int $userId): array
{
    $task = getTaskById($taskId);
    if (!$task) return ['success' => false, 'message' => 'Tugas tidak ditemukan.'];
    if ((int)($task['assigned_to'] ?? 0) !== $userId) return ['success' => false, 'message' => 'Akses ditolak.'];
    if ($task['status'] !== 'in_progress') {
        return ['success' => false, 'message' => 'Tugas harus berstatus sedang dikerjakan untuk dikirim ke review.'];
    }
    if (empty($task['reviewer_id'])) {
        return ['success' => false, 'message' => 'Tugas ini tidak memiliki reviewer.'];
    }
    return changeTaskStatus($taskId, 'review', $userId);
}

/**
 * Self-assign a task from backlog.
 * Only allowed if task is open and has no assignee.
 */
function selfAssignTask(int $taskId, int $userId): array
{
    global $conn;

    $task = getTaskById($taskId);
    if (!$task) return ['success' => false, 'message' => 'Tugas tidak ditemukan.'];

    if ($task['status'] !== 'open') {
        return ['success' => false, 'message' => 'Hanya tugas dengan status open yang bisa diambil.'];
    }

    // Validate user is member of the project
    $projectId = (int)$task['project_id'];
    $check = validateAssignee($projectId, $userId);
    if (!$check['valid']) return ['success' => false, 'message' => 'Anda bukan anggota project ini.'];

    $stmt = $conn->prepare('UPDATE tasks SET assigned_to=? WHERE id=?');
    $stmt->bind_param('ii', $userId, $taskId);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok
        ? ['success' => true, 'message' => 'Tugas berhasil diambil.']
        : ['success' => false, 'message' => 'Gagal mengambil tugas.'];
}

/**
 * Update assignee and reviewer from the task detail page.
 * Any active project member can configure the assignment; the selected users
 * themselves must still be active members (or the project owner).
 */
function updateTaskAssignment(int $taskId, int $assignedTo, int $reviewerId, int $userId): array
{
    global $conn;

    $task = getTaskById($taskId);
    if (!$task) return ['success' => false, 'message' => 'Tugas tidak ditemukan.'];

    $projectId = (int)($task['project_id'] ?? 0);
    if (!$projectId || !canAccessProject($projectId)) {
        return ['success' => false, 'message' => 'Akses ditolak.'];
    }

    $assignedTo = $assignedTo > 0 ? $assignedTo : 0;
    $reviewerId = $reviewerId > 0 ? $reviewerId : 0;

    if ($assignedTo > 0) {
        $check = validateAssignee($projectId, $assignedTo);
        if (!$check['valid']) return ['success' => false, 'message' => $check['message']];
    }
    if ($reviewerId > 0) {
        $check = validateAssignee($projectId, $reviewerId);
        if (!$check['valid']) return ['success' => false, 'message' => 'Reviewer bukan anggota project ini.'];
    }
    if ($assignedTo > 0 && $assignedTo === $reviewerId) {
        return ['success' => false, 'message' => 'Penanggung jawab dan reviewer harus berbeda.'];
    }

    $stmt = $conn->prepare(
        'UPDATE tasks
         SET assigned_to = NULLIF(?, 0), reviewer_id = NULLIF(?, 0)
         WHERE id = ? AND project_id = ?'
    );
    $stmt->bind_param('iiii', $assignedTo, $reviewerId, $taskId, $projectId);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok
        ? ['success' => true, 'message' => 'Penanggung jawab dan reviewer berhasil diperbarui.']
        : ['success' => false, 'message' => 'Gagal memperbarui penanggung jawab dan reviewer.'];
}

// ──────────────────────────────────────────────────────────────────
// Query helpers
// ──────────────────────────────────────────────────────────────────

/**
 * Get backlog: all open tasks in a project.
 */
function getBacklogTasks(int $projectId): array
{
    global $conn;
    $stmt = $conn->prepare(
        "SELECT t.*, u.username AS assignee_name, c.username AS creator_name,
                rv.username AS reviewer_name
         FROM tasks t
         LEFT JOIN users u  ON u.id  = t.assigned_to
         LEFT JOIN users c  ON c.id  = t.user_id
         LEFT JOIN users rv ON rv.id = t.reviewer_id
         WHERE t.project_id = ? AND t.status = 'open'
         ORDER BY t.deadline ASC, t.id ASC"
    );
    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $rows = [];
    $res  = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $rows[] = $row;
    $res->free();
    $stmt->close();
    return $rows;
}

/**
 * Get tasks for a project with filters.
 */
function getProjectTasks(int $projectId, array $filters = []): array
{
    global $conn;

    $where  = ['t.project_id = ?'];
    $types  = 'i';
    $params = [$projectId];

    foreach (['status','priority','label'] as $f) {
        if (!empty($filters[$f])) {
            $where[]  = "t.{$f} = ?";
            $types   .= 's';
            $params[] = $filters[$f];
        }
    }
    if (!empty($filters['assigned_to'])) {
        $where[]  = 't.assigned_to = ?';
        $types   .= 'i';
        $params[] = (int)$filters['assigned_to'];
    }
    if (!empty($filters['reviewer_id'])) {
        $where[]  = 't.reviewer_id = ?';
        $types   .= 'i';
        $params[] = (int)$filters['reviewer_id'];
    }
    if (!empty($filters['search'])) {
        $where[]  = '(t.title LIKE ? OR t.description LIKE ?)';
        $types   .= 'ss';
        $s        = '%' . $filters['search'] . '%';
        $params[] = $s;
        $params[] = $s;
    }

    $sql = "SELECT t.*, u.username AS assignee_name, c.username AS creator_name,
                   rv.username AS reviewer_name
            FROM tasks t
            LEFT JOIN users u  ON u.id  = t.assigned_to
            LEFT JOIN users c  ON c.id  = t.user_id
            LEFT JOIN users rv ON rv.id = t.reviewer_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY FIELD(t.status,'review','revision','in_progress','open','done'),
                     t.deadline ASC, t.id DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = [];
    $res  = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $rows[] = $row;
    $res->free();
    $stmt->close();
    return $rows;
}

/**
 * Get tasks assigned to a user across all projects.
 */
function getTasksAssignedToUser(int $userId): array
{
    global $conn;
    $stmt = $conn->prepare(
        "SELECT t.*, p.name AS project_name, u.username AS assignee_name
         FROM tasks t
         LEFT JOIN projects p ON p.id = t.project_id
         LEFT JOIN users u    ON u.id = t.assigned_to
         WHERE t.assigned_to = ?
         ORDER BY t.deadline ASC, t.status ASC, t.id DESC"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = [];
    $res  = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $rows[] = $row;
    $res->free();
    $stmt->close();
    return $rows;
}

/**
 * Get all tasks in review status (for admin review queue).
 */
function getReviewQueueTasks(): array
{
    global $conn;
    $result = $conn->query(
        "SELECT t.*, p.name AS project_name, u.username AS assignee_name,
                rv.username AS reviewer_name
         FROM tasks t
         LEFT JOIN projects p ON p.id = t.project_id
         LEFT JOIN users u    ON u.id = t.assigned_to
         LEFT JOIN users rv   ON rv.id = t.reviewer_id
         WHERE t.status = 'review'
         ORDER BY t.deadline ASC, t.id ASC"
    );
    $rows = [];
    if ($result) { while ($row = $result->fetch_assoc()) $rows[] = $row; }
    return $rows;
}

/**
 * Release a task (remove assignee).
 * Allowed for: assignee themselves, project owner, or admin.
 */
function releaseTask(int $taskId, int $userId): array
{
    global $conn;

    $task = getTaskById($taskId);
    if (!$task) return ['success' => false, 'message' => 'Tugas tidak ditemukan.'];

    $projectId = (int)$task['project_id'];
    $assigneeId = (int)($task['assigned_to'] ?? 0);

    // Check permissions: assignee themselves, project owner, or admin
    if ($assigneeId !== $userId && !isProjectOwner($projectId, $userId)) {
        return ['success' => false, 'message' => 'Akses ditolak. Hanya assignee atau project owner yang dapat melepas tugas.'];
    }

    if (!$assigneeId) {
        return ['success' => false, 'message' => 'Tugas belum di-assign ke siapapun.'];
    }

    $stmt = $conn->prepare('UPDATE tasks SET assigned_to = NULL WHERE id = ?');
    $stmt->bind_param('i', $taskId);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok
        ? ['success' => true, 'message' => 'Tugas berhasil dilepas dari assignee.']
        : ['success' => false, 'message' => 'Gagal melepas tugas.'];
}

/**
 * Reassign a task to a different user.
 * Allowed for: project owner, admin, or current assignee.
 */
function reassignTask(int $taskId, int $newAssigneeId, int $userId): array
{
    global $conn;

    $task = getTaskById($taskId);
    if (!$task) return ['success' => false, 'message' => 'Tugas tidak ditemukan.'];

    $projectId = (int)$task['project_id'];
    $currentAssigneeId = (int)($task['assigned_to'] ?? 0);

    // Check permissions: current assignee, project owner, or admin
    if ($currentAssigneeId !== $userId && !isProjectOwner($projectId, $userId)) {
        return ['success' => false, 'message' => 'Akses ditolak. Hanya assignee atau project owner yang dapat mengganti tugas.'];
    }

    // Validate new assignee
    if ($newAssigneeId > 0) {
        $check = validateAssignee($projectId, $newAssigneeId);
        if (!$check['valid']) return ['success' => false, 'message' => $check['message']];
    }

    $stmt = $conn->prepare('UPDATE tasks SET assigned_to = ? WHERE id = ?');
    $stmt->bind_param('ii', $newAssigneeId ?: null, $taskId);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        if ($newAssigneeId > 0) {
            // Get new assignee name for message
            $stmt = $conn->prepare('SELECT username FROM users WHERE id = ?');
            $stmt->bind_param('i', $newAssigneeId);
            $stmt->execute();
            $result = $stmt->get_result();
            $newUser = $result->fetch_assoc();
            $stmt->close();
            
            $message = 'Tugas berhasil di-assign ke ' . ($newUser['username'] ?? 'user');
        } else {
            $message = 'Tugas berhasil dilepas dari assignee.';
        }
        return ['success' => true, 'message' => $message];
    }

    return ['success' => false, 'message' => 'Gagal mengganti tugas.'];
}

/**
 * Get all tasks for a specific project (alias for getProjectTasks for backlog compatibility).
 */
function getTasksByProject(int $projectId): array
{
    return getProjectTasks($projectId);
}

/**
 * Assign tugas to user with proper validation.
 */
function assignTask(int $taskId, int $userId): array
{
    global $conn;

    $task = getTaskById($taskId);
    if (!$task) return ['success' => false, 'message' => 'Tugas tidak ditemukan.'];

    $projectId = (int)$task['project_id'];
    $check = validateAssignee($projectId, $userId);
    if (!$check['valid']) return ['success' => false, 'message' => $check['message']];

    $stmt = $conn->prepare('UPDATE tasks SET assigned_to=? WHERE id=?');
    $stmt->bind_param('ii', $userId, $taskId);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok
        ? ['success' => true, 'message' => 'Tugas berhasil diassign.']
        : ['success' => false, 'message' => 'Gagal assign tugas.'];
}

/**
 * Update task status using changeTaskStatus (alias for consistency).
 */
function updateTaskStatus(int $taskId, string $status, int $userId): array
{
    return changeTaskStatus($taskId, $status, $userId);
}

/**
 * Validate assignee is an active member of the project or project owner.
 */
function validateAssignee(int $projectId, int $userId): array
{
    global $conn;
    
    // Check if user is project owner
    if (isProjectOwner($projectId, $userId)) {
        // Verify owner is active
        $stmt = $conn->prepare("SELECT status FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$row || $row['status'] !== 'active') {
            return ['valid' => false, 'message' => 'User tidak aktif.'];
        }
        return ['valid' => true];
    }
    
    // Check if user is project member
    $stmt = $conn->prepare(
        "SELECT u.status FROM project_members pm
         JOIN users u ON u.id = pm.user_id
         WHERE pm.project_id = ? AND pm.user_id = ? LIMIT 1"
    );
    $stmt->bind_param('ii', $projectId, $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) return ['valid' => false, 'message' => 'User bukan anggota project ini.'];
    if ($row['status'] !== 'active') return ['valid' => false, 'message' => 'User tidak aktif.'];
    return ['valid' => true];
}
