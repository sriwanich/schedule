<?php
/**
 * Schedule Enterprise — AuthController
 * ★ Login / Logout / Session (Phase 3)
 */

namespace Schedule\Auth;

use PDO;

class AuthController
{
    private PDO    $pdo;
    private string $recaptchaSecret;

    public function __construct(PDO $pdo, string $recaptchaSecret = RECAPTCHA_SECRET_KEY)
    {
        $this->pdo             = $pdo;
        $this->recaptchaSecret = $recaptchaSecret;
    }

    // ── Login ─────────────────────────────────────────────────

    public function login(string $username, string $password, string $recaptchaToken = ''): array
    {
        // Rate-limit check
        if ($this->isRateLimited($username)) {
            return ['success' => false, 'error' => 'login_locked',
                    'message' => 'มีการพยายามเข้าสู่ระบบมากเกินไป กรุณารอ 5 นาทีแล้วลองใหม่'];
        }

        // reCAPTCHA verification
        if (!$this->verifyRecaptcha($recaptchaToken)) {
            return ['success' => false, 'error' => 'recaptcha_fail',
                    'message' => 'การยืนยัน reCAPTCHA ล้มเหลว โปรดลองอีกครั้ง'];
        }

        // Validate input
        if (empty($username) || empty($password)) {
            return ['success' => false, 'error' => 'empty_fields',
                    'message' => 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน'];
        }

        // Fetch user
        $stmt = $this->pdo->prepare(
            'SELECT id, username, password, first_name, last_name, role FROM users WHERE username = ? LIMIT 1'
        );
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            $this->recordFailedAttempt($username);
            return ['success' => false, 'error' => 'invalid_credentials',
                    'message' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'];
        }

        // Rehash if needed
        if (password_needs_rehash($user['password'], PASSWORD_BCRYPT)) {
            $newHash = password_hash($password, PASSWORD_BCRYPT);
            $this->pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
                      ->execute([$newHash, $user['id']]);
        }

        // Build session
        session_regenerate_id(true);
        $_SESSION['user_id']       = $user['id'];
        $_SESSION['username']      = $user['username'];
        $_SESSION['first_name']    = $user['first_name'];
        $_SESSION['last_name']     = $user['last_name'];
        $_SESSION['role']          = $user['role'];
        $_SESSION['last_activity'] = time();
        $_SESSION['__init']        = true;

        $this->clearFailedAttempts($username);

        return ['success' => true, 'role' => $user['role'],
                'redirect' => AuthMiddleware::isMobileOrTablet() ? 'dashboard_mobile' : 'dashboard'];
    }

    // ── Logout ────────────────────────────────────────────────

    public function logout(): void
    {
        AuthMiddleware::destroySession();
    }

    // ── Rate Limiting ────────────────────────────────────────

    private function isRateLimited(string $username): bool
    {
        $key  = 'login_attempts_' . md5($username . ($_SERVER['REMOTE_ADDR'] ?? ''));
        $data = $this->getAttemptData($key);
        if (!$data) return false;
        return $data['count'] >= RATE_LIMIT_LOGIN
            && (time() - $data['first_at']) < RATE_LIMIT_WINDOW;
    }

    private function recordFailedAttempt(string $username): void
    {
        $key  = 'login_attempts_' . md5($username . ($_SERVER['REMOTE_ADDR'] ?? ''));
        $data = $this->getAttemptData($key) ?? ['count' => 0, 'first_at' => time()];
        $data['count']++;
        $file = CACHE_DIR . $key . '.lock';
        file_put_contents($file, serialize($data), LOCK_EX);
    }

    private function clearFailedAttempts(string $username): void
    {
        $key  = 'login_attempts_' . md5($username . ($_SERVER['REMOTE_ADDR'] ?? ''));
        @unlink(CACHE_DIR . $key . '.lock');
    }

    private function getAttemptData(string $key): ?array
    {
        $file = CACHE_DIR . $key . '.lock';
        if (!file_exists($file)) return null;
        $data = @unserialize(file_get_contents($file));
        if (!$data) return null;
        // Expired window — reset
        if ((time() - $data['first_at']) >= RATE_LIMIT_WINDOW) {
            @unlink($file);
            return null;
        }
        return $data;
    }

    // ── reCAPTCHA ────────────────────────────────────────────

    private function verifyRecaptcha(string $token): bool
    {
        if (empty($token)) return false;
        $url  = 'https://www.google.com/recaptcha/api/siteverify';
        $resp = @file_get_contents($url . '?secret=' . urlencode($this->recaptchaSecret)
                                       . '&response=' . urlencode($token));
        if (!$resp) return false;
        $data = json_decode($resp, true);
        return !empty($data['success']);
    }

    // ── Password Reset ───────────────────────────────────────

    public function requestPasswordReset(string $username): bool
    {
        $stmt = $this->pdo->prepare('SELECT id, username FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if (!$user) return false; // Silent — don't reveal existence

        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 3600);

        // Store token (assumes password_resets table)
        $this->pdo->prepare(
            'INSERT INTO password_resets (username, token, expires_at) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at)'
        )->execute([$user['username'], hash('sha256', $token), $expires]);

        // TODO: send email via EmailService
        return true;
    }
}