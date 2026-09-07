<?php

declare(strict_types=1);

namespace Modules\Notification;

/**
 * EmailService
 * ─────────────────────────────────────────────────────────
 * PHPMailer wrapper + fallback ไปยัง mail()
 * Phase 4 ready — ใช้ composer require phpmailer/phpmailer
 *
 * ถ้ายังไม่ได้ติดตั้ง PHPMailer จะ fallback ใช้ mail() ของ PHP แทน
 */
class EmailService
{
    private bool $mailerAvailable;

    public function __construct()
    {
        $this->mailerAvailable = class_exists('\PHPMailer\PHPMailer\PHPMailer');
    }

    // ── Public API ───────────────────────────────────────────────────────

    /**
     * ส่งอีเมลทั่วไป
     */
    public function send(
        string|array $to,
        string       $subject,
        string       $body,
        array        $options = []
    ): bool {
        return $this->mailerAvailable
            ? $this->sendViaPhpMailer($to, $subject, $body, $options)
            : $this->sendViaMail($to, $subject, $body, $options);
    }

    /**
     * ส่ง password reset email
     */
    public function sendPasswordReset(string $to, string $name, string $resetUrl): bool
    {
        $subject = 'รีเซ็ตรหัสผ่าน — ' . env('APP_NAME', 'ระบบตารางงาน');
        $body    = $this->renderTemplate('password-reset', [
            'name'      => $name,
            'reset_url' => $resetUrl,
            'expires'   => '1 ชั่วโมง',
            'app_name'  => env('APP_NAME', 'ระบบตารางงาน'),
        ]);
        return $this->send($to, $subject, $body, ['is_html' => true]);
    }

    /**
     * ส่ง event reminder email
     */
    public function sendEventReminder(string $to, string $name, array $event): bool
    {
        $subject = '📅 แจ้งเตือนนัดหมาย: ' . $event['title'];
        $body    = $this->renderTemplate('event-reminder', [
            'name'       => $name,
            'event'      => $event,
            'app_name'   => env('APP_NAME', 'ระบบตารางงาน'),
            'event_url'  => url('/preview?id=' . $event['id']),
        ]);
        return $this->send($to, $subject, $body, ['is_html' => true]);
    }

    /**
     * ส่ง welcome email หลัง admin สร้าง user ใหม่
     */
    public function sendWelcome(string $to, string $name, string $username, string $tempPassword): bool
    {
        $subject = 'ยินดีต้อนรับสู่ ' . env('APP_NAME', 'ระบบตารางงาน');
        $body    = $this->renderTemplate('welcome', [
            'name'          => $name,
            'username'      => $username,
            'temp_password' => $tempPassword,
            'login_url'     => url('/login'),
            'app_name'      => env('APP_NAME', 'ระบบตารางงาน'),
        ]);
        return $this->send($to, $subject, $body, ['is_html' => true]);
    }

    // ── PHPMailer Driver ─────────────────────────────────────────────────

