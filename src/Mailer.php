<?php

declare(strict_types=1);

namespace Workshop;

use PHPMailer\PHPMailer\PHPMailer;
use Throwable;

/**
 * Plain-text mail via authenticated SMTP (port 587, STARTTLS). With the
 * "log" transport mails are written to storage/mail-outbox/ instead, which
 * is what local development uses.
 */
final class Mailer
{
    public function __construct(private readonly array $cfg, private readonly string $outboxDir)
    {
    }

    public function send(string $to, string $subject, string $body, ?string $replyTo = null): bool
    {
        $subject = trim((string) preg_replace('/[\r\n]+/', ' ', $subject));

        try {
            return ($this->cfg['transport'] ?? 'log') === 'smtp'
                ? $this->viaSmtp($to, $subject, $body, $replyTo)
                : $this->toOutbox($to, $subject, $body, $replyTo);
        } catch (Throwable $e) {
            error_log('Mail to ' . $to . ' failed: ' . $e->getMessage());

            return false;
        }
    }

    private function viaSmtp(string $to, string $subject, string $body, ?string $replyTo): bool
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = (string) $this->cfg['host'];
        $mail->Port = (int) ($this->cfg['port'] ?? 587);
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->SMTPAuth = true;
        $mail->Username = (string) $this->cfg['username'];
        $mail->Password = (string) $this->cfg['password'];
        $mail->Timeout = 15;
        // Timeout only covers connecting; this bounds each wait for a server reply.
        $mail->getSMTPInstance()->Timelimit = 15;
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->Encoding = PHPMailer::ENCODING_QUOTED_PRINTABLE;
        $mail->XMailer = ' ';

        $mail->setFrom((string) $this->cfg['from_address'], (string) ($this->cfg['from_name'] ?? ''));
        $mail->addAddress($to);
        if ($replyTo !== null && $replyTo !== '') {
            $mail->addReplyTo($replyTo);
        }
        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body = $body;

        return $mail->send();
    }

    private function toOutbox(string $to, string $subject, string $body, ?string $replyTo): bool
    {
        if (!is_dir($this->outboxDir)) {
            mkdir($this->outboxDir, 0750, true);
        }
        $headers = [
            'From: ' . ($this->cfg['from_name'] ?? '') . ' <' . ($this->cfg['from_address'] ?? '') . '>',
            'To: ' . $to,
            'Reply-To: ' . ($replyTo ?? ''),
            'Subject: ' . $subject,
            'Date: ' . date(DATE_RFC2822),
        ];
        $file = sprintf('%s/%s-%s.eml', $this->outboxDir, date('Ymd-His'), bin2hex(random_bytes(3)));

        return file_put_contents($file, implode("\r\n", $headers) . "\r\n\r\n" . $body) !== false;
    }
}
