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
        $html = self::renderTemplate('event_list', [
            'event'   => $event,
            'items'   => $items,
            's'       => $s,
            'isAdmin' => $isAdmin,
        ]);

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
