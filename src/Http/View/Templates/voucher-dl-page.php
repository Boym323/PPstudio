<?php
declare(strict_types=1);

$inline_styles = <<<'CSS'
        :root {
            --bg: #f6f1ea;
            --card: #fffaf4;
            --text: #2f231c;
            --muted: #7a6558;
            --accent: #7f593f;
            --line: #dbc8b5;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: var(--bg); color: var(--text); font-family: Georgia, "Times New Roman", serif; }
        .screen-tools {
            max-width: 920px;
            margin: 18px auto 10px;
            padding: 0 12px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .screen-tools button, .screen-tools a {
            border: 1px solid var(--accent);
            border-radius: 999px;
            padding: 9px 14px;
            background: #fff;
            color: var(--text);
            text-decoration: none;
            font-weight: 700;
            cursor: pointer;
        }
        .voucher-page {
            width: 210mm;
            height: 99mm;
            margin: 8px auto 22px;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 12px;
            overflow: hidden;
            display: grid;
            grid-template-columns: 1.35fr 0.65fr;
        }
        .voucher-main {
            padding: 12mm 13mm 10mm;
            display: grid;
            grid-template-rows: auto auto 1fr auto;
            gap: 4mm;
        }
        .brand {
            font-size: 11pt;
            letter-spacing: .24em;
            text-transform: uppercase;
            color: var(--muted);
            font-weight: 700;
        }
        .title {
            font-size: 25pt;
            line-height: 1.05;
            margin: 0;
            color: var(--text);
        }
        .value {
            font-size: 34pt;
            color: var(--accent);
            font-weight: 700;
            line-height: 1;
        }
        .meta {
            display: grid;
            gap: 2mm;
            font-size: 12pt;
        }
        .meta-row b {
            display: inline-block;
            min-width: 34mm;
            color: var(--muted);
            font-weight: 700;
        }
        .note {
            font-size: 10.5pt;
            color: var(--muted);
            border-top: 1px dashed var(--line);
            padding-top: 2.6mm;
            margin-top: 1.5mm;
            line-height: 1.35;
            max-height: 24mm;
            overflow: hidden;
        }
        .voucher-side {
            border-left: 1px solid var(--line);
            background: linear-gradient(180deg, #f1e5d8 0%, #fffaf4 100%);
            display: grid;
            place-items: center;
            padding: 8mm;
            gap: 3mm;
            text-align: center;
        }
        .qr {
            width: 44mm;
            height: 44mm;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 8px;
            object-fit: cover;
        }
        .qr-caption {
            font-size: 9.5pt;
            color: var(--muted);
            line-height: 1.25;
        }
        @page {
            size: 210mm 99mm;
            margin: 0;
        }
        @media print {
            html, body {
                background: #fff;
                width: 210mm;
                height: 99mm;
            }
            .screen-tools { display: none !important; }
            .voucher-page {
                margin: 0;
                border: 0;
                border-radius: 0;
            }
        }
CSS;

$__view = array_merge(is_array($__view ?? null) ? $__view : [], [
    'inline_styles' => $inline_styles,
    'content_template' => 'voucher-dl-content',
]);

require __DIR__ . '/layouts/site-page.php';
