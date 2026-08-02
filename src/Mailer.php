<?php
declare(strict_types=1);

/**
 * Mailer — sends emails via SMTP (fsockopen), Brevo API, or PHP mail().
 * Construct with the email_settings row from the DB.
 */
class Mailer
{
    private array  $settings;
    public  string $lastError = '';

    public function __construct(array $emailSettings)
    {
        $this->settings = $emailSettings;
    }

    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------

    /**
     * Send an email. Returns true on success, false on failure.
     * $textBody falls back to strip_tags($htmlBody) when null.
     */
    public function send(
        string  $to,
        string  $subject,
        string  $htmlBody,
        ?string $textBody = null
    ): bool {
        // Prevent header injection: strip newlines from all header-relevant values
        $to      = str_replace(["\r", "\n"], '', $to);
        $subject = str_replace(["\r", "\n"], '', $subject);

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->lastError = 'Invalid recipient address.';
            return false;
        }

        $textBody ??= wordwrap(strip_tags($htmlBody), 76, "\r\n", true);

        $transport = $this->settings['transport'] ?? 'smtp';
        try {
            return match ($transport) {
                'brevo_api' => $this->sendBrevoApi($to, $subject, $htmlBody, $textBody),
                'sendmail'  => $this->sendSendmail($to, $subject, $htmlBody, $textBody),
                default     => $this->sendSmtp($to, $subject, $htmlBody, $textBody),
            };
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    // ------------------------------------------------------------------
    // SMTP via fsockopen
    // ------------------------------------------------------------------

    private function sendSmtp(
        string $to,
        string $subject,
        string $htmlBody,
        string $textBody
    ): bool {
        $host     = $this->settings['smtp_host']     ?? '';
        $port     = (int) ($this->settings['smtp_port']     ?? 587);
        $user     = $this->settings['smtp_user']     ?? '';
        $pass     = $this->settings['smtp_password'] ?? '';
        $fromAddr = $this->settings['from_address']  ?? '';
        $fromName = $this->settings['from_name']     ?? site_title();

        if ($host === '' || $fromAddr === '') {
            $this->lastError = 'SMTP not configured (host or from_address missing).';
            return false;
        }

        $errno  = 0;
        $errstr = '';
        $timeout = 15;

        $sock = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if (!$sock) {
            $this->lastError = "SMTP connect failed: {$errstr} ({$errno})";
            return false;
        }

        stream_set_timeout($sock, $timeout);

        $read = fn() => fgets($sock, 515);
        $write = function (string $line) use ($sock): void { fwrite($sock, $line . "\r\n"); };
        $expect = function (int $code) use ($read): string {
            $line = '';
            while (true) {
                $line = $this->readLine($read);
                if ($line === '' || (strlen($line) >= 4 && $line[3] !== '-')) {
                    break;
                }
            }
            $actual = (int) substr($line, 0, 3);
            if ($actual !== $code) {
                throw new RuntimeException("SMTP expected {$code}, got {$actual}: {$line}");
            }
            return $line;
        };

        try {
            $expect(220); // Greeting

            $write('EHLO ' . ($this->settings['smtp_host'] ?? 'localhost'));
            $ehloResponse = '';
            while (true) {
                $line = $this->readLine($read);
                $ehloResponse .= $line;
                if ($line === '' || (strlen($line) >= 4 && $line[3] !== '-')) {
                    break;
                }
            }

            // STARTTLS on port 587
            if ($port === 587 && str_contains($ehloResponse, 'STARTTLS')) {
                $write('STARTTLS');
                $expect(220);
                if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('STARTTLS negotiation failed.');
                }
                $write('EHLO ' . ($this->settings['smtp_host'] ?? 'localhost'));
                while (true) {
                    $line = $this->readLine($read);
                    if ($line === '' || (strlen($line) >= 4 && $line[3] !== '-')) break;
                }
            }

            // AUTH LOGIN
            if ($user !== '') {
                $write('AUTH LOGIN');
                $expect(334);
                $write(base64_encode($user));
                $expect(334);
                $write(base64_encode($pass));
                $expect(235);
            }

            // Envelope
            $write('MAIL FROM:<' . $fromAddr . '>');
            $expect(250);

            $write('RCPT TO:<' . $to . '>');
            $expect(250);

            $write('DATA');
            $expect(354);

            // Build MIME message
            $boundary = 'MP_' . bin2hex(random_bytes(8));
            $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
            $encodedFrom    = '=?UTF-8?B?' . base64_encode($fromName) . '?= <' . $fromAddr . '>';
            $messageId      = '<' . bin2hex(random_bytes(8)) . '@mitbringliste>';

            $headers  = "From: {$encodedFrom}\r\n";
            $headers .= "To: {$to}\r\n";
            $headers .= "Subject: {$encodedSubject}\r\n";
            $headers .= "Message-ID: {$messageId}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
            $headers .= "Date: " . date('r') . "\r\n";

            $body  = "--{$boundary}\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($textBody)) . "\r\n";
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";
            $body .= "--{$boundary}--\r\n";

            // Dot-stuff: lines starting with '.' must be doubled (RFC 5321)
            $body = preg_replace('/^\./m', '..', $body);

            fwrite($sock, $headers . "\r\n" . $body . "\r\n.\r\n");
            $expect(250);

            $write('QUIT');
            $expect(221);

        } finally {
            fclose($sock);
        }

        return true;
    }

