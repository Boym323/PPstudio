<?php
$rawMapSetting = trim(setting($siteSettings, 'contact_map_url', ''));
$mapIframeSrc = '';

if ($rawMapSetting !== '') {
    if (preg_match('/<iframe\b[^>]*\bsrc=["\']([^"\']+)["\'][^>]*>/i', $rawMapSetting, $matches)) {
        $mapIframeSrc = trim((string) ($matches[1] ?? ''));
    } else {
        $mapIframeSrc = $rawMapSetting;
    }
}

if ($mapIframeSrc === '') {
    $mapIframeSrc = 'https://mapy.com/';
}

$mapIframeSrc = preg_replace('#^https?://frame\.mapy\.cz/s/([a-z0-9]+)$#i', 'https://mapy.com/s/$1', $mapIframeSrc) ?: $mapIframeSrc;
?>

<section class="contact-section" id="contact" style="padding-top:8.5rem;">
    <div class="contact-container">
        <h2 class="contact-title">Rezervace a kontakt</h2>
        <div class="reservation-box">
            <h3>Online rezervace</h3>
            <p>Vyberte službu, termín a vyplňte kontakt. Potvrzení rezervace vám dorazí e-mailem.</p>
            <?= $reservationAlertHtml ?>
            <form class="reservation-form" method="post" action="/reservation-submit.php" data-reservation-form>
                <input type="hidden" name="_csrf" value="<?= escape($csrfToken) ?>">
                <input type="hidden" name="reservation_token" value="<?= escape($reservationAntispamToken) ?>">
                <label class="hp-field" aria-hidden="true">
                    Vaše webová stránka
                    <input type="text" name="website" tabindex="-1" autocomplete="off">
                </label>
                <div class="reservation-stepper" data-reservation-stepper>
                    <div class="reservation-step-indicator" aria-label="Průběh formuláře">
                        <span class="step-chip is-active" data-step-indicator="1">1. Služba a termín</span>
                        <span class="step-chip" data-step-indicator="2">2. Kontakt</span>
                        <span class="step-chip" data-step-indicator="3">3. Potvrzení</span>
                    </div>

                    <section class="reservation-step is-active" data-step="1">
                        <div class="reservation-service-panel">
                            <div class="reservation-grid">
                                <label class="reservation-service-field">
                                    Služba
                                    <span class="reservation-service-select-wrap">
                                        <select name="sluzba_id" required data-service-select>
                                            <option value="">Načítám služby…</option>
                                        </select>
                                        <span class="reservation-service-select-icon" aria-hidden="true">⌄</span>
                                    </span>
                                </label>
                            </div>
                            <div class="reservation-service-meta" data-service-meta>
                                <span data-service-meta-category>Kategorie: —</span>
                                <span data-service-meta-duration>Délka: —</span>
                                <span data-service-meta-price>Cena: —</span>
                            </div>
                        </div>
                        <div class="reservation-calendar" data-reservation-calendar>
                            <div class="reservation-calendar-head">
                                <button type="button" class="reservation-calendar-nav" data-calendar-prev aria-label="Předchozí měsíc">‹</button>
                                <strong data-calendar-month>Vyberte službu</strong>
                                <button type="button" class="reservation-calendar-nav" data-calendar-next aria-label="Další měsíc">›</button>
                            </div>
                            <div class="reservation-calendar-weekdays" aria-hidden="true">
                                <span>Po</span><span>Út</span><span>St</span><span>Čt</span><span>Pá</span><span>So</span><span>Ne</span>
                            </div>
                            <div class="reservation-calendar-grid" data-calendar-grid>
                                <div class="reservation-calendar-empty">Nejprve vyberte službu.</div>
                            </div>
                            <label class="reservation-day-select-hidden">
                                Den
                                <select name="rezervacni_datum" required data-day-select disabled>
                                    <option value="">Nejprve vyberte službu</option>
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
                        <div class="reservation-picked-slot" data-picked-slot>
                            <strong>Vybraný termín:</strong>
                            <span data-picked-slot-value>Zatím není vybraný den a čas.</span>
                        </div>
                        <div class="reservation-step-actions">
                            <button type="button" class="reservation-next" data-step-next>Pokračovat na kontakt</button>
                        </div>
                    </section>

                    <section class="reservation-step" data-step="2" hidden>
                        <div class="reservation-grid">
                            <label>
                                Jméno a příjmení
                                <input type="text" name="jmeno" required>
                            </label>
                            <label>
                                E-mail
                                <input type="email" name="email" required>
                            </label>
                            <label>
                                Telefon
                                <input type="tel" name="telefon" placeholder="+420 123 456 789" data-phone-input>
                            </label>
                        </div>
                        <label>
                            Poznámka
                            <textarea name="poznamka" rows="3" placeholder="např. citlivá pleť, preference termínu"></textarea>
                        </label>
                        <div class="reservation-step-actions">
                            <button type="button" class="reservation-back" data-step-back>Zpět</button>
                            <button type="button" class="reservation-next" data-step-next>Pokračovat na potvrzení</button>
                        </div>
                    </section>

                    <section class="reservation-step" data-step="3" hidden>
                        <div class="reservation-summary" data-reservation-summary>
                            <h4>Shrnutí rezervace</h4>
                            <p><strong>Služba:</strong> <span data-summary-service>—</span></p>
                            <p><strong>Termín:</strong> <span data-summary-slot>—</span></p>
                            <p><strong>Kontakt:</strong> <span data-summary-contact>—</span></p>
                        </div>
                        <p class="reservation-meta-note">Odesláním formuláře souhlasíte se zpracováním údajů pouze pro účely rezervace.</p>
                        <div class="reservation-step-actions reservation-submit-wrap">
                            <button type="button" class="reservation-back" data-step-back>Zpět</button>
                            <button type="submit" data-submit-button data-default-label="Odeslat rezervaci" data-loading-label="Odesílám...">Odeslat rezervaci</button>
                        </div>
                    </section>
                </div>
            </form>
        </div>

        <div class="reservation-faq">
            <h3>Časté dotazy k rezervaci</h3>
            <div class="reservation-faq-list">
                <details>
                    <summary>Jak rychle dostanu potvrzení rezervace?</summary>
                    <p>Po odeslání formuláře přijde potvrzení e-mailem. Pokud e-mail nevidíte, zkontrolujte i složku spam/hromadné.</p>
                </details>
                <details>
                    <summary>Mohu termín změnit nebo zrušit?</summary>
                    <p>Ano, stačí odpovědět na potvrzovací e-mail nebo mě kontaktovat telefonicky. Ideálně co nejdříve.</p>
                </details>
                <details>
                    <summary>Co když si nejsem jistá výběrem služby?</summary>
                    <p>Vyberte orientačně službu, která je vám nejbližší. Na místě ji případně společně upravíme podle pleti a cíle ošetření.</p>
                </details>
                <details>
                    <summary>Za jak dlouho před termínem mám přijít?</summary>
                    <p>Doporučuji dorazit 5 minut předem, abychom mohly začít v klidu a bez stresu.</p>
                </details>
            </div>
        </div>

        <div class="contact-grid" id="contact-info">
            <div>
                <div class="contact-box">
                    <strong>Pavlína Pomykalová</strong><br>
                    <i class="fas fa-phone" style="color:#7a5a43; margin-right:6px;"></i> +420 732 856 036<br>
                    <i class="fas fa-envelope" style="color:#7a5a43; margin-right:6px;"></i>
                    <span class="email" data-user="pavlina" data-domain="pomykal.cz">pavlina [zavináč] pomykal.cz</span><br>
                    <i class="fab fa-instagram" style="color:#7a5a43; margin-right:6px;"></i>
                    <a href="https://www.instagram.com/beauty_touch_by_vp/" target="_blank" style="color:#7a5a43;">@beauty_touch_by_vp</a><br>
                    <span style="font-size:0.98em; color:#7a6a5b;"><strong>IČO:</strong> 234 275 66</span>
                </div>
                <div class="contact-box">
                    <strong>Otevírací doba:</strong><br>
                    Po–Pá: Dle objednávek
                </div>
            </div>
            <div>
                <div class="contact-box" style="margin-bottom: 1.2rem;">
                    <strong>Adresa:</strong><br>
                    Interhotel Zlín<br>
                    náměstí Práce 2512, Zlín<br>
                    5. patro, dveře č. 512
                </div>
                <div>
                    <iframe
                        class="contact-map-iframe"
                        src="<?= escape($mapIframeSrc) ?>"
                        width="100%"
                        height="280"
                        frameborder="0"
                        style="border:none;"
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                        allowfullscreen
                    ></iframe>
                </div>
            </div>
        </div>
    </div>
</section>
