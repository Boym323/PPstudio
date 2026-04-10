<?php
declare(strict_types=1);

require __DIR__ . '/config/app.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/security_events.php';
require __DIR__ . '/includes/settings.php';
require __DIR__ . '/includes/mailer.php';
require __DIR__ . '/includes/availability.php';

$dbConfig = require __DIR__ . '/config/database.php';
$emailConfig = require __DIR__ . '/config/email.php';

$reservationId = (int) ($_REQUEST['id'] ?? 0);
$action = trim((string) ($_REQUEST['action'] ?? ''));
$expiresAt = (int) ($_REQUEST['exp'] ?? 0);
$nonce = trim((string) ($_REQUEST['nonce'] ?? ''));
$signature = trim((string) ($_REQUEST['sig'] ?? ''));
$isPost = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
$linkIsValid = $action === 'reschedule'
    && isValidReservationActionSignature($emailConfig, $reservationId, $action, $expiresAt, $nonce, $signature);

$pageTitle = defaultSiteName() . ' | Přesun termínu';
$message = '';
$messageType = 'info';
$showForm = false;
$reservation = null;
$siteSettings = [];

if (! $linkIsValid) {
    http_response_code(403);
    $message = 'Odkaz je neplatný nebo expiroval.';
    $messageType = 'error';
    securityEventLog('reservation_customer_reschedule_invalid_link', 'reservation_reschedule', 'warning', [
        'reservation_id' => $reservationId,
    ]);
} else {
    $connection = @new mysqli(
        $dbConfig['host'],
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['database']
    );

    if ($connection->connect_errno) {
        http_response_code(500);
        $message = 'Databáze není dostupná.';
        $messageType = 'error';
    } else {
        $connection->set_charset($dbConfig['charset']);
        $siteSettings = loadSiteSettings($connection);
        $reservation = loadReservationDetails($connection, $reservationId);

        if ($reservation === null) {
            http_response_code(404);
            $message = 'Rezervace nebyla nalezena.';
            $messageType = 'error';
        } else {
            $statusBefore = (string) ($reservation['stav'] ?? '');

            if (! $isPost) {
                if ($statusBefore === 'zrusena') {
                    $message = 'Tato rezervace už je zrušena.';
                } elseif ($statusBefore === 'dokoncena') {
                    $message = 'Tato rezervace je již dokončená a nelze ji přesouvat.';
                    $messageType = 'error';
                } else {
                    $message = 'Vyberte nový termín rezervace.';
                    $showForm = true;
                }
            } else {
                if ($statusBefore === 'zrusena' || $statusBefore === 'dokoncena') {
                    $message = 'Tuto rezervaci už nelze přesunout.';
                    $messageType = 'error';
                } else {
                    $newDate = trim((string) ($_POST['rezervacni_datum'] ?? ''));
                    $newTime = trim((string) ($_POST['rezervacni_cas'] ?? ''));

                    if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDate) || ! preg_match('/^\d{2}:\d{2}$/', $newTime)) {
                        $message = 'Vyberte prosím platný den a čas.';
                        $messageType = 'error';
                        $showForm = true;
                    } else {
                        $newDateTime = $newDate . ' ' . $newTime . ':00';
                        $oldDateTime = (string) ($reservation['datum_cas'] ?? '');
                        $newTimestamp = strtotime($newDateTime);
                        $oldTimestamp = strtotime($oldDateTime);

                        if (! $newTimestamp || ! $oldTimestamp) {
                            $message = 'Termín se nepodařilo zpracovat.';
                            $messageType = 'error';
                            $showForm = true;
                        } elseif (date('Y-m-d H:i', $newTimestamp) === date('Y-m-d H:i', $oldTimestamp)) {
                            $message = 'Zvolte prosím jiný termín než původní.';
                            $messageType = 'error';
                            $showForm = true;
                        } elseif (! isValidReservationSlot($connection, (int) ($reservation['service_id'] ?? 0), $newDateTime)) {
                            $message = 'Zvolený termín už není dostupný. Vyberte prosím jiný.';
                            $messageType = 'error';
                            $showForm = true;
                        } else {
                            if (! consumeReservationActionNonce($reservationId, 'reschedule', $expiresAt, $nonce)) {
                                http_response_code(403);
                                $message = 'Odkaz už byl použit nebo expiroval.';
                                $messageType = 'error';
                                $showForm = false;
                            } else {
                                $update = $connection->prepare(
                                    'UPDATE rezervace
                                     SET datum_cas = ?
                                     WHERE id = ?
                                     LIMIT 1'
                                );

                                if (! $update) {
                                    http_response_code(500);
                                    $message = 'Termín se nepodařilo změnit.';
                                    $messageType = 'error';
                                    $showForm = true;
                                } else {
                                    $update->bind_param('si', $newDateTime, $reservationId);
                                    if (! $update->execute()) {
                                        http_response_code(500);
                                        $message = 'Termín se nepodařilo změnit.';
                                        $messageType = 'error';
                                        $showForm = true;
                                    } else {
                                        $reservationAfter = loadReservationDetails($connection, $reservationId);
                                        if ($reservationAfter !== null) {
                                            sendReservationConfirmedEmail($emailConfig, $siteSettings, $reservationAfter, [
                                                'previous_datetime' => $oldDateTime,
                                            ]);
                                            $reservation = $reservationAfter;
                                        }
                                        $message = 'Termín byl úspěšně změněn. Potvrzení jsme poslali i na váš e-mail.';
                                        $messageType = 'success';
                                        securityEventLog('reservation_customer_rescheduled', 'reservation_reschedule', 'info', [
                                            'reservation_id' => $reservationId,
                                            'old_datetime' => $oldDateTime,
                                            'new_datetime' => $newDateTime,
                                        ]);
                                    }
                                    $update->close();
                                }
                            }
                        }
                    }
                }
            }
        }

        $connection->close();
    }
}

