<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

Auth::startSession();
Security::secureHeaders();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'error' => 'POST method required']);
    exit;
}

$input = json_decode(file_get_contents('php://input') ?: '', true) ?: $_POST;
$action = $input['action'] ?? 'login';

$rateLimitKey = 'auth_' . $action . '_' . Security::getClientIp();
if (!Security::rateLimit($rateLimitKey, 10, 60)) {
    http_response_code(429);
    echo json_encode([
        'status' => 'error',
        'error' => 'Too many requests. Please try again later.',
        'retry_after' => 60,
    ]);
    exit;
}

switch ($action) {
    case 'register':
        $response = Auth::register(
            trim($input['username'] ?? ''),
            trim($input['email'] ?? ''),
            $input['password'] ?? ''
        );

        if ($response['success']) {
            Logger::log('info', 'User registered', [
                'username' => $input['username'],
                'ip' => Security::getClientIp(),
            ]);
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'user_id' => $response['user_id'],
                    'message' => 'Registration successful.',
                    'redirect' => '/index.php',
                ],
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'error' => implode(' ', $response['errors']),
                'errors' => $response['errors'],
            ]);
        }
        break;

    case 'login':
        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';
        $remember = (bool) ($input['remember'] ?? false);

        if (empty($username) || empty($password)) {
            echo json_encode([
                'status' => 'error',
                'error' => 'Username and password are required.',
            ]);
            exit;
        }

        $loginResult = Auth::login($username, $password, $remember);

        if ($loginResult) {
            $user = Auth::getCurrentUser();
            Logger::log('info', 'User logged in', [
                'username' => $user['username'],
                'ip' => Security::getClientIp(),
            ]);
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'email' => $user['email'],
                        'role' => $user['role'],
                    ],
                    'redirect' => '/index.php',
                ],
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'error' => 'Invalid username or password.',
            ]);
        }
        break;

    case 'logout':
        Auth::logout();
        echo json_encode([
            'status' => 'success',
            'data' => ['redirect' => '/login.php'],
        ]);
        break;

    case 'check':
        if (Auth::isLoggedIn()) {
            $user = Auth::getCurrentUser();
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'authenticated' => true,
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'email' => $user['email'],
                        'role' => $user['role'],
                    ],
                ],
            ]);
        } else {
            echo json_encode([
                'status' => 'success',
                'data' => ['authenticated' => false],
            ]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'error' => 'Unknown auth action']);
}
