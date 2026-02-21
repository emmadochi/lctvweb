<?php
/**
 * Mailer Utility
 * Simple wrapper around PHP mail() using environment configuration.
 *
 * NOTE: On local XAMPP you may need to configure SMTP in php.ini
 * for mail() to actually send. This class focuses on composing
 * messages and centralizing email logic.
 */

require_once __DIR__ . '/EnvLoader.php';

class Mailer
{
    /**
     * Send an email
     *
     * @param string $to
     * @param string $subject
     * @param string $htmlBody
     * @param string|null $textBody Optional plain-text alternative
     * @return bool
     */
    public static function send($to, $subject, $htmlBody, $textBody = null)
    {
        $fromEmail = getenv('SMTP_FROM') ?: getenv('ADMIN_EMAIL') ?: 'no-reply@localhost';
        $fromName  = getenv('APP_NAME') ?: 'LCMTV';

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        // Basic headers for HTML email
        $headers   = [];
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-type: text/html; charset=UTF-8";
        $headers[] = 'From: ' . self::formatAddress($fromEmail, $fromName);

        // Optional Reply-To
        $replyTo = getenv('SMTP_REPLY_TO');
        if ($replyTo) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        // Simple plain-text fallback appended to bottom if provided
        if ($textBody) {
            $htmlBody .= '<hr><pre style="font-family: monospace; white-space: pre-wrap;">'
                . htmlspecialchars($textBody, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '</pre>';
        }

        $headerString = implode("\r\n", $headers);

        try {
            $result = @mail($to, $encodedSubject, $htmlBody, $headerString);

            if (!$result) {
                error_log("Mailer::send failed for {$to} with subject '{$subject}'");
            }

            return $result;
        } catch (\Throwable $e) {
            error_log("Mailer::send exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Format an email address with an optional display name.
     */
    private static function formatAddress($email, $name = null)
    {
        $email = trim($email);
        if (!$name) {
            return $email;
        }

        $name = trim($name);
        // Quote name if it contains special characters
        if (preg_match('/[^\w\s]/', $name)) {
            $name = '"' . addcslashes($name, '"') . '"';
        }
        return "{$name} <{$email}>";
    }
}

?>

