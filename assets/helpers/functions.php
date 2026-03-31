<?php
session_start();

//DB Connection
$host = "localhost";
$username = "root";
$password = "root";
$dbname = "todo_list";

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function cleanInput($input)
{
    global $conn;

    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input);

    $input = $conn->real_escape_string($input);

    return $input;
}

function signin($username, $password)
{
    global $conn;

    $username = cleanInput($username);
    $password = hash('sha256', cleanInput($password));

    $sql = "SELECT users.*, roles.name AS role_name
            FROM users
            LEFT JOIN roles ON roles.id = users.role_id
            WHERE users.username = '$username' AND users.password = '$password'";

    return $conn->query($sql);
}

function signup($username, $password)
{
    global $conn;

    $username = cleanInput($username);
    $password = hash('sha256', cleanInput($password));

    $sql = "INSERT INTO users (username, password) VALUES ('$username', '$password')";
    $result = false;
    try {
        $result = $conn->query($sql);
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() == 1062) { // Duplicate entry
            return 'duplicate';
        }
        return false;
    }
    return $result;
}

function checkLogin($path)
{
    if (!isset($_SESSION['user'])) {
        header('Location: ' . url($path));
        exit;
    }
}

function isAdmin()
{
    if (!isset($_SESSION['user'])) {
        return false;
    }

    return (($_SESSION['user']['role_name'] ?? '') === 'admin') || ((int) ($_SESSION['user']['role_id'] ?? 0) === 1);
}

function checkAdmin($fallbackPath = 'dashboard')
{
    checkLogin('auth/signin');

    if (!isAdmin()) {
        header('Location: ' . url($fallbackPath));
        exit;
    }
}

function getTasks()
{
    global $conn;
    $user_id = $_SESSION['user']['id'];

    $sql = "SELECT * FROM tasks WHERE user_id = $user_id";

    $result = $conn->query($sql);

    $tasks = [];

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $tasks[] = $row;
        }
    }

    return $tasks;
}

function getAllTasksWithUsers()
{
    global $conn;

    $sql = "SELECT tasks.*, users.username
            FROM tasks
            INNER JOIN users ON users.id = tasks.user_id
            ORDER BY tasks.id DESC";

    $result = $conn->query($sql);
    $tasks = [];

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $tasks[] = $row;
        }
    }

    return $tasks;
}

function getAllUsersWithRole()
{
    global $conn;

    $sql = "SELECT users.id, users.username, roles.name AS role_name
            FROM users
            LEFT JOIN roles ON roles.id = users.role_id
            ORDER BY users.id DESC";

    $result = $conn->query($sql);
    $users = [];

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }

    return $users;
}

function deleteFile($file)
{
    if (empty($file) || $file === '.' || $file === '..') {
        return;
    }

    $filePath = __DIR__ . "/../../assets/public/" . $file;
    if (file_exists($filePath)) {
        unlink($filePath);
    }
}

function deleteTask($id)
{
    global $conn;
    $sql = "SELECT attachment FROM tasks WHERE id = $id AND user_id = " . $_SESSION['user']['id'];
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $file = $result->fetch_assoc()['attachment'];
        deleteFile($file);
    }

    $sql = "DELETE FROM tasks WHERE id = $id AND user_id = " . $_SESSION['user']['id'];

    return $conn->query($sql);
}

function saveFile($file)
{
    // No file selected in form
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    // Any upload error besides no-file should fail the request
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    $target_dir = __DIR__ . "/../../assets/public/";
    $baseName = basename(time() . "_" . $file['name']);
    $target_file = $target_dir . $baseName;

    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return $baseName;
    } else {
        return false;
    }
}

function addTask($title, $description, $deadline, $attachment, $status)
{
    global $conn;
    $user_id = (int) $_SESSION['user']['id'];

    $title = cleanInput($title);
    $description = cleanInput($description);
    $deadlineTs = strtotime($deadline);
    if ($deadlineTs === false) {
        return false;
    }
    $deadline = date('Y-m-d', $deadlineTs);

    $attachment = saveFile($attachment);
    $status = cleanInput($status);

    if ($attachment === false) {
        return false;
    }

    if ($attachment === null) {
        $attachment = '';
    }

    $sql = "INSERT INTO tasks (title, description, deadline, attachment, status, user_id) VALUES ('$title', '$description', '$deadline', '$attachment', '$status', $user_id)";
    $result = $conn->query($sql);

    if (!$result) {
        error_log('addTask failed: ' . $conn->error . ' | SQL: ' . $sql);
    }

    return $result;
}

function updateTask($id, $title, $description, $deadline, $attachment, $status, $replaceAttachment = false)
{
    global $conn;
    $id = (int) $id;
    $userId = (int) $_SESSION['user']['id'];

    $sql = "SELECT attachment FROM tasks WHERE id = $id AND user_id = $userId";
    $result = $conn->query($sql);

    if (!$result || $result->num_rows === 0) {
        error_log("updateTask failed: task not found for id=$id user_id=$userId");
        return false;
    }

    $file = $result->fetch_assoc()['attachment'];

    $title = cleanInput($title);
    $description = cleanInput($description);
    $deadline = $deadline;
    //change deadline to format yyyy-mm-dd
    $deadline = date('Y-m-d', strtotime($deadline));
    $status = cleanInput($status);

    $hasNewUpload = $replaceAttachment
        && isset($attachment['error'], $attachment['name'], $attachment['tmp_name'])
        && $attachment['error'] === UPLOAD_ERR_OK
        && $attachment['name'] !== ''
        && $attachment['tmp_name'] !== ''
        && is_uploaded_file($attachment['tmp_name']);

    if ($hasNewUpload) {
        $newAttachment = saveFile($attachment);
        if ($newAttachment === false) {
            error_log("updateTask failed: upload save failed for id=$id user_id=$userId");
            return false;
        }

        $sql = "UPDATE tasks SET title = '$title', description = '$description', deadline = '$deadline', attachment = '$newAttachment', status = '$status' WHERE id = $id AND user_id = $userId";
        $updated = $conn->query($sql);

        if (!$updated) {
            error_log('updateTask failed: ' . $conn->error . ' | SQL: ' . $sql);
            return false;
        }

        if (!empty($file)) {
            deleteFile($file);
        }

        return true;
    } else {
        // Keep existing attachment untouched when no new file is uploaded.
        $sql = "UPDATE tasks SET title = '$title', description = '$description', deadline = '$deadline', status = '$status' WHERE id = $id AND user_id = $userId";
        $updated = $conn->query($sql);

        if (!$updated) {
            error_log('updateTask failed: ' . $conn->error . ' | SQL: ' . $sql);
        }

        return $updated;
    }
}
