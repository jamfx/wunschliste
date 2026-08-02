<?php
declare(strict_types=1);

class PageController
{
    /** GET /hilfe */
    public function help(array $params = []): void
    {
        global $translator;

        $locale = $translator ? $translator->getLocale() : 'de';

        render('help', [
            'pageTitle'  => __('help.title'),
            'helpLocale' => $locale,
        ]);
    }

    /** GET /legal/imprint */
    public function imprint(array $params = []): void
    {
        global $db;
        $text = '';
        if ($db !== null) {
            $row  = $db->fetch("SELECT setting_value FROM site_settings WHERE setting_key = 'legal_imprint'");
            $text = $row['setting_value'] ?? '';
        }

        if (empty($text)) {
            abort(404);
        }

        render('legal', [
            'pageTitle' => __('legal.imprint_title'),
            'heading'   => __('legal.imprint_title'),
            'content'   => $text,
        ]);
    }

    /** GET /legal/privacy */
    public function privacy(array $params = []): void
    {
        global $db;
        $text = '';
        if ($db !== null) {
            $row  = $db->fetch("SELECT setting_value FROM site_settings WHERE setting_key = 'legal_privacy'");
            $text = $row['setting_value'] ?? '';
        }

        if (empty($text)) {
            abort(404);
        }

        render('legal', [
            'pageTitle' => __('legal.privacy_title'),
            'heading'   => __('legal.privacy_title'),
            'content'   => $text,
        ]);
    }
}
