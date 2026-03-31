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
