<?php

namespace Laguna\Integration\Utils;

/**
 * URL Helper Utility
 * 
 * Provides utility functions for generating correct URLs based on the project's location.
 */
class UrlHelper
{
    /**
     * Get the base URL for the project
     * 
     * @return string The base URL (e.g., '/laguna_3dcart_netsuite')
     */
    public static function getBaseUrl(): string
    {
        // Get the script name and extract the project directory
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        
        // If we're in the public directory, get the parent directory
        if (strpos($scriptName, '/public/') !== false) {
            $parts = explode('/public/', $scriptName);
            return $parts[0];
        }
        
        // Fallback: try to determine from document root and current directory
        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $currentDir = __DIR__;
        
        // Navigate up from src/Utils to project root
        $projectRoot = dirname(dirname($currentDir));
        
        if ($documentRoot && strpos($projectRoot, $documentRoot) === 0) {
            $relativePath = substr($projectRoot, strlen($documentRoot));
            return rtrim($relativePath, '/');
        }
        
        // Default fallback for development
        return '/laguna_3dcart_netsuite';
    }
    
    /**
     * Get the public URL for the project
     * 
     * @return string The public URL (e.g., '/laguna_3dcart_netsuite/public')
     */
    public static function getPublicUrl(): string
    {
        return self::getBaseUrl() . '/public';
    }
    
    /**
     * Generate a URL for a public page
     * 
     * @param string $page The page name (e.g., 'login.php', 'index.php')
     * @return string The complete URL
     */
    public static function url(string $page): string
    {
        return self::getPublicUrl() . '/' . ltrim($page, '/');
    }
    
    /**
     * Generate a URL for a project file (non-public)
     * 
     * @param string $path The relative path from project root
     * @return string The complete URL
     */
    public static function projectUrl(string $path): string
    {
        return self::getBaseUrl() . '/' . ltrim($path, '/');
    }
    
    /**
     * Get the current page URL
     * 
     * @return string The current page URL
     */
    public static function getCurrentUrl(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        
        return $protocol . $host . $uri;
    }
    
    /**
     * Redirect to a public page
     * 
     * @param string $page The page name
     * @param array $params Optional query parameters
     * @return void
     */
    public static function redirect(string $page, array $params = []): void
    {
        $url = self::url($page);
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        header('Location: ' . $url);
        exit;
    }
    
    /**
     * Check if we're running in a subdirectory
     * 
     * @return bool True if in subdirectory, false if in document root
     */
    public static function isSubdirectory(): bool
    {
        return self::getBaseUrl() !== '';
    }
}