<?php
/**
 * Schedule Enterprise — AuthMiddleware
 * ★ Guard for every authenticated page (Phase 3)
 */

namespace Schedule\Auth;

class AuthMiddleware
{
    private static string $loginUrl = 'login';

    /**
     * Require authenticated session — redirect to login if not.
     */
    public static function guard(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Session fixation protection — regenerate on first use
        if (empty($_SESSION['__init'])) {
            session_regenerate_id(true);
            $_SESSION['__init'] = true;
        }

        if (!self::isLoggedIn()) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
            header('Location: ' . self::$loginUrl);
            exit();
        }

        // Session lifetime check
        if (isset($_SESSION['last_activity'])) {
            $idle = time() - $_SESSION['last_activity'];
            if ($idle > SESSION_LIFETIME) {
                self::destroySession();
                header('Location: ' . self::$loginUrl . '?timeout=1');
                exit();
            }
        }
        $_SESSION['last_activity'] = time();
    }

    /**
     * Require admin role.
     */
    public static function requireAdmin(): void
    {
        self::guard();
        if (($_SESSION['role'] ?? '') !== 'admin') {
            http_response_code(403);
            exit('ไม่มีสิทธิ์เข้าถึงส่วนนี้');
        }
    }

    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['username']);
    }

    public static function username(): string
    {
        return $_SESSION['username'] ?? '';
    }

    public static function role(): string
    {
        return $_SESSION['role'] ?? 'user';
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }

    public static function destroySession(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']
            );
        }
        session_destroy();
    }

    // ── CSRF ──────────────────────────────────────────────────

    public static function csrfToken(): string
    {
        if (empty($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . self::csrfToken() . '">';
    }

    public static function verifyCsrf(): bool
    {
        $token = $_POST[CSRF_TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        return hash_equals($_SESSION[CSRF_TOKEN_NAME] ?? '', $token);
    }

    public static function assertCsrf(): void
    {
        if (!self::verifyCsrf()) {
            http_response_code(403);
            exit(json_encode(['error' => 'CSRF token mismatch']));
        }
    }

    // ── Device detection ─────────────────────────────────────

    public static function isMobile(): bool
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return (bool) preg_match('/(android|iphone|ipod|blackberry|windows phone)/i', $ua);
    }

    public static function isTablet(): bool
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return (bool) preg_match('/(ipad|tablet|playbook|silk)|(android(?!.*mobile))/i', $ua);
    }

    public static function isMobileOrTablet(): bool
    {
        return self::isMobile() || self::isTablet();
    }
}