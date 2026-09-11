<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/Auth.php';

Auth::startSession();
Security::secureHeaders();

if (Auth::isLoggedIn() && Auth::isAdmin()) {
    redirectTo('/admin/index.php');
}

$error = '';
if ($_POST) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_valid = true;
    if (isset($_POST['_csrf']) && !empty($_SESSION['csrf_tokens']['admin_login'])) {
        $csrf_valid = hash_equals($_SESSION['csrf_tokens']['admin_login']['token'], hash('sha256', $_POST['_csrf']));
    }

    if (!$csrf_valid) {
        $error = 'Invalid security token. Please try again.';
    } elseif (empty($username) || empty($password)) {
        $error = 'Username and password are required.';
    } else {
        if (Auth::login($username, $password)) {
            if (Auth::isAdmin()) {
                redirectTo('/admin/index.php');
            } else {
                $error = 'Admin access required.';
                Auth::logout();
            }
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
$loginToken = Security::generateToken('admin_login');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | <?= APP_NAME ?></title>
    <meta name="description" content="Admin login for <?= APP_NAME ?> streaming platform">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="admin-login-page">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">
                    <h1><?= APP_NAME ?></h1>
                    <span>Admin Panel</span>
                </div>
            </div>
<?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
            <form method="POST" action="/admin/login.php" class="login-form">
                <input type="hidden" name="_csrf" value="<?= $loginToken ?>">
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <input type="text" id="username" name="username" required autofocus class="form-control" autocomplete="username">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required class="form-control" autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-primary btn-full">Sign In</button>
            </form>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.login-form');
            form.addEventListener('submit', function() {
                const btn = form.querySelector('button[type="submit"]');
                btn.disabled = true;
                btn.textContent = 'Signing In...';
            });
        });
    </script>
</body>
</html>
