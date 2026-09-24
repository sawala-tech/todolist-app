<?php
// middleware.php

session_start();

// Check if the session exists
$sessionExists = isset($_SESSION['user']);

// Get the current URL path
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Check if the user is trying to access a route starting with "/auth"
if ($sessionExists && strpos($currentPath, '/auth') === 0) {
    // Redirect to the dashboard if the session exists and the user is trying to access an auth page
    header('Location: '.url('dashboard'));
    exit();
}

// Check if the user is trying to access a route starting with "/dashboard"
if (!$sessionExists && strpos($currentPath, '/dashboard') === 0) {
    // Redirect to the sign-in page if the session does not exist and the user is trying to access a dashboard page
    header('Location: '.url('auth/signin'));
    exit();
}
?>