<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/templates/ChannelCard.php';

Auth::startSession();
Security::secureHeaders();

$error = '';
$success = '';

if (Auth::isLoggedIn()) {
    redirectTo('/');
}

if ($_POST) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!Security::validateToken($csrfToken, 'login')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (Auth::login($username, $password, (bool) ($_POST['remember'] ?? false))) {
            $redirect = $_GET['redirect'] ?? '/';
            redirectTo($redirect);
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
if ($_GET['registered'] ?? false) {
    $success = 'Registration successful! Please log in.';
}
$loginToken = Security::generateToken('login');
?>
<?php require_once __DIR__ . '/templates/header.php'; ?>

<section class="auth-section">
    <div class="container">
        <div class="auth-wrapper">
            <div class="auth-card">
                <div class="auth-header">
                    <h1>Sign In to Your Account</h1>
                    <p>Access your favorites, personalized recommendations, and AI assistant.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <form method="POST" action="/login.php" class="auth-form">
                    <input type="hidden" name="csrf_token" value="<?= $loginToken ?>">
                    <div class="form-group">
                        <label for="username">Username or Email *</label>
                        <input type="text" id="username" name="username" required class="form-control" autocomplete="username" autofocus>
                    </div>
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" id="password" name="password" required class="form-control" autocomplete="current-password">
                    </div>
                    <div class="form-group checkbox-row">
                        <label class="checkbox-label">
                            <input type="checkbox" name="remember" value="1"> Remember me
                        </label>
                        <a href="/forgot-password.php" class="link-secondary">Forgot password?</a>
                    </div>
                    <button type="submit" class="btn btn-primary btn-full">Sign In</button>
                </form>

                <div class="auth-divider">
                    <span>or</span>
                </div>

                <p class="text-center">
                    Don't have an account?
                    <a href="/register.php" class="link-primary">Create Account</a>
                </p>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
