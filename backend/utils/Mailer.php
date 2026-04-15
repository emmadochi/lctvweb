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
     * @param string $htmlBodyContent The content to put inside the template
     * @param string|null $textBody Optional plain-text alternative
     * @return bool
     */
    public static function send($to, $subject, $htmlBodyContent, $textBody = null)
    {
        $fromEmail = getenv('SMTP_FROM') ?: getenv('ADMIN_EMAIL') ?: 'no-reply@localhost';
        $fromName  = getenv('APP_NAME') ?: 'LCMTV';

        // Wrap content in the branded template
        $fullHtmlBody = self::wrapInTemplate($subject, $htmlBodyContent);

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

        $headerString = implode("\r\n", $headers);

        try {
            $result = @mail($to, $encodedSubject, $fullHtmlBody, $headerString);

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
     * Wraps the email content in a branded HTML template (Orange and Purple)
     */
    public static function wrapInTemplate($title, $content) {
        $appUrl = getenv('APP_URL') ?: 'http://localhost/LCMTVWebNew/frontend';
        $appName = getenv('APP_NAME') ?: 'LCMTV';
        $year = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #FF8C00 0%, #6A0DAD 100%); padding: 30px; text-align: center; color: #ffffff; }
        .header h1 { margin: 0; font-size: 24px; text-transform: uppercase; letter-spacing: 2px; }
        .content { padding: 30px; font-size: 16px; min-height: 200px; }
        .footer { background: #f9f9f9; padding: 20px; text-align: center; font-size: 12px; color: #777; border-top: 1px solid #eeeeee; }
        .button { display: inline-block; padding: 12px 24px; background-color: #FF8C00; color: #ffffff !important; text-decoration: none; border-radius: 5px; font-weight: bold; margin-top: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        .button:hover { background-color: #e67e00; }
        .purple { color: #6A0DAD; }
        .orange { color: #FF8C00; }
        hr { border: 0; border-top: 1px solid #eeeeee; margin: 25px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{$appName}</h1>
        </div>
        <div class="content">
            <h2 class="purple" style="margin-top:0;">{$title}</h2>
            {$content}
            <div style="text-align: center;">
                <a href="{$appUrl}" class="button">Go to {$appName} Platform</a>
            </div>
        </div>
        <div class="footer">
            <p>&copy; {$year} {$appName}. All rights reserved.</p>
            <p>You received this email because you are a registered member of {$appName}.</p>
        </div>
    </div>
</body>
</html>
HTML;
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

