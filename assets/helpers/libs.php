<?php
// helpers.php

/**
 * Checks if the 'assets' function does not already exist, and if not, defines it.
 * 
 * @param string $path The path to the asset file.
 * @return string The full URL to the asset file.
 */
if (!function_exists('assets')) {
    function assets($path)
    {
        return appBasePath() . '/assets/' . ltrim($path, '/');
    }
}

/**
 * Resolve the app base path dynamically (e.g. /todolist).
 *
 * @return string
 */
if (!function_exists('appBasePath')) {
    function appBasePath()
    {
        static $basePath = null;

        if ($basePath !== null) {
            return $basePath;
        }

        $projectRoot = realpath(__DIR__ . '/../../');
        $documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;

        if ($projectRoot && $documentRoot && strpos($projectRoot, $documentRoot) === 0) {
            $relativePath = trim(substr($projectRoot, strlen($documentRoot)), '/');
            $basePath = $relativePath === '' ? '' : '/' . $relativePath;
            return $basePath;
        }

        $basePath = '';
        return $basePath;
    }
}

/**
 * Checks if the 'components' function does not already exist, and if not, defines it.
 * 
 * @param string $component The name of the component.
 * @return string The full file path to the component's index.php file.
 */
if (!function_exists('components')) {
    function components($component)
    {
        return __DIR__ . '/../../components/' . $component . '/index.php';
    }
}

/**
 * Checks if the 'url' function does not already exist, and if not, defines it.
 * 
 * @param string $path The path to the URL.
 * @return string The full URL to the path.
 */
if (!function_exists('url')) {
    function url($path = '')
    {
        $path = ltrim($path, '/');
        return appBasePath() . ($path === '' ? '' : '/' . $path);
    }
}

/**
 * Generate full URL with domain for sharing (e.g., invitations).
 */
if (!function_exists('fullUrl')) {
    function fullUrl($path = '')
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $path = ltrim($path, '/');
        return $protocol . $host . appBasePath() . ($path === '' ? '' : '/' . $path);
    }
}

/**
 * Generate URL-friendly slug from text.
 */
if (!function_exists('slugify')) {
    function slugify($text)
    {
        // Replace non-alphanumeric characters with dashes
        $slug = preg_replace('/[^a-zA-Z0-9]+/', '-', trim($text));
        // Remove leading/trailing dashes and convert to lowercase
        $slug = trim(strtolower($slug), '-');
        // Remove multiple consecutive dashes
        $slug = preg_replace('/-+/', '-', $slug);
        
        return $slug;
    }
}

/**
 * Get project by slug (for pretty URLs).
 */
if (!function_exists('getProjectBySlug')) {
    function getProjectBySlug($slug)
    {
        global $conn;
        
        // First try to find by exact slug match if we stored slugs
        // For now, we'll search by name pattern matching
        $searchName = str_replace('-', ' ', $slug);
        
        $stmt = $conn->prepare(
            "SELECT p.*, u.username AS owner_name
             FROM projects p
             LEFT JOIN users u ON u.id = p.owner_id
             WHERE LOWER(REPLACE(p.name, ' ', '-')) = ? OR LOWER(p.name) LIKE ?
             LIMIT 1"
        );
        $likePattern = '%' . $searchName . '%';
        $stmt->bind_param('ss', $slug, $likePattern);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        return $row ?: null;
    }
}
