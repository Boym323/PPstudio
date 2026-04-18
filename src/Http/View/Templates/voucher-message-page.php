<?php
declare(strict_types=1);

$inline_styles = <<<'CSS'
        body { margin: 0; font-family: Georgia, "Times New Roman", serif; background: #f8f2ea; color: #3a2b20; }
        .wrap { max-width: 760px; margin: 44px auto; padding: 0 16px; }
        .wrap.is-narrow { max-width: 700px; padding: 0 14px; }
        .card { background: #fffaf4; border: 1px solid #dcc8b5; border-radius: 20px; padding: 26px; }
CSS;

$__view = array_merge(is_array($__view ?? null) ? $__view : [], [
    'inline_styles' => $inline_styles,
    'content_template' => 'voucher-message-content',
]);

require __DIR__ . '/layouts/site-page.php';
