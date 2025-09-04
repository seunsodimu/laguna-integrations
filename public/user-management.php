<?php
/**
 * User Management Page (Admin Only)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Middleware\AuthMiddleware;
use Laguna\Integration\Services\AuthService;
use Laguna\Integration\Utils\UrlHelper;

$auth = new AuthMiddleware();
$authService = new AuthService();

// Require admin access
$currentUser = $auth->requireAdmin();
if (!$currentUser) {
    exit; // Middleware handles redirect
}

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_user':
            $userData = [
                'username' => trim($_POST['username'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'password' => $_POST['password'] ?? '',
                'role' => $_POST['role'] ?? 'user',
                'first_name' => trim($_POST['first_name'] ?? ''),
                'last_name' => trim($_POST['last_name'] ?? '')
            ];
            
            $result = $authService->createUser($userData, $currentUser['id']);
            
            if ($result['success']) {
                $success = 'User created successfully!';
            } else {
                $error = $result['error'];
            }
            break;
            
        case 'update_user':
            $userId = (int)($_POST['user_id'] ?? 0);
            $userData = [
                'email' => trim($_POST['email'] ?? ''),
                'role' => $_POST['role'] ?? 'user',
                'first_name' => trim($_POST['first_name'] ?? ''),
                'last_name' => trim($_POST['last_name'] ?? ''),
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];
            
            $result = $authService->updateUser($userId, $userData, $currentUser['id']);
            
            if ($result['success']) {
                $success = 'User updated successfully!';
            } else {
                $error = $result['error'];
            }
            break;
    }
}

// Get all users
$users = $authService->getAllUsers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - 3DCart NetSuite Integration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .navbar-brand {
            font-weight: bold;
        }
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .table th {
            border-top: none;
            font-weight: 600;
            color: #495057;
        }
        .badge {
            font-size: 0.75em;
        }
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="<?php echo UrlHelper::url('index.php'); ?>">
                <i class="fas fa-sync-alt me-2"></i>
                3DCart NetSuite Integration
            </a>
            
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user me-1"></i>
                        <?= htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?php echo UrlHelper::url('index.php'); ?>">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?php echo UrlHelper::url('logout.php'); ?>">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-users me-2"></i>User Management</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                        <i class="fas fa-plus me-2"></i>Create New User
                    </button>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?= htmlspecialchars($success) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Users Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Name</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Last Login</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?= $user['id'] ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($user['username']) ?></strong>
                                            </td>
                                            <td><?= htmlspecialchars($user['email']) ?></td>
                                            <td><?= htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $user['role'] === 'admin' ? 'danger' : 'primary' ?>">
                                                    <?= ucfirst($user['role']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $statusClass = 'secondary';
                                                $statusIcon = 'question';
                                                
                                                switch ($user['status']) {
                                                    case 'active':
                                                        $statusClass = 'success';
                                                        $statusIcon = 'check';
                                                        break;
                                                    case 'inactive':
                                                        $statusClass = 'secondary';
                                                        $statusIcon = 'times';
                                                        break;
                                                    case 'locked':
                                                        $statusClass = 'warning';
                                                        $statusIcon = 'lock';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge bg-<?= $statusClass ?>">
                                                    <i class="fas fa-<?= $statusIcon ?> me-1"></i>
                                                    <?= ucfirst($user['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($user['last_login']): ?>
                                                    <small><?= date('M j, Y g:i A', strtotime($user['last_login'])) ?></small>
                                                <?php else: ?>
                                                    <small class="text-muted">Never</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        onclick="editUser(<?= htmlspecialchars(json_encode($user)) ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="create_user">
                    <div class="modal-header">
                        <h5 class="modal-title">Create New User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username *</label>
                                    <input type="text" class="form-control" name="username" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" name="email" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="first_name" class="form-label">First Name</label>
                                    <input type="text" class="form-control" name="first_name">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="last_name" class="form-label">Last Name</label>
                                    <input type="text" class="form-control" name="last_name">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password *</label>
                                    <input type="password" class="form-control" name="password" required>
                                    <div class="form-text">Minimum 8 characters</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="role" class="form-label">Role *</label>
                                    <select class="form-select" name="role" required>
                                        <option value="user">User</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" id="edit_username" readonly>
                                    <div class="form-text">Username cannot be changed</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" name="email" id="edit_email" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_first_name" class="form-label">First Name</label>
                                    <input type="text" class="form-control" name="first_name" id="edit_first_name">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_last_name" class="form-label">Last Name</label>
                                    <input type="text" class="form-control" name="last_name" id="edit_last_name">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_role" class="form-label">Role *</label>
                                    <select class="form-select" name="role" id="edit_role" required>
                                        <option value="user">User</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active">
                                        <label class="form-check-label" for="edit_is_active">
                                            Account is active
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editUser(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_first_name').value = user.first_name || '';
            document.getElementById('edit_last_name').value = user.last_name || '';
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_is_active').checked = user.is_active == 1;
            
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        }
    </script>
</body>
</html>