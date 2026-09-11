<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
Auth::startSession();
Security::secureHeaders();

$errorRef = bin2hex(random_bytes(4));
Logger::log('error', '500 Internal Server Error displayed to user', [
    'ref' => $errorRef,
    'uri' => $_SERVER['REQUEST_URI'] ?? '',
    'ip' => Security::getClientIp(),
]);
?>
<?php require_once __DIR__ . '/templates/header.php'; ?>

<section class="auth-section">
    <div class="container">
        <div class="error-page">
            <div class="error-code">500</div>
            <h1>Internal Server Error</h1>
            <p>Something went wrong on our end. Our AI monitoring agent has been notified.</p>
            <p>Reference ID: <strong><?= $errorRef ?></strong></p>
            <a href="/" class="btn btn-primary">Go Home</a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
