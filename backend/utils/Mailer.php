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
    public static function send($to, $subject, $htmlBodyContent, $textBody = null, $actionUrl = null, $actionText = null)
    {
        $fromEmail = getenv('SMTP_FROM') ?: getenv('ADMIN_EMAIL') ?: 'no-reply@localhost';
        $fromName  = getenv('APP_NAME') ?: 'LCMTV';

        // Wrap content in the branded template
        $fullHtmlBody = self::wrapInTemplate($subject, $htmlBodyContent, $actionUrl, $actionText);

        // Check if SMTP is configured
        $smtpHost = getenv('SMTP_HOST');
        if ($smtpHost) {
            return self::sendViaSmtp($to, $subject, $fullHtmlBody, $fromEmail, $fromName);
        }

        // Fallback to mail()
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $headers   = [];
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-type: text/html; charset=UTF-8";
        $headers[] = 'From: ' . self::formatAddress($fromEmail, $fromName);

        $replyTo = getenv('SMTP_REPLY_TO');
        if ($replyTo) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        $headerString = implode("\r\n", $headers);

        try {
            $result = @mail($to, $encodedSubject, $fullHtmlBody, $headerString);
            if (!$result) {
                error_log("Mailer::send (mail) failed for {$to}");
            }
            return $result;
        } catch (\Throwable $e) {
            error_log("Mailer::send exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email via SMTP (Lightweight implementation)
     */
    private static function sendViaSmtp($to, $subject, $htmlBody, $fromEmail, $fromName) {
        $host = getenv('SMTP_HOST');
        $port = getenv('SMTP_PORT') ?: 587;
        $user = getenv('SMTP_USER');
        $pass = getenv('SMTP_PASS');
        $secure = ($port == 465) ? 'ssl://' : '';

        $timeout = 10;
        
        // Create a stream context to bypass SSL certificate verification if needed
        // (Commonly required on shared hosting where certificates might mismatch)
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);

        $socket = stream_socket_client($secure . $host . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);

        if (!$socket) {
            error_log("SMTP Connection Error: $errstr ($errno) for host $host:$port");
            return false;
        }

        $getResponse = function($socket) {
            $response = "";
            while ($line = fgets($socket, 515)) {
                $response .= $line;
                if (substr($line, 3, 1) == " ") break;
            }
            return $response;
        };

        $sendCommand = function($socket, $cmd) use ($getResponse) {
            fputs($socket, $cmd . "\r\n");
            $response = $getResponse($socket);
            // Log conversation if needed for debugging
            // error_log("SMTP CMD: $cmd -> $response");
            return $response;
        };

        $greeting = $getResponse($socket); // Greeting
        if (substr($greeting, 0, 3) != '220') {
            error_log("SMTP Greeting Failed: " . $greeting);
            fclose($socket);
            return false;
        }

        $sendCommand($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        
        if ($port == 587) {
            $sendCommand($socket, "STARTTLS");
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $sendCommand($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        }

        if ($user && $pass) {
            $sendCommand($socket, "AUTH LOGIN");
            $sendCommand($socket, base64_encode($user));
            $response = $sendCommand($socket, base64_encode($pass));
            if (substr($response, 0, 3) != '235') {
                error_log("SMTP Auth Failed for $user. Server said: " . $response);
                fclose($socket);
                return false;
            }
        }

        $res1 = $sendCommand($socket, "MAIL FROM: <$fromEmail>");
        $res2 = $sendCommand($socket, "RCPT TO: <$to>");
        $res3 = $sendCommand($socket, "DATA");

        if (substr($res1, 0, 3) != '250' || substr($res2, 0, 3) != '250' || substr($res3, 0, 3) != '354') {
            error_log("SMTP Command Failed. MAIL FROM: $res1, RCPT TO: $res2, DATA: $res3");
            fclose($socket);
            return false;
        }

        $headers = [
            "MIME-Version: 1.0",
            "Content-type: text/html; charset=UTF-8",
            "To: $to",
            "From: " . self::formatAddress($fromEmail, $fromName),
            "Subject: " . '=?UTF-8?B?' . base64_encode($subject) . '?=',
            "Date: " . date('r')
        ];

        $content = implode("\r\n", $headers) . "\r\n\r\n" . $htmlBody . "\r\n.";
        $response = $sendCommand($socket, $content);
        
        if (substr($response, 0, 3) != '250') {
            error_log("SMTP Message Delivery Failed. Server said: " . $response);
        }

        $sendCommand($socket, "QUIT");
        fclose($socket);

        return substr($response, 0, 3) == '250';
    }

    /**
     * Wraps the email content in a branded HTML template (Orange and Purple)
     */
    public static function wrapInTemplate($title, $content, $actionUrl = null, $actionText = null) {
        $appUrl = getenv('APP_URL');
        if (!$appUrl || strpos($appUrl, 'localhost') !== false) {
            $appUrl = 'https://tv.lifechangerstouch.org';
        }
        $appName = getenv('APP_NAME') ?: 'LCMTV';
        $year = date('Y');

        $finalUrl = $actionUrl ?: $appUrl;
        $finalText = $actionText ?: "Go to {$appName} Platform";

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
                <a href="{$finalUrl}" class="button">{$finalText}</a>
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

