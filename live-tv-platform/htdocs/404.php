<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
Auth::startSession();
Security::secureHeaders();
?>
<?php require_once __DIR__ . '/templates/header.php'; ?>

<section class="auth-section">
    <div class="container">
        <div class="error-page">
            <div class="error-code">404</div>
            <h1>Page Not Found</h1>
            <p>Sorry, the page you're looking for doesn't exist or has been moved.</p>
            <a href="/" class="btn btn-primary">Go Home</a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
