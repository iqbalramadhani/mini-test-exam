<?php

/**
 * Security helper — CSRF protection, input validation, security headers.
 * No external dependencies required.
 */
class Security
{
    /**
     * Generate a CSRF token and store it in the session.
     * Returns the token string.
     */
    public static function generateToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Output a hidden CSRF token field for forms.
     */
    public static function tokenField(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . self::generateToken() . '">';
    }

    /**
     * Validate a CSRF token from request input.
     * Returns true if valid, false otherwise.
     */
    public static function validateToken(string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Invalidate the current CSRF token (call after successful form submission).
     */
    public static function invalidateToken(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['csrf_token']);
    }

    /**
     * Validate that a controller name is safe (no path traversal, no invalid chars).
     * Returns true if valid, false otherwise.
     */
    public static function isValidControllerName(string $name): bool
    {
        if (strlen($name) === 0) {
            return false;
        }

        // Reject path separators and dot-dot segments
        if (strpos($name, '/') !== false || strpos($name, '\\') !== false) {
            return false;
        }

        if (strpos($name, '..') !== false) {
            return false;
        }

        // Only allow alphanumeric, underscores, and hyphens
        return (bool) preg_match('/^[a-zA-Z_][a-zA-Z0-9_-]*$/', $name);
    }

    /**
     * Validate that an ID parameter is a positive integer.
     */
    public static function isValidId($id): bool
    {
        if ($id === null || $id === '') {
            return false;
        }

        // Cast to string then check digits only
        $str = (string) $id;
        return (bool) preg_match('/^\d+$/', $str);
    }

    /**
     * Sanitize a string for safe HTML output.
     * Alias for htmlspecialchars for convenience.
     */
    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Set standard security HTTP headers.
     */
    public static function setHeaders(): void
    {
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');

        if (defined('URL') && str_starts_with(URL, 'https')) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    /**
     * Validate that the request method matches expected.
     */
    public static function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    public static function isGet(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'GET';
    }

    /**
     * Check if user is logged in via session.
     */
    public static function isLoggedIn(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Get current logged-in user data from session.
     */
    public static function getUser(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'email' => $_SESSION['email'] ?? null,
            'role' => $_SESSION['role'] ?? 'user',
        ];
    }
}
