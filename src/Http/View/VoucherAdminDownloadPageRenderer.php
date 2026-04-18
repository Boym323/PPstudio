<?php
declare(strict_types=1);

namespace PPStudio\Http\View;

final class VoucherAdminDownloadPageRenderer
{
    /**
     * @param array<string, mixed> $state
     */
    public function render(array $state): never
    {
        $httpCode = (int) ($state['http_code'] ?? 200);
        if ($httpCode !== 200) {
            http_response_code($httpCode);
        }

        $mode = (string) ($state['mode'] ?? 'message_page');
        if ($mode === 'plain_text') {
            echo (string) ($state['message'] ?? 'Nepodařilo se zpracovat požadavek.');
            exit;
        }
        if ($mode === 'pdf_download') {
            $filename = trim((string) ($state['pdf_filename'] ?? 'voucher.pdf'));
            if ($filename === '') {
                $filename = 'voucher.pdf';
            }
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
            echo (string) ($state['pdf_content'] ?? '');
            exit;
        }

        if ($mode === 'voucher_dl') {
            $this->renderTemplate('voucher-dl-page', $state);
        }

        $this->renderTemplate('voucher-message-page', $state);
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function renderTemplate(string $name, array $variables): never
    {
        $__view = $variables;
        require __DIR__ . '/Templates/' . $name . '.php';
        exit;
    }
}