    private function sendViaPhpMailer(
        string|array $to,
        string       $subject,
        string       $body,
        array        $options = []
    ): bool {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

            // SMTP config
            $mail->isSMTP();
            $mail->Host        = env('MAIL_HOST', 'localhost');
            $mail->Port        = (int)env('MAIL_PORT', 587);
            $mail->SMTPAuth    = !empty(env('MAIL_USERNAME'));
            $mail->Username    = env('MAIL_USERNAME', '');
            $mail->Password    = env('MAIL_PASSWORD', '');
            $mail->SMTPSecure  = match (strtolower(env('MAIL_ENCRYPTION', 'tls'))) {
                'ssl'  => \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS,
                default => \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS,
            };

            // Charset
            $mail->CharSet  = 'UTF-8';
            $mail->Encoding = 'base64';

            // From
            $mail->setFrom(
                env('MAIL_FROM_ADDRESS', 'noreply@your-domain.com'),
                env('MAIL_FROM_NAME', env('APP_NAME', 'ระบบตารางงาน'))
            );

            // Reply-to
            if (!empty($options['reply_to'])) {
                $mail->addReplyTo($options['reply_to']);
            }

            // To
            $recipients = is_array($to) ? $to : [$to];
            foreach ($recipients as $address) {
                $mail->addAddress(trim($address));
            }

            // CC / BCC
            foreach ($options['cc'] ?? [] as $cc)   $mail->addCC($cc);
            foreach ($options['bcc'] ?? [] as $bcc)  $mail->addBCC($bcc);

            // Attachments
            foreach ($options['attachments'] ?? [] as $att) {
                $mail->addAttachment($att['path'], $att['name'] ?? '');
            }

            // Body
            $mail->Subject = $subject;
            if ($options['is_html'] ?? false) {
                $mail->isHTML(true);
                $mail->Body    = $body;
                $mail->AltBody = strip_tags($body);
            } else {
                $mail->Body = $body;
            }

            return $mail->send();
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            log_error('Email send failed (PHPMailer)', ['error' => $e->getMessage(), 'to' => $to]);
            return false;
        }
    }

    // ── Fallback: PHP mail() ──────────────────────────────────────────────

    private function sendViaMail(
        string|array $to,
        string       $subject,
        string       $body,
        array        $options = []
    ): bool {
        $recipients = is_array($to) ? implode(',', $to) : $to;
        $from       = env('MAIL_FROM_ADDRESS', 'noreply@your-domain.com');
        $fromName   = env('MAIL_FROM_NAME', env('APP_NAME', 'ระบบตารางงาน'));
        $isHtml     = $options['is_html'] ?? false;

        $headers  = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$from}>\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= $isHtml
            ? "Content-Type: text/html; charset=UTF-8\r\n"
            : "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: base64\r\n";

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encodedBody    = base64_encode($body);

        $result = @mail($recipients, $encodedSubject, $encodedBody, $headers);

        if (!$result) {
            log_error('Email send failed (mail())', ['to' => $recipients, 'subject' => $subject]);
        }

        return $result;
    }

    // ── Template Renderer ─────────────────────────────────────────────────

    /**
     * Render email template จาก views/emails/{$name}.php
     * ถ้าไม่พบ template จะใช้ plain text fallback
     */
    private function renderTemplate(string $name, array $vars = []): string
    {
        $templatePath = base_path("views/emails/{$name}.php");

        if (file_exists($templatePath)) {
            extract($vars, EXTR_SKIP);
            ob_start();
            include $templatePath;
            return ob_get_clean();
        }

        // Plain-text fallback
        return $this->plainFallback($name, $vars);
    }

    private function plainFallback(string $name, array $vars): string
    {
        $appName = $vars['app_name'] ?? env('APP_NAME', 'ระบบตารางงาน');

        return match ($name) {
            'password-reset' =>
                "สวัสดี {$vars['name']}\n\n"
                . "กรุณาคลิกลิงก์เพื่อตั้งรหัสผ่านใหม่ (มีอายุ {$vars['expires']}):\n"
                . "{$vars['reset_url']}\n\n"
                . "หากคุณไม่ได้ขอรีเซ็ต กรุณาไม่ต้องสนใจอีเมลนี้\n\n{$appName}",

            'event-reminder' =>
                "สวัสดี {$vars['name']}\n\n"
                . "แจ้งเตือน: {$vars['event']['title']}\n"
                . "วันที่: {$vars['event']['event_date']}\n"
                . "ดูรายละเอียด: {$vars['event_url']}\n\n{$appName}",

            'welcome' =>
                "สวัสดี {$vars['name']}\n\n"
                . "บัญชีของคุณถูกสร้างแล้ว\n"
                . "Username: {$vars['username']}\n"
                . "Password: {$vars['temp_password']}\n"
                . "กรุณาเปลี่ยนรหัสผ่านทันทีหลังเข้าสู่ระบบ\n\n"
                . "เข้าสู่ระบบ: {$vars['login_url']}\n\n{$appName}",

            default => json_encode($vars, JSON_UNESCAPED_UNICODE),
        };
    }
}