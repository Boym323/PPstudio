<?php
declare(strict_types=1);

$inline_styles = <<<'CSS'
        .reschedule-shell {
            max-width: 980px;
            margin: 2rem auto;
            padding: 0 1rem 2rem;
        }
        .reschedule-card {
            border: 1px solid var(--color-border-soft);
            border-radius: 24px;
            background: var(--color-surface);
            padding: 1.25rem;
            box-shadow: var(--shadow-soft);
        }
        .reschedule-eyebrow {
            margin: 0;
            text-transform: uppercase;
            letter-spacing: .14em;
            font-size: .78rem;
            color: #7a5a43;
            font-weight: 700;
        }
        .reschedule-card h1 {
            margin: .35rem 0 1rem;
        }
        .reschedule-message {
            margin: 0 0 1rem;
            border-radius: 14px;
            padding: .8rem 1rem;
            border: 1px solid var(--color-border-soft);
            background: #f4eee6;
            color: #513827;
        }
        .reschedule-message.is-helper {
            background: transparent;
            border-color: transparent;
            padding: .2rem 0 1rem;
            color: var(--color-text-muted);
        }
        .reschedule-message.is-success {
            background: #eaf3ed;
            border-color: #b3d2bc;
            color: #2d5a3f;
        }
        .reschedule-message.is-error {
            background: #f8e9e6;
            border-color: #e3b5a9;
            color: #8b3c2e;
        }
        .reschedule-summary {
            margin: 0 0 1rem;
            padding: .9rem 1rem;
            border: 1px solid var(--color-border-soft);
            border-radius: 14px;
            background: #f8f4ed;
            display: grid;
            gap: .2rem;
        }
        .reschedule-confirm-panel {
            margin-top: 1rem;
            border: 1px solid rgba(122, 90, 67, 0.18);
            border-radius: 18px;
            background: linear-gradient(180deg, #fcfaf6 0%, #f8f3eb 100%);
            padding: 1rem 1rem 1.1rem;
            box-shadow: 0 14px 36px rgba(122, 90, 67, 0.08);
            transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease, background .2s ease;
        }
        .reschedule-confirm-panel[data-ready="true"] {
            border-color: rgba(122, 90, 67, 0.34);
            box-shadow: 0 18px 40px rgba(122, 90, 67, 0.14);
            background: linear-gradient(180deg, #fdfbf7 0%, #f7f1e7 100%);
        }
        .reschedule-confirm-title {
            margin: 0 0 .35rem;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--color-text-primary);
        }
        .reschedule-confirm-hint {
            margin: .35rem 0 0;
            color: var(--color-text-muted);
            font-size: .98rem;
        }
        .reschedule-actions {
            display: flex;
            gap: .75rem;
            margin-top: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .reschedule-button,
        .reschedule-link {
            border-radius: 999px;
            border: 1px solid #7a5a43;
            font-weight: 700;
            padding: .82rem 1.45rem;
            text-decoration: none;
            cursor: pointer;
            font-size: 1.02rem;
        }
        .reschedule-button {
            background: #fffaf4 !important;
            border-color: #e0ceb8 !important;
            color: #b7a18d !important;
            box-shadow: 0 8px 18px rgba(122, 90, 67, 0.08);
            min-width: 16rem;
            text-align: center;
            letter-spacing: .01em;
            transition: background-color .2s ease, transform .2s ease, box-shadow .2s ease, border-color .2s ease, color .2s ease;
        }
        .reschedule-button.is-ready,
        .reschedule-button[data-ready="true"] {
            background: #7a5a43 !important;
            border-color: #7a5a43 !important;
            color: #fff !important;
            box-shadow: 0 20px 38px rgba(122, 90, 67, 0.34);
            transform: translateY(-1px);
        }
        .reschedule-button.is-ready:hover,
        .reschedule-button[data-ready="true"]:hover {
            background: #8d684c;
            border-color: #8d684c;
            box-shadow: 0 16px 32px rgba(122, 90, 67, 0.24);
            transform: translateY(-1px);
        }
        .reschedule-button[data-ready="false"] {
            cursor: default;
            background: #fffaf4 !important;
            border-color: #e0ceb8 !important;
            color: #b7a18d !important;
            box-shadow: 0 8px 18px rgba(122, 90, 67, 0.08);
        }
        .reschedule-link {
            background: transparent;
            color: var(--color-text-muted);
            border-color: rgba(122, 90, 67, 0.18);
            font-weight: 600;
            padding: .65rem 1rem;
            font-size: .98rem;
        }
        .reschedule-link:hover {
            background: rgba(255, 255, 255, 0.7);
            color: var(--color-text-primary);
            border-color: rgba(122, 90, 67, 0.26);
        }
        .reschedule-success-card {
            margin-top: 1rem;
        }
        .reschedule-success-card[hidden] {
            display: none !important;
        }
        @media (max-width: 720px) {
            .reschedule-actions {
                flex-direction: column;
                align-items: stretch;
            }
            .reschedule-button,
            .reschedule-link {
                width: 100%;
                text-align: center;
            }
        }
CSS;

$content_template = 'reservation-action-reschedule-content';

require __DIR__ . '/layouts/site-page.php';
