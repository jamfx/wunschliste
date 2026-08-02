<?php
declare(strict_types=1);

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;

class Totp
{
    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------

    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    public static function getCode(string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        return self::hotp($secret, (int) floor($timestamp / 30));
    }

    /** Verify with ±1 time-step tolerance. */
    public static function verify(string $secret, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code);
        if (strlen($code) !== 6 || !ctype_digit($code)) {
            return false;
        }
        $counter = (int) floor(time() / 30);
        for ($i = -1; $i <= 1; $i++) {
            if (hash_equals(self::hotp($secret, $counter + $i), $code)) {
                return true;
            }
        }
        return false;
    }

    public static function getQrCodeUri(
        string $secret,
        string $email,
        string $issuer = 'Mitbringliste'
    ): string {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            rawurlencode($issuer),
            rawurlencode($email),
            $secret,
            rawurlencode($issuer)
        );
    }

    /** Returns inline SVG markup for the QR code. */
    public static function renderQrCodeSvg(string $uri): string
    {
        $qrCode = new QrCode($uri, size: 250, margin: 10);
        $writer = new SvgWriter();
        $result = $writer->write($qrCode);
        return $result->getString();
    }

    // ------------------------------------------------------------------
    // HOTP (RFC 4226 core)
    // ------------------------------------------------------------------

    private static function hotp(string $secret, int $counter): string
    {
        $key     = self::base32Decode($secret);
        $message = pack('N*', 0) . pack('N*', $counter);
        $hash    = hash_hmac('sha1', $message, $key, true);
        $offset  = ord($hash[19]) & 0x0F;
        $code    = (
            ((ord($hash[$offset])   & 0x7F) << 24) |
            ((ord($hash[$offset+1]) & 0xFF) << 16) |
            ((ord($hash[$offset+2]) & 0xFF) << 8)  |
             (ord($hash[$offset+3]) & 0xFF)
        ) % 1_000_000;
        return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
    }

    // ------------------------------------------------------------------
    // Base32 (RFC 4648)
    // ------------------------------------------------------------------

    public static function base32Encode(string $data): string
    {
        $chars  = self::BASE32_CHARS;
        $result = '';
        $bits   = 0;
        $buffer = 0;
        foreach (str_split($data) as $char) {
            $buffer = ($buffer << 8) | ord($char);
            $bits  += 8;
            while ($bits >= 5) {
                $bits  -= 5;
                $result .= $chars[($buffer >> $bits) & 0x1F];
            }
        }
        if ($bits > 0) {
            $result .= $chars[($buffer << (5 - $bits)) & 0x1F];
        }
        return $result;
    }

    private static function base32Decode(string $data): string
    {
        $chars  = self::BASE32_CHARS;
        $data   = strtoupper(rtrim($data, '='));
        $result = '';
        $bits   = 0;
        $buffer = 0;
        foreach (str_split($data) as $char) {
            $pos = strpos($chars, $char);
            if ($pos === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $pos;
            $bits  += 5;
            if ($bits >= 8) {
                $bits  -= 8;
                $result .= chr(($buffer >> $bits) & 0xFF);
            }
        }
        return $result;
    }
}
