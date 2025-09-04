<?php
/**
 * Debug Script for 403 Error Troubleshooting
 * 
 * This script helps diagnose common issues causing 403 errors
 */

echo "<h1>403 Error Diagnostic Tool</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .ok{color:green;} .error{color:red;} .warning{color:orange;}</style>";

echo "<h2>Basic Information</h2>";
echo "<strong>Current Directory:</strong> " . __DIR__ . "<br>";
echo "<strong>Document Root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "<strong>Request URI:</strong> " . $_SERVER['REQUEST_URI'] . "<br>";
echo "<strong>Script Name:</strong> " . $_SERVER['SCRIPT_NAME'] . "<br>";
echo "<strong>PHP Version:</strong> " . PHP_VERSION . "<br>";

echo "<h2>File Permissions Check</h2>";

$files_to_check = [
    __DIR__ . '/public/index.php',
    __DIR__ . '/public/status.php',
    __DIR__ . '/public/webhook.php',
    __DIR__ . '/public/upload.php',
    __DIR__ . '/config/config.php',
    __DIR__ . '/config/credentials.php',
    __DIR__ . '/vendor/autoload.php'
];

foreach ($files_to_check as $file) {
    $exists = file_exists($file);
    $readable = is_readable($file);
    $perms = $exists ? substr(sprintf('%o', fileperms($file)), -4) : 'N/A';
    
    $status = $exists && $readable ? 'ok' : 'error';
    echo "<div class='$status'>";
    echo "<strong>" . basename($file) . ":</strong> ";
    echo "Exists: " . ($exists ? 'Yes' : 'No') . " | ";
    echo "Readable: " . ($readable ? 'Yes' : 'No') . " | ";
    echo "Permissions: $perms";
    echo "</div>";
}

echo "<h2>Directory Permissions Check</h2>";

$dirs_to_check = [
    __DIR__ . '/public',
    __DIR__ . '/config',
    __DIR__ . '/vendor',
    __DIR__ . '/logs',
    __DIR__ . '/uploads'
];

foreach ($dirs_to_check as $dir) {
    $exists = is_dir($dir);
    $readable = is_readable($dir);
    $writable = is_writable($dir);
    $perms = $exists ? substr(sprintf('%o', fileperms($dir)), -4) : 'N/A';
    
    $status = $exists && $readable ? 'ok' : 'error';
    echo "<div class='$status'>";
    echo "<strong>" . basename($dir) . "/:</strong> ";
    echo "Exists: " . ($exists ? 'Yes' : 'No') . " | ";
    echo "Readable: " . ($readable ? 'Yes' : 'No') . " | ";
    echo "Writable: " . ($writable ? 'Yes' : 'No') . " | ";
    echo "Permissions: $perms";
    echo "</div>";
}

echo "<h2>Apache Modules Check</h2>";

if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    $required_modules = ['mod_rewrite', 'mod_headers'];
    
    foreach ($required_modules as $module) {
        $loaded = in_array($module, $modules);
        $status = $loaded ? 'ok' : 'error';
        echo "<div class='$status'><strong>$module:</strong> " . ($loaded ? 'Loaded' : 'Not Loaded') . "</div>";
    }
} else {
    echo "<div class='warning'>Cannot check Apache modules (not running under Apache or function not available)</div>";
}

echo "<h2>.htaccess Files Check</h2>";

$htaccess_files = [
    __DIR__ . '/.htaccess',
    __DIR__ . '/public/.htaccess'
];

foreach ($htaccess_files as $file) {
    $exists = file_exists($file);
    $readable = is_readable($file);
    
    echo "<div class='" . ($exists && $readable ? 'ok' : 'error') . "'>";
    echo "<strong>" . str_replace(__DIR__, '', $file) . ":</strong> ";
    echo "Exists: " . ($exists ? 'Yes' : 'No') . " | ";
    echo "Readable: " . ($readable ? 'Yes' : 'No');
    echo "</div>";
    
    if ($exists && $readable) {
        echo "<details><summary>View content</summary><pre>" . htmlspecialchars(file_get_contents($file)) . "</pre></details>";
    }
}

echo "<h2>PHP Configuration</h2>";

$php_settings = [
    'allow_url_fopen',
    'file_uploads',
    'max_execution_time',
    'memory_limit',
    'upload_max_filesize',
    'post_max_size'
];

foreach ($php_settings as $setting) {
    $value = ini_get($setting);
    echo "<strong>$setting:</strong> $value<br>";
}

echo "<h2>Test Basic PHP Functionality</h2>";

try {
    echo "<div class='ok'><strong>Basic PHP:</strong> Working</div>";
    
    // Test autoloader
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        require_once __DIR__ . '/vendor/autoload.php';
        echo "<div class='ok'><strong>Autoloader:</strong> Loaded successfully</div>";
    } else {
        echo "<div class='error'><strong>Autoloader:</strong> vendor/autoload.php not found</div>";
    }
    
    // Test config
    if (file_exists(__DIR__ . '/config/config.php')) {
        $config = require __DIR__ . '/config/config.php';
        echo "<div class='ok'><strong>Config:</strong> Loaded successfully</div>";
    } else {
        echo "<div class='error'><strong>Config:</strong> config/config.php not found</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'><strong>PHP Error:</strong> " . $e->getMessage() . "</div>";
}

echo "<h2>Suggested Solutions</h2>";
echo "<ul>";
echo "<li><strong>If files don't exist:</strong> Make sure all files were created properly</li>";
echo "<li><strong>If permissions are wrong:</strong> Run <code>chmod 755</code> on directories and <code>chmod 644</code> on files</li>";
echo "<li><strong>If mod_rewrite is missing:</strong> Enable it in Apache configuration</li>";
echo "<li><strong>If .htaccess is causing issues:</strong> Temporarily rename it to test</li>";
echo "<li><strong>If running on Windows/XAMPP:</strong> Check that Apache is running and configured properly</li>";
echo "</ul>";

echo "<h2>Quick Tests</h2>";
echo "<p>Try these URLs:</p>";
echo "<ul>";
echo "<li><a href='debug.php'>debug.php</a> (this file)</li>";
echo "<li><a href='test.php'>test.php</a> (installation test)</li>";
echo "<li><a href='public/index.php'>public/index.php</a> (direct access)</li>";
echo "<li><a href='public/status.php'>public/status.php</a> (direct access)</li>";
echo "</ul>";
?>