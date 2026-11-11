<?php
declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;

class PdfExport
{
    /**
     * Generate a PDF for an event and return the raw bytes.
     *
     * @param array $event  Event row from DB
     * @param array $items  Items with 'claims', 'claims_total', 'remaining' attached
     * @param array $s      site_settings key→value
     * @param bool  $isAdmin Whether to include pending items and names (admin view)
     */
    public static function generate(
        array $event,
        array $items,
        array $s,
        bool  $isAdmin = false
    ): string {
        // Dompdf cannot draw emoji from a font (DejaVu Sans has no glyphs for the
        // supplementary-plane ones and the PDF library cannot read them from other
        // fonts either), so emoji in user-entered text become placeholders that are
        // swapped for small SVG images (assets/emoji/, Twemoji) after rendering.
        // Emoji without an image file are dropped instead of showing as empty boxes.
        $event['title'] = self::emojiToTokens((string) ($event['title'] ?? ''));
        if (!empty($event['description'])) {
            $event['description'] = self::emojiToTokens((string) $event['description']);
        }
        foreach ($items as &$item) {
            $item['name'] = self::emojiToTokens((string) ($item['name'] ?? ''));
            if (!empty($item['suggested_by_guest'])) {
                $item['suggested_by_guest'] = self::emojiToTokens((string) $item['suggested_by_guest']);
            }
            foreach ($item['claims'] ?? [] as $i => $claim) {
                $item['claims'][$i]['guest_name'] = self::emojiToTokens((string) ($claim['guest_name'] ?? ''));
            }
        }
        unset($item);

        $html = self::renderTemplate('event_list', [
            'event'   => $event,
            'items'   => $items,
            's'       => $s,
            'isAdmin' => $isAdmin,
        ]);
        $html = self::emojiTokensToImages($html);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('dpi', 96);
        $options->set('defaultMediaType', 'print');
        $options->set('isFontSubsettingEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper(
            $s['pdf_page_size']   ?? 'A4',
            $s['pdf_orientation'] ?? 'portrait'
        );
        $dompdf->render();

        return (string) $dompdf->output();
    }

    private const EMOJI_DIR = '/assets/emoji/';

    /** Matches one emoji (with variation selectors, skin tones, ZWJ parts, keycap). */
    private const EMOJI_PATTERN =
        '/((?:[\x{1F000}-\x{1FAFF}]|[\x{2600}-\x{27BF}\x{2B50}\x{2B55}\x{2B05}-\x{2B07}\x{2139}\x{231A}\x{231B}\x{23E9}-\x{23F3}\x{23F8}-\x{23FA}]\x{FE0F}?)'
        . '(?:\x{FE0E}|\x{FE0F}|\x{20E3}|[\x{1F3FB}-\x{1F3FF}]|\x{200D}(?:[\x{1F000}-\x{1FAFF}]|[\x{2600}-\x{27BF}]\x{FE0F}?))*)([ \t]?)/u';

    /**
     * Replace each emoji by a placeholder naming its image (zzemoji<codepoints>zz),
     * or remove it (plus one following space) if there is no image for it.
     * Symbols that DejaVu Sans can draw itself (e.g. a check mark) are left alone.
     */
    public static function emojiToTokens(string $text): string
    {
        $out = preg_replace_callback(self::EMOJI_PATTERN, static function (array $m): string {
            $cps = [];
            foreach (preg_split('//u', $m[1], -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
                $cps[] = mb_ord($ch, 'UTF-8');
            }
            $isAstral = $cps[0] >= 0x10000;

            // Image key: code points without variation selectors/skin tones/keycap.
            $key = [];
            foreach ($cps as $cp) {
                if ($cp === 0xFE0E || $cp === 0xFE0F || $cp === 0x20E3 || ($cp >= 0x1F3FB && $cp <= 0x1F3FF)) {
                    continue;
                }
                $key[] = dechex($cp);
            }
            foreach ([implode('-', $key), $key[0] ?? ''] as $candidate) {
                if ($candidate !== '' && is_file(BASE_DIR . self::EMOJI_DIR . $candidate . '.svg')) {
                    return 'zzemoji' . $candidate . 'zz' . $m[2];
                }
            }

            // No image. Plain BMP symbols stay as text (DejaVu may know them);
            // everything else would render as an empty box, so drop it.
            return $isAstral || count($cps) > 1 ? '' : $m[0];
        }, $text);

        // Leftover invisible combining characters (e.g. of a keycap like 1 + FE0F + 20E3)
        // would show as boxes in DejaVu Sans.
        return preg_replace('/[\x{FE0E}\x{FE0F}\x{200D}\x{20E3}]/u', '', $out ?? $text) ?? ($out ?? $text);
    }

    /** Swap the placeholders from emojiToTokens() for inline SVG images. */
    private static function emojiTokensToImages(string $html): string
    {
        // Never inside <title> (no markup allowed there).
        $html = preg_replace_callback('~<title>.*?</title>~s', static fn(array $m): string =>
            preg_replace('/zzemoji[0-9a-f-]+zz/', '', $m[0]), $html) ?? $html;

        static $cache = [];
        return preg_replace_callback('/zzemoji([0-9a-f]+(?:-[0-9a-f]+)*)zz/', static function (array $m) use (&$cache): string {
            $key = $m[1];
            if (!isset($cache[$key])) {
                $svg = @file_get_contents(BASE_DIR . self::EMOJI_DIR . $key . '.svg');
                $cache[$key] = $svg === false ? '' : 'data:image/svg+xml;base64,' . base64_encode($svg);
            }
            if ($cache[$key] === '') {
                return '';
            }
            return '<img src="' . $cache[$key] . '" alt="" style="height:1.05em;width:1.05em;vertical-align:-0.15em;">';
        }, $html) ?? $html;
    }

    /** Render a template from templates/pdf/ and return HTML string. */
    private static function renderTemplate(string $name, array $vars): string
    {
        return (static function (string $__path, array $__vars): string {
            extract($__vars);
            ob_start();
            include $__path;
            return (string) ob_get_clean();
        })(BASE_DIR . '/templates/pdf/' . $name . '.php', $vars);
    }
}
