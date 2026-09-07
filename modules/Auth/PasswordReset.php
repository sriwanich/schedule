<?php

declare(strict_types=1);

namespace Modules\Auth;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

/**
 * PasswordReset
 * ─────────────────────────────────────────────────────────
 * Flow:
 *   1. showForgotForm()   → กรอก email
 *   2. sendResetLink()    → ส่ง token ทาง email
 *   3. showResetForm()    → กรอกรหัสผ่านใหม่ (พร้อม token ใน URL)
 *   4. resetPassword()    → บันทึกรหัสผ่านใหม่
 */
class PasswordReset extends Controller
{
    private const TOKEN_TTL    = 3600;   // 1 ชั่วโมง
    private const TOKEN_BYTES  = 32;
    private const TABLE        = 'password_reset_tokens';

    public function __construct(
        private Database    $db,
        private AuthService $auth
    ) {}

    // ── Step 1: Show Forgot Form ─────────────────────────────────────────

    public function showForgotForm(Request $request): Response
    {
        if ($this->auth->check()) return $this->redirect('/dashboard');

        return $this->view('pages.auth.forgot', [
            'pageTitle' => 'ลืมรหัสผ่าน',
        ]);
    }

    // ── Step 2: Send Reset Link ──────────────────────────────────────────

    public function sendResetLink(Request $request): Response
    {
        $email = trim(strtolower($request->input('email', '')));

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->backWithError('กรุณากรอกอีเมลให้ถูกต้อง');
        }

        // Rate limit — ป้องกันการ spam
        $cacheKey = 'pwd_reset_' . md5($email);
        if (!empty($_SESSION[$cacheKey])) {
            $wait = ceil((int)$_SESSION[$cacheKey] - time());
            if ($wait > 0) {
                return $this->backWithError("กรุณารอ {$wait} วินาทีก่อนส่งอีกครั้ง");
            }
        }

        // ค้นหา user ด้วย email (ไม่บอกว่าพบหรือไม่ เพื่อความปลอดภัย)
        $user = $this->db->selectOne(
            "SELECT id, username, first_name, email FROM users WHERE email = ? AND is_active = 1 AND deleted_at IS NULL",
            [$email]
        );

        if ($user) {
            // ลบ token เก่า
            $this->db->query(
                "DELETE FROM " . self::TABLE . " WHERE email = ?",
                [$email]
            );

            // สร้าง token ใหม่
            $token     = bin2hex(random_bytes(self::TOKEN_BYTES));
            $expiresAt = date('Y-m-d H:i:s', time() + self::TOKEN_TTL);

            $this->db->query(
                "INSERT INTO " . self::TABLE . " (email, token, expires_at, created_at) VALUES (?, ?, ?, NOW())",
                [$email, hash('sha256', $token), $expiresAt]
            );

            // ส่งอีเมล (ใช้ EmailService — Phase 4)
            $this->sendEmail($user, $token);

            log_activity('password_reset_requested', 'auth', $user['id']);
        }

        // Rate limit: ห้ามส่งซ้ำภายใน 60 วินาที
        $_SESSION[$cacheKey] = time() + 60;

