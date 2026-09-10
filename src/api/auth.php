<?php
/**
 * HDHome Live TV - Auth API endpoints.
 *
 * GET    auth/csrf    -> current CSRF token
 * POST   auth/login   -> login with username/password
 * POST   auth/logout  -> end session
 * GET    auth/me      -> current session info
 */

declare(strict_types=1);

switch ($method) {

    case 'GET':
        if ($action === 'csrf') {
            success(['csrf_token' => Auth::csrfToken()]);
        }
        if ($action === 'me' || $action === 'check') {
            if (!Auth::isLoggedIn()) {
                error('Not logged in.', 401, 'UNAUTHORIZED');
            }
            success(['user' => Auth::user(), 'logged_in' => true]);
        }
        error('Unknown auth action.', 404, 'NOT_FOUND');

    case 'POST':
        if ($action === 'login') {
            RateLimiter::guard('auth:login', 8, 60);

            $username = (string) input('username', '');
            $password = (string) input('password', '');

            if ($username === '' || $password === '') {
                error('Username and password are required.', 400, 'VALIDATION_ERROR');
            }

            if (Auth::login($username, $password)) {
                Auth::checkCsrf(); // verify token if provided (enforce on login)
                success([
                    'user' => $username,
                    'message' => 'Login successful.',
                ]);
            }

            error('Invalid credentials.', 401, 'INVALID_CREDENTIALS');
        }

        if ($action === 'logout') {
            Auth::logout();
            success(['message' => 'Logged out.']);
        }

        error('Unknown auth action.', 404, 'NOT_FOUND');

    default:
        error('Method not allowed.', 405, 'METHOD_NOT_ALLOWED');
}
