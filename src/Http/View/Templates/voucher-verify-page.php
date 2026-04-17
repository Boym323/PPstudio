<?php
declare(strict_types=1);

$inline_styles = <<<'CSS'
        body { margin: 0; font-family: Georgia, "Times New Roman", serif; background: #f8f2ea; color: #3a2b20; }
        .wrap { max-width: 860px; margin: 34px auto; padding: 0 14px; }
        .card { background: #fffaf4; border: 1px solid #dcc8b5; border-radius: 14px; padding: 20px; }
        .eyebrow { margin: 0; font-size: 12px; letter-spacing: .18em; text-transform: uppercase; color: #7a6558; }
        .status { display: inline-block; padding: 6px 11px; border-radius: 999px; border: 1px solid #c8b29f; background: #efe4d8; font-weight: 700; margin-top: 6px; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px 16px; margin-top: 14px; }
        .row b { display: block; color: #7a6558; font-size: 13px; margin-bottom: 2px; }
        .tools { margin-top: 16px; display: flex; gap: 10px; flex-wrap: wrap; }
        .btn { border: 1px solid #7f593f; border-radius: 999px; padding: 9px 14px; text-decoration: none; color: #2f231c; font-weight: 700; background: #fff; }
        @media (max-width: 760px) { .grid { grid-template-columns: 1fr; } }
CSS;

$content_template = 'voucher-verify-content';

require __DIR__ . '/layouts/site-page.php';