        // แสดง success เสมอ (ไม่ว่าจะพบ user หรือไม่)
        session_flash('success', 'หากอีเมลนี้มีอยู่ในระบบ คุณจะได้รับลิงก์รีเซ็ตรหัสผ่านในอีกสักครู่');
        return $this->redirect('/login');
    }

    // ── Step 3: Show Reset Form ──────────────────────────────────────────

    public function showResetForm(Request $request): Response
    {
        $token = $request->query('token', '');
        $email = $request->query('email', '');

        if (empty($token) || empty($email)) {
            session_flash('error', 'ลิงก์รีเซ็ตรหัสผ่านไม่ถูกต้อง');
            return $this->redirect('/forgot-password');
        }

        if (!$this->isValidToken($email, $token)) {
            session_flash('error', 'ลิงก์รีเซ็ตรหัสผ่านหมดอายุหรือไม่ถูกต้อง กรุณาขอใหม่');
            return $this->redirect('/forgot-password');
        }

        return $this->view('pages.auth.reset', [
            'pageTitle' => 'ตั้งรหัสผ่านใหม่',
            'token'     => $token,
            'email'     => $email,
        ]);
    }

    // ── Step 4: Reset Password ───────────────────────────────────────────

    public function resetPassword(Request $request): Response
    {
        $token           = $request->input('token', '');
        $email           = trim(strtolower($request->input('email', '')));
        $password        = $request->input('password', '');
        $passwordConfirm = $request->input('password_confirmation', '');

        // Validate fields
        $errors = [];
        if (empty($token) || empty($email)) {
            $errors[] = 'ข้อมูลไม่ครบ กรุณาขอลิงก์รีเซ็ตใหม่';
        }
        if (strlen($password) < 8) {
            $errors[] = 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'รหัสผ่านต้องมีตัวพิมพ์ใหญ่อย่างน้อย 1 ตัว';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'รหัสผ่านต้องมีตัวเลขอย่างน้อย 1 ตัว';
        }
        if ($password !== $passwordConfirm) {
            $errors[] = 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน';
        }

        if (!empty($errors)) {
            $_SESSION['_flash']['errors'] = $errors;
            return $this->redirect("/reset-password?token={$token}&email=" . urlencode($email));
        }

        if (!$this->isValidToken($email, $token)) {
            session_flash('error', 'ลิงก์หมดอายุ กรุณาขอใหม่');
            return $this->redirect('/forgot-password');
        }

        // อัพเดทรหัสผ่าน
        $hash = $this->auth->hashPassword($password);
        $this->db->query(
            "UPDATE users SET password = ?, failed_login_attempts = 0, locked_until = NULL, updated_at = NOW()
             WHERE email = ? AND is_active = 1 AND deleted_at IS NULL",
            [$hash, $email]
        );

        // ลบ token ออก (ใช้ได้ครั้งเดียว)
        $this->db->query("DELETE FROM " . self::TABLE . " WHERE email = ?", [$email]);

        // ล้าง session ที่เกี่ยวข้อง
        $user = $this->db->selectOne("SELECT id FROM users WHERE email = ?", [$email]);
        if ($user) {
            log_activity('password_reset_completed', 'auth', $user['id']);
        }

        session_flash('success', 'ตั้งรหัสผ่านใหม่สำเร็จ กรุณาเข้าสู่ระบบ');
        return $this->redirect('/login');
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function isValidToken(string $email, string $token): bool
    {
        $record = $this->db->selectOne(
            "SELECT token, expires_at FROM " . self::TABLE . " WHERE email = ? AND expires_at > NOW()",
            [$email]
        );
        if (!$record) return false;
        return hash_equals($record['token'], hash('sha256', $token));
    }

    private function sendEmail(array $user, string $token): void
    {
        // Phase 4: ใช้ EmailService (PHPMailer)
        // ตอนนี้ใช้ mail() เป็น fallback
        $resetUrl = url("/reset-password?token={$token}&email=" . urlencode($user['email']));
        $name     = $user['first_name'] ?? $user['username'];
        $subject  = 'รีเซ็ตรหัสผ่าน — ' . env('APP_NAME', 'ระบบตารางงาน');
        $body     = "สวัสดี {$name}\n\n"
                  . "กรุณาคลิกลิงก์ด้านล่างเพื่อตั้งรหัสผ่านใหม่ (มีอายุ 1 ชั่วโมง):\n\n"
                  . "{$resetUrl}\n\n"
                  . "หากคุณไม่ได้ขอรีเซ็ตรหัสผ่าน กรุณาไม่ต้องสนใจอีเมลนี้\n\n"
                  . env('APP_NAME', 'ระบบตารางงาน');

        $headers = "From: " . env('MAIL_FROM_ADDRESS', 'noreply@your-domain.com') . "\r\n"
                 . "Content-Type: text/plain; charset=UTF-8\r\n";

        @mail($user['email'], $subject, $body, $headers);
    }

    private function backWithError(string $message): Response
    {
        session_flash('error', $message);
        return $this->redirect('/forgot-password');
    }
}