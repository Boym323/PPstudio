<?php
$contactName = setting($siteSettings, 'contact_name', 'Pavlína Pomykalová');
$contactPhone = setting($siteSettings, 'contact_phone', '+420 732 856 036');
$contactEmail = setting($siteSettings, 'contact_email', 'pavlina@pomykal.cz');
$contactInstagramUrl = setting($siteSettings, 'contact_instagram_url', '');
$contactIco = setting($siteSettings, 'contact_ico', '234 275 66');
$contactOpeningHours = setting($siteSettings, 'contact_opening_hours', 'Po-Pá: Dle objednávek');
$contactAddress = setting($siteSettings, 'contact_address', '');
$contactPhoneHref = contactPhoneHref($contactPhone);
$contactEmailHref = contactEmailHref($contactEmail);
$contactInstagramHandle = contactInstagramHandle($contactInstagramUrl);
$contactInstagramDmUrl = contactInstagramDmHref($contactInstagramUrl);
?>
<section class="contact-section" id="contact" style="padding-top:8.5rem;">
    <div class="contact-container">
        <h1 class="contact-title">Rezervace a kontakt</h1>
        <div class="reservation-box">
            <h3>Online rezervace</h3>
            <p>Vyberte službu, termín a vyplňte kontakt. Potvrzení rezervace vám dorazí e-mailem.</p>
            <div class="reservation-reassurance" aria-label="Důležité informace k rezervaci">
                <div class="reservation-reassurance-item">
                    <strong>Potvrzení e-mailem</strong>
                    <span>Po odeslání vám během chvíle dorazí potvrzení rezervace do e-mailu.</span>
                </div>
                <div class="reservation-reassurance-item">
                    <strong>Změna nebo zrušení</strong>
                    <span>Termín lze přesunout nebo zrušit nejpozději 24 hodin před začátkem procedury.</span>
                </div>
                <div class="reservation-reassurance-item">
                    <strong>Nejste si jistá službou?</strong>
                    <span>Vyberte nejbližší variantu a do poznámky napište, s čím potřebujete poradit.</span>
                </div>
            </div>
            <section class="reservation-fallback" data-reservation-fallback hidden>
                <div class="reservation-fallback-icon" aria-hidden="true">!</div>
                <div class="reservation-fallback-copy">
                    <h3>Online rezervace je dočasně nedostupná</h3>
                    <p>Rezervační formulář se teď nenačetl správně. Zavolejte mi, napište e-mail nebo zprávu na Instagram a domluvíme termín jinak.</p>
                </div>
                <div class="reservation-fallback-actions">
                    <?php if ($contactPhoneHref !== ''): ?>
                        <a class="cta-button cta-button-primary" href="<?= escape($contactPhoneHref) ?>">Zavolat</a>
                    <?php endif; ?>
                    <?php if ($contactEmailHref !== ''): ?>
                        <a class="cta-button cta-button-ghost" href="<?= escape($contactEmailHref) ?>">Napsat e-mail</a>
                    <?php endif; ?>
                    <?php if ($contactInstagramUrl !== ''): ?>
                        <a class="cta-button cta-button-ghost" href="<?= escape($contactInstagramUrl) ?>" target="_blank" rel="noreferrer noopener">
                            <i class="fab fa-instagram" aria-hidden="true"></i>
                            <span>Instagram</span>
                        </a>
                    <?php endif; ?>
                </div>
            </section>
            <div class="reservation-feedback" data-reservation-feedback><?= $reservationAlertHtml ?></div>
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
                        <p class="reservation-field-hint">Pokud si nejste jistá výběrem služby, napište to do poznámky. Na místě ji případně společně upravíme podle pleti a cíle ošetření.</p>
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
            <section class="reservation-success-card" data-reservation-success hidden>
                <div class="reservation-success-badge" aria-hidden="true">✓</div>
                <h4>Rezervace je úspěšně odeslaná</h4>
                <p class="reservation-success-message" data-success-message>
                    Děkujeme. Potvrzení rezervace vám během chvíle dorazí e-mailem.
                </p>
                <div class="reservation-success-summary">
                    <p><strong>Služba:</strong> <span data-success-service>—</span></p>
                    <p><strong>Termín:</strong> <span data-success-slot>—</span></p>
                    <p><strong>Kontakt:</strong> <span data-success-contact>—</span></p>
                </div>
                <div class="reservation-success-actions">
                    <button type="button" class="reservation-back" data-success-reset>Vytvořit další rezervaci</button>
                </div>
            </section>
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
                    <p>Ano. V potvrzovacím e-mailu najdete bezpečná tlačítka pro přesun i zrušení rezervace, případně mě můžete kontaktovat telefonicky.</p>
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
        <?php
        $emailUser = $contactEmail;
        $emailDomain = '';
        if (str_contains($contactEmail, '@')) {
            [$emailUser, $emailDomain] = explode('@', $contactEmail, 2);
        }
        ?>
        <div class="contact-grid" id="contact-info">
            <div>
                <div class="contact-box">
                    <strong><?= escape($contactName) ?></strong><br>
                    <i class="fas fa-phone" style="color:#7a5a43; margin-right:6px;"></i> <a href="<?= escape($contactPhoneHref) ?>"><?= escape($contactPhone) ?></a><br>
                    <i class="fas fa-envelope" style="color:#7a5a43; margin-right:6px;"></i>
                    <a class="email" href="<?= escape($contactEmailHref) ?>" data-user="<?= escape($emailUser) ?>" data-domain="<?= escape($emailDomain) ?>"><?= escape($emailUser) ?> [zavináč] <?= escape($emailDomain) ?></a><br>
                    <?php if ($contactInstagramUrl !== ''): ?>
                        <i class="fab fa-instagram" style="color:#7a5a43; margin-right:6px;"></i>
                        <a href="<?= escape($contactInstagramUrl) ?>" target="_blank" rel="noreferrer noopener" style="color:#7a5a43;"><?= escape($contactInstagramHandle !== '' ? '@' . $contactInstagramHandle : $contactInstagramUrl) ?></a><br>
                    <?php endif; ?>
                    <?php if ($contactIco !== ''): ?>
                        <span style="font-size:0.98em; color:#7a6a5b;"><strong>IČO:</strong> <?= escape($contactIco) ?></span>
                    <?php endif; ?>
                </div>
                <div class="contact-box">
                    <strong>Otevírací doba:</strong><br>
                    <?= nl2br(escape($contactOpeningHours)) ?>
                </div>
            </div>
            <div>
                <?php if ($contactAddress !== ''): ?>
                    <div class="contact-box" style="margin-bottom: 1.2rem;">
                        <strong>Adresa:</strong><br>
                        <?= nl2br(escape($contactAddress)) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
