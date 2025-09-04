<?php
/**
 * Authentication Protection Test
 * 
 * Tests that all pages requiring authentication are properly protected
 */

require_once __DIR__ . '/vendor/autoload.php';

echo "🔐 Authentication Protection Test\n";
echo "=================================\n\n";

// List of all public PHP files and their expected authentication status
$publicFiles = [
    // Should require authentication
    'index.php' => ['auth_required' => true, 'description' => 'Main Dashboard'],
    'status.php' => ['auth_required' => true, 'description' => 'Status Dashboard'],
    'upload.php' => ['auth_required' => true, 'description' => 'File Upload Interface'],
    'order-sync.php' => ['auth_required' => true, 'description' => 'Order Synchronization'],
    'user-management.php' => ['auth_required' => true, 'admin_only' => true, 'description' => 'User Management (Admin)'],
    'email-provider-config.php' => ['auth_required' => true, 'admin_only' => true, 'description' => 'Email Configuration (Admin)'],
    'test-email.php' => ['auth_required' => true, 'description' => 'Email Testing'],
    'webhook-settings.php' => ['auth_required' => true, 'description' => 'Webhook Settings'],
    'test-webhook.php' => ['auth_required' => true, 'description' => 'Webhook Testing'],
    'webhook-test.php' => ['auth_required' => true, 'description' => 'System Diagnostic'],
    'test-direct.php' => ['auth_required' => true, 'description' => 'Direct Access Test'],
    
    // Should NOT require authentication
    'login.php' => ['auth_required' => false, 'description' => 'Login Page'],
    'logout.php' => ['auth_required' => false, 'description' => 'Logout Handler'],
    'access-denied.php' => ['auth_required' => false, 'description' => 'Access Denied Page'],
    'webhook.php' => ['auth_required' => false, 'description' => 'Webhook API Endpoint']
];

echo "1. Checking file existence and authentication implementation...\n";
echo str_repeat('-', 70) . "\n";

$allProtected = true;
$protectedCount = 0;
$unprotectedCount = 0;

foreach ($publicFiles as $file => $config) {
    $filePath = __DIR__ . '/public/' . $file;
    $status = '';
    
    if (!file_exists($filePath)) {
        $status = "❌ FILE MISSING";
        $allProtected = false;
    } else {
        $content = file_get_contents($filePath);
        $hasAuthMiddleware = strpos($content, 'AuthMiddleware') !== false;
        $hasRequireAuth = strpos($content, 'requireAuth') !== false || strpos($content, 'requireAdmin') !== false;
        
        if ($config['auth_required']) {
            if ($hasAuthMiddleware && $hasRequireAuth) {
                $status = "✅ PROTECTED";
                $protectedCount++;
                
                // Check if admin-only pages use requireAdmin
                if (isset($config['admin_only']) && $config['admin_only']) {
                    if (strpos($content, 'requireAdmin') !== false) {
                        $status .= " (ADMIN)";
                    } else {
                        $status .= " ⚠️  (Should use requireAdmin)";
                    }
                }
            } else {
                $status = "❌ NOT PROTECTED";
                $allProtected = false;
            }
        } else {
            if (!$hasAuthMiddleware) {
                $status = "✅ PUBLIC (Correct)";
                $unprotectedCount++;
            } else {
                $status = "⚠️  HAS AUTH (May be unnecessary)";
            }
        }
    }
    
    printf("%-25s %-35s %s\n", $file, $config['description'], $status);
}

echo str_repeat('-', 70) . "\n";
echo "Protected pages: $protectedCount\n";
echo "Public pages: $unprotectedCount\n\n";

echo "2. Testing authentication middleware implementation...\n";
echo str_repeat('-', 70) . "\n";

// Check specific authentication patterns
$authPatterns = [
    'AuthMiddleware import' => 'use Laguna\Integration\Middleware\AuthMiddleware;',
    'Auth instance creation' => '$auth = new AuthMiddleware();',
    'Require auth call' => '$auth->requireAuth()',
    'Admin auth call' => '$auth->requireAdmin()',
    'Exit on auth failure' => 'exit; // Middleware handles redirect'
];

$protectedFiles = array_filter($publicFiles, function($config) {
    return $config['auth_required'];
});

foreach ($protectedFiles as $file => $config) {
    $filePath = __DIR__ . '/public/' . $file;
    if (file_exists($filePath)) {
        $content = file_get_contents($filePath);
        echo "\n$file:\n";
        
        foreach ($authPatterns as $pattern => $code) {
            if (strpos($content, $code) !== false) {
                echo "  ✅ $pattern\n";
            } else {
                if ($pattern === 'Admin auth call' && !isset($config['admin_only'])) {
                    continue; // Skip admin check for non-admin pages
                }
                echo "  ❌ Missing: $pattern\n";
                $allProtected = false;
            }
        }
    }
}

echo "\n3. Summary and Recommendations...\n";
echo str_repeat('-', 70) . "\n";

if ($allProtected) {
    echo "🎉 SUCCESS: All pages are properly protected!\n\n";
    
    echo "✅ Authentication Status:\n";
    echo "• All sensitive pages require authentication\n";
    echo "• Admin pages use proper admin-only protection\n";
    echo "• Public endpoints remain accessible\n";
    echo "• Authentication middleware properly implemented\n\n";
    
    echo "🔒 Security Features Active:\n";
    echo "• Login required for dashboard and management pages\n";
    echo "• Admin-only access for user management and configuration\n";
    echo "• Automatic redirect to login for unauthenticated users\n";
    echo "• Session-based authentication with proper validation\n\n";
    
} else {
    echo "⚠️  WARNING: Some pages may not be properly protected!\n\n";
    
    echo "🔧 Recommended Actions:\n";
    echo "• Review pages marked as 'NOT PROTECTED'\n";
    echo "• Ensure all sensitive pages have authentication\n";
    echo "• Test authentication flow manually\n";
    echo "• Verify admin-only pages use requireAdmin()\n\n";
}

echo "📋 Next Steps:\n";
echo "1. Test login functionality: /public/login.php\n";
echo "2. Verify protected pages redirect to login when not authenticated\n";
echo "3. Confirm admin pages are accessible only to admin users\n";
echo "4. Test that public endpoints (webhook.php) remain accessible\n\n";

echo "🚀 Authentication protection test completed!\n";
?>