$siteName = setting($siteSettings, 'site_name', defaultSiteName());
$serviceName = (string) ($reservation['service_name'] ?? 'Rezervace');
$serviceDuration = (int) ($reservation['service_duration'] ?? 0);
$serviceLabel = $serviceName . ($serviceDuration > 0 ? ' (' . $serviceDuration . ' min)' : '');
$currentDateTime = formatCzechDateTime((string) ($reservation['datum_cas'] ?? ''));
$currentDateTimeMachine = '';
if ($reservation !== null && isset($reservation['datum_cas'])) {
    $ts = strtotime((string) $reservation['datum_cas']);
    if ($ts) {
        $currentDateTimeMachine = date('Y-m-d H:i', $ts);
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($pageTitle) ?></title>
    <link rel="stylesheet" href="frontend/site.css">
    <style>
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
    </style>
</head>
<body>
    <div class="reschedule-shell">
        <section class="reschedule-card">
            <p class="reschedule-eyebrow">Online rezervace</p>
            <h1>Přesun termínu</h1>

            <p class="reschedule-message<?= $messageType === 'success' ? ' is-success' : ($messageType === 'error' ? ' is-error' : ' is-helper') ?>" data-reschedule-message>
                <?= escape($message) ?>
            </p>

            <?php if ($reservation !== null): ?>
                <div class="reschedule-summary">
                    <div><strong>Služba:</strong> <?= escape($serviceLabel) ?></div>
                    <div><strong>Aktuální termín:</strong> <?= escape($currentDateTime !== '' ? $currentDateTime : '—') ?></div>
                </div>
            <?php endif; ?>

            <?php if ($showForm && $reservation !== null): ?>
                <form method="post" data-reschedule-form>
                    <input type="hidden" name="id" value="<?= escape((string) $reservationId) ?>">
                    <input type="hidden" name="action" value="<?= escape($action) ?>">
                    <input type="hidden" name="exp" value="<?= escape((string) $expiresAt) ?>">
                    <input type="hidden" name="nonce" value="<?= escape($nonce) ?>">
                    <input type="hidden" name="sig" value="<?= escape($signature) ?>">

                    <div class="reservation-calendar" data-reservation-calendar>
                        <div class="reservation-calendar-head">
                            <button type="button" class="reservation-calendar-nav" data-calendar-prev aria-label="Předchozí měsíc">‹</button>
                            <strong data-calendar-month>Načítám termíny…</strong>
                            <button type="button" class="reservation-calendar-nav" data-calendar-next aria-label="Další měsíc">›</button>
                        </div>
                        <div class="reservation-calendar-weekdays" aria-hidden="true">
                            <span>Po</span><span>Út</span><span>St</span><span>Čt</span><span>Pá</span><span>So</span><span>Ne</span>
                        </div>
                        <div class="reservation-calendar-grid" data-calendar-grid>
                            <div class="reservation-calendar-empty">Načítám dostupné dny…</div>
                        </div>
                        <label class="reservation-day-select-hidden">
                            Den
                            <select name="rezervacni_datum" required data-day-select disabled>
                                <option value="">Načítám dny…</option>
                            </select>
                        </label>
                    </div>

                    <div class="reservation-time-picker" data-time-picker>
                        <p class="reservation-time-title">Čas</p>
                        <div class="reservation-time-slots" data-time-slots>
                            <div class="reservation-calendar-empty">Nejprve vyberte den.</div>
                        </div>
                        <label class="reservation-day-select-hidden">
                            Čas
                            <select name="rezervacni_cas" required data-time-select disabled>
                                <option value="">Nejprve vyberte den</option>
                            </select>
                        </label>
                    </div>

                    <div class="reschedule-confirm-panel" data-reschedule-confirm data-ready="false">
                        <p class="reschedule-confirm-title">Potvrzení nového termínu</p>
                        <div class="reservation-picked-slot" data-picked-slot>
                            <strong>Nový vybraný termín:</strong>
                            <span data-picked-slot-value>Zatím není vybraný den a čas.</span>
                        </div>
                        <p class="reschedule-confirm-hint" data-reschedule-hint>Nejprve vyberte nový den a čas, potom potvrďte přesun.</p>
                        <div class="reschedule-actions">
                            <button class="reschedule-button" type="submit" aria-disabled="true" data-reschedule-submit data-ready="false">Potvrdit přesun</button>
                            <a class="reschedule-link" href="/index.php#rezervace">Ponechat původní termín</a>
                        </div>
                    </div>
                </form>

                <script>
                    (() => {
                        const form = document.querySelector('[data-reschedule-form]');
                        if (!form) return;

                        const serviceId = <?= (int) ($reservation['service_id'] ?? 0) ?>;
                        const originalDateTime = <?= json_encode($currentDateTimeMachine, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
                        const daySelect = form.querySelector('[data-day-select]');
                        const timeSelect = form.querySelector('[data-time-select]');
                        const pickedSlotValue = form.querySelector('[data-picked-slot-value]');
                        const calendarGrid = form.querySelector('[data-calendar-grid]');
                        const calendarMonth = form.querySelector('[data-calendar-month]');
                        const calendarPrev = form.querySelector('[data-calendar-prev]');
                        const calendarNext = form.querySelector('[data-calendar-next]');
                        const timeSlots = form.querySelector('[data-time-slots]');
                        const submitButton = form.querySelector('[data-reschedule-submit]');
                        const messageBox = document.querySelector('[data-reschedule-message]');
                        const hintBox = form.querySelector('[data-reschedule-hint]');
                        const confirmPanel = form.querySelector('[data-reschedule-confirm]');
                        let availableDays = [];
                        let availableDayMap = new Map();
                        let calendarMonths = [];
                        let activeCalendarMonthIndex = 0;

                        const setOptions = (select, items, placeholder) => {
                            if (!select) return;
                            select.innerHTML = '';
                            const first = document.createElement('option');
                            first.value = '';
                            first.textContent = placeholder;
                            select.appendChild(first);
                            items.forEach((item) => {
                                const option = document.createElement('option');
                                option.value = item.value;
                                option.textContent = item.label;
                                select.appendChild(option);
                            });
                            select.disabled = items.length === 0;
                        };

                        const selectedOptionText = (select) => {
                            if (!select || !String(select.value || '').trim()) return '';
                            const selected = select.options[select.selectedIndex];
                            return selected ? String(selected.textContent || '').trim() : '';
                        };

                        const createDateFromKey = (value) => {
                            const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
                            if (!match) return null;
                            return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
                        };

                        const fetchJson = async (url) => {
                            const res = await fetch(url, { headers: { 'X-Requested-With': 'fetch' } });
                            if (!res.ok) throw new Error('load_failed');
                            return res.json();
                        };

                        const updatePickedLabel = () => {
                            const day = selectedOptionText(daySelect);
                            const time = selectedOptionText(timeSelect);
                            if (!pickedSlotValue) return;
                            pickedSlotValue.textContent = (day && time) ? `${day} v ${time}` : 'Zatím není vybraný den a čas.';
                            if (!submitButton) return;
                            const hasNewTerm = day && time && (originalDateTime === '' || `${String(daySelect?.value || '')} ${String(timeSelect?.value || '')}` !== originalDateTime);
                            if (hasNewTerm) {
                                submitButton.classList.add('is-ready');
                                submitButton.setAttribute('data-ready', 'true');
                                submitButton.setAttribute('aria-disabled', 'false');
                                if (confirmPanel) {
                                    confirmPanel.setAttribute('data-ready', 'true');
                                }
                                if (hintBox) {
                                    hintBox.textContent = 'Nový termín je připravený. Pokud vám vyhovuje, potvrďte přesun.';
                                }
                                if (messageBox && messageBox.classList.contains('is-error')) {
                                    messageBox.textContent = 'Nový termín je připravený k potvrzení.';
                                    messageBox.classList.remove('is-error');
                                    messageBox.classList.add('is-helper');
                                }
                            } else {
                                submitButton.classList.remove('is-ready');
                                submitButton.setAttribute('data-ready', 'false');
                                submitButton.setAttribute('aria-disabled', 'true');
                                if (confirmPanel) {
                                    confirmPanel.setAttribute('data-ready', 'false');
                                }
                                if (hintBox) {
                                    hintBox.textContent = 'Nejprve vyberte nový den a čas, potom potvrďte přesun.';
                                }
                            }
                        };

                        const renderTimeSlots = (items, selectedValue = '') => {
                            if (!timeSlots || !timeSelect) return;
                            const slots = Array.isArray(items) ? items : [];
                            if (slots.length === 0) {
                                timeSlots.innerHTML = '<div class="reservation-calendar-empty">Pro tento den nejsou volné časy.</div>';
                                return;
                            }
                            timeSlots.innerHTML = slots.map((slot) => {
                                const value = String(slot?.value || '');
                                const label = String(slot?.label || value);
                                const isSelected = value !== '' && value === selectedValue;
                                return `<button type="button" class="reservation-time-chip${isSelected ? ' is-selected' : ''}" data-time-value="${value}" aria-pressed="${isSelected ? 'true' : 'false'}">${label}</button>`;
                            }).join('');
                            timeSlots.querySelectorAll('[data-time-value]').forEach((button) => {
                                button.addEventListener('click', () => {
                                    const value = String(button.getAttribute('data-time-value') || '');
                                    if (!value) return;
                                    timeSelect.value = value;
                                    renderTimeSlots(slots, value);
                                    updatePickedLabel();
                                });
                            });
                        };

                        const renderCalendar = () => {
                            if (!calendarGrid || !calendarMonth) return;
                            if (availableDays.length === 0 || calendarMonths.length === 0) {
                                calendarMonth.textContent = 'Bez dostupných termínů';
                                calendarGrid.innerHTML = '<div class="reservation-calendar-empty">Pro tuto rezervaci teď nejsou volné termíny.</div>';
                                if (calendarPrev) calendarPrev.disabled = true;
                                if (calendarNext) calendarNext.disabled = true;
                                return;
                            }

                            const monthKey = calendarMonths[activeCalendarMonthIndex];
                            const [yearRaw, monthRaw] = monthKey.split('-');
                            const year = Number(yearRaw);
                            const month = Number(monthRaw);
                            const firstDay = new Date(year, month - 1, 1);
                            const daysInMonth = new Date(year, month, 0).getDate();
                            const mondayBasedWeekday = ((firstDay.getDay() + 6) % 7) + 1;
                            const monthLabel = new Intl.DateTimeFormat('cs-CZ', { month: 'long', year: 'numeric' }).format(firstDay);
                            const today = new Date();
                            today.setHours(0, 0, 0, 0);
                            const selectedDay = String(daySelect?.value || '');

                            calendarMonth.textContent = monthLabel.charAt(0).toUpperCase() + monthLabel.slice(1);
                            if (calendarPrev) calendarPrev.disabled = activeCalendarMonthIndex <= 0;
                            if (calendarNext) calendarNext.disabled = activeCalendarMonthIndex >= calendarMonths.length - 1;

                            const cells = [];
                            for (let i = 1; i < mondayBasedWeekday; i += 1) {
                                cells.push('<span class="reservation-calendar-spacer" aria-hidden="true"></span>');
                            }

                            for (let day = 1; day <= daysInMonth; day += 1) {
                                const dateKey = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                                const thisDate = createDateFromKey(dateKey);
                                const isAvailable = availableDayMap.has(dateKey);
                                const isPast = thisDate ? thisDate.getTime() < today.getTime() : false;
                                const isSelectable = isAvailable && !isPast;
                                const isSelected = selectedDay === dateKey;
                                const label = availableDayMap.get(dateKey) || dateKey;
                                cells.push(`<button type="button" class="reservation-calendar-day${isSelected ? ' is-selected' : ''}${!isSelectable ? ' is-disabled' : ''}" data-calendar-date="${dateKey}" ${isSelectable ? '' : 'disabled'} aria-label="${label}">${day}</button>`);
                            }

                            calendarGrid.innerHTML = cells.join('');
                            calendarGrid.querySelectorAll('[data-calendar-date]').forEach((button) => {
                                button.addEventListener('click', () => {
                                    const dateValue = String(button.getAttribute('data-calendar-date') || '');
                                    if (!dateValue || !daySelect) return;
                                    daySelect.value = dateValue;
                                    renderCalendar();
                                    loadTimes(dateValue);
                                    updatePickedLabel();
                                });
                            });
                        };

                        const updateCalendarDays = (days) => {
                            availableDays = Array.isArray(days) ? days : [];
                            availableDayMap = new Map();
                            availableDays.forEach((day) => {
                                const value = String(day?.value || '');
                                if (!value) return;
                                availableDayMap.set(value, String(day?.label || value));
                            });
                            calendarMonths = Array.from(new Set(
                                availableDays
                                    .map((day) => String(day?.value || '').slice(0, 7))
                                    .filter((key) => /^\d{4}-\d{2}$/.test(key))
                            )).sort();
                            const selectedMonthKey = String(daySelect?.value || '').slice(0, 7);
                            const selectedIndex = calendarMonths.indexOf(selectedMonthKey);
                            activeCalendarMonthIndex = selectedIndex >= 0 ? selectedIndex : 0;
                            renderCalendar();
                        };

                        const loadTimes = async (day) => {
                            setOptions(timeSelect, [], 'Načítání časů…');
                            if (!day) {
                                setOptions(timeSelect, [], 'Nejprve vyberte den');
                                if (timeSlots) {
                                    timeSlots.innerHTML = '<div class="reservation-calendar-empty">Nejprve vyberte den.</div>';
                                }
                                updatePickedLabel();
                                return;
                            }
                            try {
                                const payload = await fetchJson(`/api/availability.php?service_id=${encodeURIComponent(String(serviceId))}&date=${encodeURIComponent(day)}`);
                                const times = Array.isArray(payload.times) ? payload.times : [];
                                setOptions(timeSelect, times, times.length ? 'Vyberte čas' : 'Pro tento den nejsou časy');
                                renderTimeSlots(times, String(timeSelect.value || ''));
                            } catch (_) {
                                setOptions(timeSelect, [], 'Časy se nepodařilo načíst');
                                if (timeSlots) {
                                    timeSlots.innerHTML = '<div class="reservation-calendar-empty">Časy se nepodařilo načíst.</div>';
                                }
                            }
                            updatePickedLabel();
                        };

                        const init = async () => {
                            setOptions(daySelect, [], 'Načítání dnů…');
                            setOptions(timeSelect, [], 'Nejprve vyberte den');
                            try {
                                const payload = await fetchJson(`/api/availability.php?service_id=${encodeURIComponent(String(serviceId))}`);
                                const days = Array.isArray(payload.days) ? payload.days : [];
                                setOptions(daySelect, days, days.length ? 'Vyberte den' : 'Pro tuto službu nejsou termíny');
                                updateCalendarDays(days);
                            } catch (_) {
                                setOptions(daySelect, [], 'Dny se nepodařilo načíst');
                                updateCalendarDays([]);
                            }
                            updatePickedLabel();
                        };

                        daySelect?.addEventListener('change', () => {
                            loadTimes(daySelect.value);
                            updateCalendarDays(availableDays);
                            updatePickedLabel();
                        });
                        timeSelect?.addEventListener('change', updatePickedLabel);

                        if (calendarPrev) {
                            calendarPrev.addEventListener('click', () => {
                                if (activeCalendarMonthIndex > 0) {
                                    activeCalendarMonthIndex -= 1;
                                    renderCalendar();
                                }
                            });
                        }
                        if (calendarNext) {
                            calendarNext.addEventListener('click', () => {
                                if (activeCalendarMonthIndex < calendarMonths.length - 1) {
                                    activeCalendarMonthIndex += 1;
                                    renderCalendar();
                                }
                            });
                        }

                        form.addEventListener('submit', (event) => {
                            const isReady = submitButton?.getAttribute('data-ready') === 'true';
                            const day = String(daySelect?.value || '');
                            const time = String(timeSelect?.value || '');
                            if (!day || !time || !isReady) {
                                event.preventDefault();
                                alert('Nejprve vyberte den a čas.');
                                return;
                            }
                            if (originalDateTime !== '' && `${day} ${time}` === originalDateTime) {
                                event.preventDefault();
                                alert('Zvolte prosím jiný termín než původní.');
                            }
                        });

                        init();
                    })();
                </script>
            <?php else: ?>
                <div class="reschedule-actions">
                    <a class="reschedule-link" href="/index.php#rezervace">Zpět na web</a>
                </div>
            <?php endif; ?>
        </section>
    </div>
</body>
</html>
