<?php
declare(strict_types=1);

$inline_styles = <<<'CSS'
        :root {
            --bg: #f7f0e7;
            --card: rgba(255, 251, 246, 0.95);
            --card-soft: rgba(255, 251, 246, 0.78);
            --line: rgba(219, 200, 181, 0.92);
            --text: #35261d;
            --muted: #7a6659;
            --accent: #7a5a43;
            --accent-soft: #efe3d7;
            --shadow: 0 28px 60px rgba(138, 112, 88, 0.16);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Georgia, "Times New Roman", serif;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(217, 193, 168, 0.18), transparent 34%),
                radial-gradient(circle at top right, rgba(222, 204, 180, 0.18), transparent 28%),
                linear-gradient(180deg, #fbf6f0 0%, var(--bg) 100%);
        }
        .wrap {
            max-width: 980px;
            margin: 0 auto;
            padding: 28px 18px 42px;
        }
        .tools {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 48px;
            padding: 0 18px;
            border-radius: 999px;
            text-decoration: none;
            font-weight: 700;
            border: 1px solid transparent;
            cursor: pointer;
            font-family: inherit;
            font-size: 1rem;
        }
        .btn-primary {
            background: var(--accent);
            color: #fff;
            box-shadow: 0 14px 28px rgba(122, 90, 67, 0.18);
        }
        .btn-secondary {
            background: #fff;
            color: var(--text);
            border-color: var(--line);
        }
        .voucher-shell {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 28px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        .voucher-hero {
            padding: 34px 34px 28px;
            background:
                radial-gradient(circle at top right, rgba(236, 223, 210, 0.85), transparent 34%),
                linear-gradient(180deg, rgba(255, 252, 248, 0.98), rgba(250, 243, 234, 0.92));
            border-bottom: 1px solid var(--line);
        }
        .eyebrow {
            margin: 0 0 14px;
            font-size: 0.82rem;
            letter-spacing: 0.28rem;
            text-transform: uppercase;
            color: var(--muted);
            font-weight: 700;
        }
        h1 {
            margin: 0;
            font-size: clamp(2.6rem, 6vw, 4.5rem);
            line-height: 0.96;
            max-width: 12ch;
        }
        .hero-copy {
            max-width: 38rem;
            margin-top: 18px;
            font-size: 1.15rem;
            line-height: 1.65;
            color: #56483d;
        }
        .voucher-body {
            padding: 28px 34px 34px;
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(280px, 0.9fr);
            gap: 22px;
        }
        .voucher-panel,
        .voucher-card {
            background: var(--card-soft);
            border: 1px solid var(--line);
            border-radius: 22px;
        }
        .voucher-panel {
            padding: 26px 24px;
            display: grid;
            gap: 18px;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: fit-content;
            padding: 8px 14px;
            border-radius: 999px;
            background: var(--accent-soft);
            color: var(--accent);
            font-weight: 700;
            border: 1px solid rgba(184, 157, 135, 0.7);
        }
        .voucher-value {
            font-size: clamp(2rem, 4vw, 3rem);
            line-height: 1;
            color: var(--accent);
            font-weight: 700;
        }
        .voucher-grid {
            display: grid;
            gap: 14px;
        }
        .voucher-grid-item {
            padding: 16px 18px;
            border: 1px solid rgba(219, 200, 181, 0.88);
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.55);
        }
        .voucher-grid-item strong {
            display: block;
            margin-bottom: 6px;
            color: var(--muted);
            font-size: 0.88rem;
            letter-spacing: 0.04rem;
            text-transform: uppercase;
        }
        .voucher-grid-item span {
            font-size: 1.18rem;
            line-height: 1.45;
        }
        .voucher-card {
            padding: 26px 24px;
            display: grid;
            align-content: start;
            gap: 18px;
        }
        .voucher-card h2 {
            margin: 0;
            font-size: 1.8rem;
        }
        .voucher-card p {
            margin: 0;
            font-size: 1rem;
            line-height: 1.7;
            color: #5d5046;
        }
        .voucher-actions {
            display: grid;
            gap: 10px;
            margin-top: 6px;
        }
        .voucher-footnote {
            font-size: 0.94rem;
            color: var(--muted);
        }
        .print-only {
            display: none;
        }
        .voucher-code-box {
            padding: 16px 18px;
            border-radius: 18px;
            border: 1px solid rgba(214, 194, 175, 0.9);
            background: #fff;
        }
        .voucher-code-box strong {
            display: block;
            margin-bottom: 6px;
            color: var(--muted);
            font-size: 0.82rem;
            letter-spacing: 0.12rem;
            text-transform: uppercase;
        }
        .voucher-code-box span {
            font-size: 1.55rem;
            font-weight: 700;
            letter-spacing: 0.04rem;
        }
        .voucher-print-page {
            width: 210mm;
            height: 99mm;
            margin: 0 auto;
            background: #fffaf4;
            border: 1px solid #dbc8b5;
            border-radius: 12px;
            overflow: hidden;
            grid-template-columns: 1.35fr 0.65fr;
        }
        .voucher-print-main {
            padding: 12mm 13mm 10mm;
            display: grid;
            grid-template-rows: auto auto 1fr auto;
            gap: 4mm;
        }
        .voucher-print-brand {
            font-size: 11pt;
            letter-spacing: .24em;
            text-transform: uppercase;
            color: #7a6558;
            font-weight: 700;
        }
        .voucher-print-title {
            font-size: 25pt;
            line-height: 1.05;
            margin: 0;
            color: #2f231c;
        }
        .voucher-print-value {
            font-size: 34pt;
            color: #7f593f;
            font-weight: 700;
            line-height: 1;
        }
        .voucher-print-meta {
            display: grid;
            gap: 2mm;
            font-size: 12pt;
        }
        .voucher-print-meta-row b {
            display: inline-block;
            min-width: 34mm;
            color: #7a6558;
            font-weight: 700;
        }
        .voucher-print-side {
            border-left: 1px solid #dbc8b5;
            background: linear-gradient(180deg, #f1e5d8 0%, #fffaf4 100%);
            display: grid;
            place-items: center;
            padding: 8mm;
            gap: 3mm;
            text-align: center;
        }
        .voucher-print-qr {
            width: 44mm;
            height: 44mm;
            background: #fff;
            border: 1px solid #dbc8b5;
            border-radius: 8px;
            object-fit: cover;
        }
        .voucher-print-caption {
            font-size: 9.5pt;
            color: #7a6558;
            line-height: 1.25;
        }
        @media print {
            body {
                background: #fff;
            }
            .wrap {
                padding: 0;
                max-width: none;
            }
            .tools {
                display: none !important;
            }
            .screen-only {
                display: none !important;
            }
            .print-only {
                display: grid !important;
            }
            .voucher-print-page {
                margin: 0;
                border: 0;
                border-radius: 0;
            }
            @page {
                size: 210mm 99mm;
                margin: 0;
            }
        }
        @media (max-width: 820px) {
            .voucher-body {
                grid-template-columns: 1fr;
                padding: 20px 18px 24px;
            }
            .voucher-hero {
                padding: 26px 18px 22px;
            }
            h1 {
                max-width: 10ch;
            }
        }
CSS;

$content_template = 'voucher-view-content';

require __DIR__ . '/layouts/site-page.php';
