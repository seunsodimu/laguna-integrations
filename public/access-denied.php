<?php
require_once __DIR__ . '/../vendor/autoload.php';
use Laguna\Integration\Utils\UrlHelper;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied - 3DCart NetSuite Integration</title>
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
        .error-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 3rem;
            text-align: center;
            max-width: 500px;
        }
        .error-icon {
            color: #dc3545;
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <i class="fas fa-ban error-icon"></i>
        <h1 class="h2 mb-3">Access Denied</h1>
        <p class="text-muted mb-4">
            You don't have permission to access this page. This area is restricted to administrators only.
        </p>
        
        <div class="d-grid gap-2 d-md-block">
            <a href="<?php echo UrlHelper::url('index.php'); ?>" class="btn btn-primary">
                <i class="fas fa-home me-2"></i>
                Go to Dashboard
            </a>
            <a href="<?php echo UrlHelper::url('logout.php'); ?>" class="btn btn-outline-secondary">
                <i class="fas fa-sign-out-alt me-2"></i>
                Logout
            </a>
        </div>
        
        <hr class="my-4">
        
        <small class="text-muted">
            If you believe this is an error, please contact your system administrator.
        </small>
    </div>
</body>
</html>