<?php
/**
 * pages/api/project/invite.php
 * 
 * API endpoint untuk generate project invitation link
 */

require_once __DIR__ . '/../../../assets/helpers/functions.php';
require_once __DIR__ . '/../../../assets/helpers/auth_helpers.php';
require_once __DIR__ . '/../../../assets/helpers/project_invitation_helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

requireActiveUser();

// Get project ID from URL path
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$matches = [];
if (!preg_match('#/api/project/(\d+)/invite#', $path, $matches)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid project ID']);
    exit;
}

$projectId = (int)$matches[1];
$userId = (int)$_SESSION['user']['id'];

// Verify user is project owner
if (!isProjectOwner($projectId, $userId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Hanya owner project yang bisa membuat invitation']);
    exit;
}

// Parse JSON body
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !hash_equals(csrfToken(), $input['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

try {
    // Create invitation
    $invitation = createProjectInvitation($projectId, $userId);
    $inviteUrl = getInvitationUrl($invitation['token']);
    
    echo json_encode([
        'success' => true,
        'url' => $inviteUrl,
        'code' => $invitation['code'],
        'expires_at' => $invitation['expires_at']
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal membuat invitation: ' . $e->getMessage()]);
}
?>