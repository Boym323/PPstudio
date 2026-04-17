<?php
declare(strict_types=1);

namespace PPStudio\Http\View;

final class VoucherPublicPageRenderer
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

        if ($mode === 'voucher_view') {
            $this->renderTemplate('voucher-view-page', $state);
        }

        if ($mode === 'voucher_verify') {
            $this->renderTemplate('voucher-verify-page', $state);
        }

        $this->renderTemplate('voucher-message-page', $state);
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function renderTemplate(string $name, array $variables): never
    {
        extract($variables, EXTR_SKIP);
        require __DIR__ . '/Templates/' . $name . '.php';
        exit;
    }
}
