<?php
declare(strict_types=1);

namespace PPStudio\Http\View;

final class SiteTrustedSingleIframeEmbedRenderer
{
    public function render(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        if (! preg_match('/<iframe\b[^>]*\bsrc=["\']([^"\']+)["\'][^>]*>\s*<\/iframe>/is', $html, $matches)) {
            return '';
        }

        $src = trim((string) ($matches[1] ?? ''));
        if ($src === '' || ! preg_match('#^https?://#i', $src)) {
            return '';
        }

        $safeSrc = \PPStudio\Support\ViewHelper::escape($src);

        return '<iframe src="' . $safeSrc . '" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>';
    }
}