    /** Read a single response line, returning empty string on EOF. */
    private function readLine(callable $reader): string
    {
        $line = $reader();
        return is_string($line) ? rtrim($line, "\r\n") : '';
    }

    // ------------------------------------------------------------------
    // Brevo API
    // ------------------------------------------------------------------

    private function sendBrevoApi(
        string $to,
        string $subject,
        string $htmlBody,
        string $textBody
    ): bool {
        $apiKey   = $this->settings['api_key']       ?? '';
        $fromAddr = $this->settings['from_address']  ?? '';
        $fromName = $this->settings['from_name']     ?? site_title();

        if ($apiKey === '' || $fromAddr === '') {
            $this->lastError = 'Brevo not configured (api_key or from_address missing).';
            return false;
        }

        $payload = json_encode([
            'sender'       => ['name' => $fromName, 'email' => $fromAddr],
            'to'           => [['email' => $to]],
            'subject'      => $subject,
            'htmlContent'  => $htmlBody,
            'textContent'  => $textBody,
        ], JSON_THROW_ON_ERROR);

        $context = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => implode("\r\n", [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'api-key: ' . $apiKey,
                ]),
                'content'       => $payload,
                'ignore_errors' => true,
                'timeout'       => 15,
            ],
        ]);

        $result = @file_get_contents('https://api.brevo.com/v3/smtp/email', false, $context);

        if ($result === false) {
            // cURL fallback
            if (function_exists('curl_init')) {
                return $this->sendBrevoApiCurl($to, $subject, $htmlBody, $textBody);
            }
            $this->lastError = 'Brevo API: file_get_contents failed and cURL unavailable.';
            return false;
        }

        $httpCode = 0;
        if (!empty($http_response_header)) {
            preg_match('/HTTP\/\S+ (\d{3})/', $http_response_header[0], $m);
            $httpCode = (int) ($m[1] ?? 0);
        }

        if ($httpCode !== 201) {
            $decoded = json_decode($result, true);
            $this->lastError = 'Brevo API HTTP ' . $httpCode . ': '
                . ($decoded['message'] ?? $result);
            return false;
        }

        return true;
    }

    private function sendBrevoApiCurl(
        string $to,
        string $subject,
        string $htmlBody,
        string $textBody
    ): bool {
        $apiKey   = $this->settings['api_key']      ?? '';
        $fromAddr = $this->settings['from_address'] ?? '';
        $fromName = $this->settings['from_name']    ?? site_title();

        $payload = json_encode([
            'sender'      => ['name' => $fromName, 'email' => $fromAddr],
            'to'          => [['email' => $to]],
            'subject'     => $subject,
            'htmlContent' => $htmlBody,
            'textContent' => $textBody,
        ], JSON_THROW_ON_ERROR);

        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'api-key: ' . $apiKey,
            ],
        ]);
        $result   = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 201) {
            $decoded = json_decode((string) $result, true);
            $this->lastError = 'Brevo cURL HTTP ' . $httpCode . ': '
                . ($decoded['message'] ?? (string) $result);
            return false;
        }

        return true;
    }

    // ------------------------------------------------------------------
    // PHP mail() / Sendmail
    // ------------------------------------------------------------------

    private function sendSendmail(
        string $to,
        string $subject,
        string $htmlBody,
        string $textBody
    ): bool {
        $fromAddr = $this->settings['from_address'] ?? '';
        $fromName = $this->settings['from_name']    ?? site_title();

        $boundary       = 'MP_' . bin2hex(random_bytes(8));
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encodedFrom    = '=?UTF-8?B?' . base64_encode($fromName) . '?= <' . $fromAddr . '>';

        $headers  = "From: {$encodedFrom}\r\n";
        $headers .= "Reply-To: {$fromAddr}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";

        $body  = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($textBody)) . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";
        $body .= "--{$boundary}--";

        if (!mail($to, $encodedSubject, $body, $headers)) {
            $this->lastError = 'PHP mail() returned false.';
            return false;
        }
        return true;
    }

    // ------------------------------------------------------------------
    // Helper: render an email HTML template
    // ------------------------------------------------------------------

    /**
     * Render a template from templates/emails/{$name}.php and return HTML string.
     * $vars are extracted into the template scope.
     */
    public static function renderTemplate(string $name, array $vars = []): string
    {
        return (static function (string $__path, array $__vars): string {
            extract($__vars);
            ob_start();
            include $__path;
            return (string) ob_get_clean();
        })(BASE_DIR . '/templates/emails/' . $name . '.php', $vars);
    }
}
