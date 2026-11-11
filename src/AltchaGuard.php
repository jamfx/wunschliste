<?php
declare(strict_types=1);

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\CreateChallengeOptions;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\VerifySolutionOptions;

/**
 * Proof-of-work spam protection (ALTCHA v3, PBKDF2/SHA-256) for the public
 * forms. Challenges are created statelessly (HMAC-signed with secrets derived
 * from app_secret); the only server state is a one-time-use marker per solved
 * challenge (stored in rate_limit, cleaned up by the existing lazy cleanup).
 *
 * Difficulty is set by the admin as one of three levels (site_settings key
 * "altcha_level"): off | normal | high. Default is "off".
 */
final class AltchaGuard
{
    public const FORMS = ['create_event', 'suggest'];

    /** Challenge validity in seconds. */
    private const TTL = 3600;

    /** Solver iterates the counter; [min, max] tries × cost PBKDF2 iterations each. */
    private const LEVELS = [
        'normal' => ['cost' => 1000, 'counter' => [800, 1600]],
        'high'   => ['cost' => 1000, 'counter' => [4000, 8000]],
    ];

    public static function level(): string
    {
        global $db;
        static $level = null;
        if ($level !== null) {
            return $level;
        }
        $level = 'off';
        if ($db !== null) {
            try {
                $row = $db->fetch("SELECT setting_value FROM site_settings WHERE setting_key = 'altcha_level' LIMIT 1");
                $value = (string) ($row['setting_value'] ?? '');
                if (isset(self::LEVELS[$value])) {
                    $level = $value;
                }
            } catch (PDOException) {
                // keep "off"
            }
        }
        return $level;
    }

    public static function enabled(): bool
    {
        return self::level() !== 'off';
    }

    /** @return array<string, mixed> challenge in the JSON shape the widget expects */
    public static function createChallenge(string $form): array
    {
        $cfg = self::LEVELS[self::level()] ?? self::LEVELS['normal'];

        return self::altcha()->createChallenge(new CreateChallengeOptions(
            algorithm: new Pbkdf2(),
            cost: $cfg['cost'],
            counter: random_int($cfg['counter'][0], $cfg['counter'][1]),
            expiresAt: time() + self::TTL,
            data: ['f' => $form],
        ))->toArray();
    }

    /**
     * Verify the widget payload ($_POST['altcha']) for the given form.
     * Returns null on success, otherwise a translation key describing the problem.
     * Always succeeds while the protection is switched off.
     */
    public static function verify(string $form): ?string
    {
        if (!self::enabled()) {
            return null;
        }

        $raw = trim((string) ($_POST['altcha'] ?? ''));
        if ($raw === '') {
            return 'validation.altcha_missing';
        }

        try {
            $payload = Payload::fromBase64($raw);
            $result  = self::altcha()->verifySolution(new VerifySolutionOptions(
                payload: $payload,
                algorithm: new Pbkdf2(),
            ));
        } catch (\Throwable) {
            return 'validation.altcha_invalid';
        }

        if ($result->expired) {
            return 'validation.altcha_expired';
        }

        $params = $payload->challenge->parameters;
        if (!$result->verified || ($params->data['f'] ?? null) !== $form || !$params->expiresAt) {
            return 'validation.altcha_invalid';
        }

        // One-time use: a solved challenge must not be replayed.
        global $db;
        $marker = 'altcha_used:' . $params->nonce;
        try {
            if ($db->fetch('SELECT id FROM rate_limit WHERE action = ? LIMIT 1', [$marker])) {
                return 'validation.altcha_invalid';
            }
            $db->insert('rate_limit', ['ip_address' => 'altcha', 'action' => $marker]);
        } catch (PDOException) {
            // rate_limit table missing: cannot track replays, accept (best effort)
        }

        return null;
    }

    private static function altcha(): Altcha
    {
        // Separate, purpose-bound secrets derived from app_secret.
        return new Altcha(
            hmacSignatureSecret:    hash_hmac('sha256', 'altcha-signature', form_timing_key()),
            hmacKeySignatureSecret: hash_hmac('sha256', 'altcha-key-signature', form_timing_key()),
        );
    }
}
