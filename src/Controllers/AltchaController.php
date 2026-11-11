<?php
declare(strict_types=1);

class AltchaController
{
    // ------------------------------------------------------------------
    // GET /altcha/challenge?f={create_event|suggest}
    // ------------------------------------------------------------------
    public function challenge(array $params = []): void
    {
        $form = (string) ($_GET['f'] ?? '');

        if (!AltchaGuard::enabled() || !in_array($form, AltchaGuard::FORMS, true)) {
            abort(404);
        }

        if (!rate_limit_check('altcha_challenge', 60, 60)) {
            http_response_code(429);
            header('Content-Type: application/json');
            echo '{"error":"rate_limit"}';
            return;
        }
        rate_limit_record('altcha_challenge');

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        try {
            echo json_encode(AltchaGuard::createChallenge($form), JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            // display_errors is off, so log the cause for the PHP error log.
            error_log('ALTCHA challenge failed: ' . get_class($e) . ': ' . $e->getMessage()
                . ' in ' . $e->getFile() . ':' . $e->getLine());
            http_response_code(500);
            echo '{"error":"challenge_failed"}';
        }
    }
}
