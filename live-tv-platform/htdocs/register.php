<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/templates/ChannelCard.php';

Auth::startSession();
Security::secureHeaders();

if (Auth::isLoggedIn()) {
    redirectTo('/');
}

$error = '';
if ($_POST) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!Security::validateToken($csrfToken, 'register')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($username) || empty($email) || empty($password)) {
            $error = 'All fields are required.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } else {
            $result = Auth::register($username, $email, $password);
            if ($result['success']) {
                $_SESSION['flash_success'] = 'Registration successful! Welcome!';
                redirectTo('/');
            } else {
                $error = implode(' ', $result['errors']);
            }
        }
    }
}
$registerToken = Security::generateToken('register');
?>
<?php require_once __DIR__ . '/templates/header.php'; ?>

<section class="auth-section">
    <div class="container">
        <div class="auth-wrapper">
            <div class="auth-card">
                <div class="auth-header">
                    <h1>Create Your Account</h1>
                    <p>Join our community and get access to AI-powered channel recommendations.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="/register.php" class="auth-form">
                    <input type="hidden" name="csrf_token" value="<?= $registerToken ?>">
                    <div class="form-group">
                        <label for="username">Username *</label>
                        <input type="text" id="username" name="username" required class="form-control" minlength="3" maxlength="50">
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" id="password" name="password" required class="form-control" minlength="8">
                        <small class="help-text">At least 8 characters</small>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password *</label>
                        <input type="password" id="confirm_password" name="confirm_password" required class="form-control">
                    </div>
                    <button type="submit" class="btn btn-primary btn-full">Create Account</button>
                </form>

                <div class="auth-divider">
                    <span>or</span>
                </div>

                <p class="text-center">
                    Already have an account?
                    <a href="/login.php" class="link-primary">Sign In</a>
                </p>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
