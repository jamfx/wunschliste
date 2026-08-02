<?php
declare(strict_types=1);

class Translator
{
    private string $locale;
    private array  $translations = [];

    public function __construct(string $locale)
    {
        $this->locale = '';
        $this->setLocale($locale);
    }

    public function setLocale(string $locale): void
    {
        $locale = preg_replace('/[^a-zA-Z_-]/', '', $locale) ?: 'de';

        if ($locale === $this->locale) {
            return;
        }

        $this->locale       = $locale;
        $this->translations = [];
        $this->loadJson($locale);
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Return translated string.
     * Placeholders: {name} is replaced by $replacements['name'].
     * Fallback: returns the key itself (aids debugging missing translations).
     */
    public function t(string $key, array $replacements = []): string
    {
        $value = $this->translations[$key] ?? $key;

        if (!empty($replacements)) {
            foreach ($replacements as $placeholder => $replacement) {
                $value = str_replace('{' . $placeholder . '}', (string) $replacement, $value);
            }
        }

        return $value;
    }

    // ------------------------------------------------------------------

    private function loadJson(string $locale): void
    {
        $path = BASE_DIR . '/lang/' . $locale . '.json';

        // Try exact locale; fall back to 'de' as guaranteed baseline
        if (!file_exists($path)) {
            $path = BASE_DIR . '/lang/de.json';
        }

        if (file_exists($path)) {
            $data = json_decode((string) file_get_contents($path), true);
            if (is_array($data)) {
                $this->translations = $data;
            }
        }
    }
}
