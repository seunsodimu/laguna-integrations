<?php
/**
 * User Logout Page
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Middleware\AuthMiddleware;
use Laguna\Integration\Utils\UrlHelper;

$auth = new AuthMiddleware();
$auth->logout();

// Redirect to login page
UrlHelper::redirect('login.php', ['message' => 'logged_out']);
?>