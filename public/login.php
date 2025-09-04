<?php
/**
 * User Login Page
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Middleware\AuthMiddleware;
use Laguna\Integration\Utils\UrlHelper;

$auth = new AuthMiddleware();
$error = '';
$success = '';

// Check if already logged in
if ($auth->isAuthenticated()) {
    $redirectUrl = $_GET['redirect'] ?? UrlHelper::url('index.php');
    header('Location: ' . $redirectUrl);
    exit;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $rememberMe = isset($_POST['remember_me']);
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $result = $auth->login($username, $password, $rememberMe);
        
        if ($result['success']) {
            $redirectUrl = $_GET['redirect'] ?? UrlHelper::url('index.php');
            header('Location: ' . $redirectUrl);
            exit;
        } else {
            $error = $result['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - 3DCart NetSuite Integration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .login-body {
            padding: 2rem;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        .input-group-text {
            background: #f8f9fa;
            border-right: none;
        }
        .form-control {
            border-left: none;
        }
        .alert {
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <i class="fas fa-sync-alt fa-2x mb-3"></i>
            <h4 class="mb-0">3DCart NetSuite Integration</h4>
            <p class="mb-0 opacity-75">Please sign in to continue</p>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" class="form-control" id="username" name="username" 
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" 
                               placeholder="Enter your username" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="Enter your password" required>
                    </div>
                </div>
                
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
                    <label class="form-check-label" for="remember_me">
                        Remember me for 30 days
                    </label>
                </div>
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i>
                        Sign In
                    </button>
                </div>
            </form>
            
            <hr class="my-4">
            
            <div class="text-center">
                <small class="text-muted">
                    <i class="fas fa-shield-alt me-1"></i>
                    Secure login protected by encryption
                </small>
            </div>
            
            <div class="text-center mt-3">
                <small class="text-muted">
                    Default admin credentials:<br>
                    Username: <code>admin</code> | Password: <code>admin123</code><br>
                    <span class="text-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        Change default password after first login!
                    </span>
                </small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-focus username field
        document.getElementById('username').focus();
        
        // Add loading state to login button
        document.querySelector('form').addEventListener('submit', function() {
            const btn = document.querySelector('.btn-login');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Signing In...';
            btn.disabled = true;
        });
    </script>
</body>
</html>