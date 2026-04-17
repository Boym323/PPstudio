                <section class="admin-single" id="nastaveni">
                    <?php
                    $settingsSectionLabel = $settingsSection === 'recenze'
                        ? 'Recenze a sociální sítě'
                        : ($settingsSection === 'email' ? 'E-mailové notifikace' : 'Studio a kontakt');
                    ?>
                    <div class="admin-card settings-section-switcher">
                        <p class="eyebrow">Nastavení studia a webu</p>
                        <h2>Upravte kontakty, provozní údaje i napojení recenzí</h2>
                        <div class="settings-section-tabs" role="tablist" aria-label="Podsekce nastavení">
                            <a
                                class="button button-secondary button-small <?= $settingsSection === 'studio' ? 'is-active' : '' ?>"
                                href="<?= \PPStudio\Support\ViewHelper::escape($adminBasePath ?? '/admin.php') ?>?tab=nastaveni&amp;settings_section=studio#nastaveni"
                                role="tab"
                                aria-selected="<?= $settingsSection === 'studio' ? 'true' : 'false' ?>"
                            >Studio a kontakt</a>
                            <a
                                class="button button-secondary button-small <?= $settingsSection === 'recenze' ? 'is-active' : '' ?>"
                                href="<?= \PPStudio\Support\ViewHelper::escape($adminBasePath ?? '/admin.php') ?>?tab=nastaveni&amp;settings_section=recenze#nastaveni"
                                role="tab"
                                aria-selected="<?= $settingsSection === 'recenze' ? 'true' : 'false' ?>"
                            >Recenze a sociální sítě</a>
                            <a
                                class="button button-secondary button-small <?= $settingsSection === 'email' ? 'is-active' : '' ?>"
                                href="<?= \PPStudio\Support\ViewHelper::escape($adminBasePath ?? '/admin.php') ?>?tab=nastaveni&amp;settings_section=email#nastaveni"
                                role="tab"
                                aria-selected="<?= $settingsSection === 'email' ? 'true' : 'false' ?>"
                            >E-mailové notifikace</a>
                        </div>
                        <p>Zobrazte jen tu část nastavení, kterou zrovna potřebujete upravit. Aktivní podsekce: <strong><?= \PPStudio\Support\ViewHelper::escape($settingsSectionLabel) ?></strong>.</p>
                    </div>

                    <div class="settings-section-panel" <?= $settingsSection !== 'studio' ? 'hidden' : '' ?>>
                        <div class="admin-card">
                            <p class="eyebrow">Studio a kontakt</p>
                            <h2>Základní provozní údaje</h2>
                            <form method="post" class="admin-form admin-form-grid">
                                <?= csrfInputField() ?>
                                <input type="hidden" name="settings_section" value="studio">
                                <?php
                                foreach ($studioSettingFields as $fieldKey => $fieldLabel):
                                    $isLong = in_array($fieldKey, ['contact_address', 'contact_opening_hours'], true);
                                    $inputType = str_contains($fieldKey, 'email')
                                        ? 'email'
                                        : (str_contains($fieldKey, 'url') ? 'url' : 'text');
                                ?>
                                    <label class="<?= $isLong ? 'full-span' : '' ?>">
                                        <span><?= \PPStudio\Support\ViewHelper::escape($fieldLabel) ?></span>
                                        <?php if ($isLong): ?>
                                            <textarea name="<?= \PPStudio\Support\ViewHelper::escape($fieldKey) ?>" rows="4"><?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\SettingsHelper::setting($siteSettings, $fieldKey, '')) ?></textarea>
                                        <?php else: ?>
                                            <input type="<?= $inputType ?>" name="<?= \PPStudio\Support\ViewHelper::escape($fieldKey) ?>" value="<?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\SettingsHelper::setting($siteSettings, $fieldKey, '')) ?>">
                                        <?php endif; ?>
                                    </label>
                                <?php endforeach; ?>
                                <button class="button button-primary full-span" type="submit" name="save_settings" value="1">Uložit nastavení studia</button>
                            </form>
                        </div>
                    </div>

                    <div class="settings-section-panel" <?= $settingsSection !== 'recenze' ? 'hidden' : '' ?>>
                        <div class="admin-card">
                            <p class="eyebrow">Recenze a sociální sítě</p>
                            <h2>Napojení na Google a Firmy.cz</h2>
                            <form method="post" class="admin-form admin-form-grid">
                                <?= csrfInputField() ?>
                                <input type="hidden" name="settings_section" value="recenze">
                                <label><span>Google recenze URL</span><input type="url" name="google_reviews_url" value="<?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\SettingsHelper::setting($siteSettings, 'google_reviews_url', '')) ?>"></label>
                                <label><span>Firmy.cz URL</span><input type="url" name="firmy_reviews_url" value="<?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\SettingsHelper::setting($siteSettings, 'firmy_reviews_url', '')) ?>"></label>
                                <label><span>Google Place ID</span><input type="text" name="google_place_id" value="<?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\SettingsHelper::setting($siteSettings, 'google_place_id', '')) ?>" placeholder="např. ChIJ..."></label>
                                <label><span>Jazyk recenzí (kód)</span><input type="text" name="google_reviews_language" value="<?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\SettingsHelper::setting($siteSettings, 'google_reviews_language', 'cs')) ?>" placeholder="cs"></label>
                                <label class="full-span"><span>Firmy.cz embed HTML</span><textarea name="firmy_reviews_embed" rows="4"><?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\SettingsHelper::setting($siteSettings, 'firmy_reviews_embed', '')) ?></textarea></label>
                                <button class="button button-primary full-span" type="submit" name="save_integrations" value="1">Uložit napojení recenzí</button>
                            </form>
                            <p class="form-hint">Google Places API key je načítán pouze z ENV (<code>PPSTUDIO_GOOGLE_PLACES_API_KEY</code>). Pro Firmy.cz lze použít embed HTML.</p>
                        </div>
                    </div>

                    <div class="settings-section-panel" <?= $settingsSection !== 'email' ? 'hidden' : '' ?>>
                        <div class="admin-card">
                            <p class="eyebrow">E-mailové notifikace</p>
                            <h2>Notifikace rezervací a potvrzovací odkazy</h2>
                            <form method="post" class="admin-form admin-form-grid">
                                <?= csrfInputField() ?>
                                <input type="hidden" name="settings_section" value="email">
                                <label>
                                    <span>Notifikační e-maily</span>
                                    <input type="text" name="notification_emails" value="<?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\SettingsHelper::setting($siteSettings, 'notification_emails', '')) ?>" placeholder="info@ppstudio.cz, druha@adresa.cz">
                                </label>
                                <label>
                                    <span>Odesílatel</span>
                                    <input type="text" value="<?= \PPStudio\Support\ViewHelper::escape((string) ($emailConfig['from_email'] ?? '')) ?>" readonly>
                                </label>
                                <label>
                                    <span>SMTP server</span>
                                    <input type="text" value="<?= \PPStudio\Support\ViewHelper::escape((string) ($emailConfig['host'] ?? '')) ?>" readonly>
                                </label>
                                <label>
                                    <span>Reply-to</span>
                                    <input type="text" value="<?= \PPStudio\Support\ViewHelper::escape((string) ($emailConfig['reply_to'] ?? '')) ?>" readonly>
                                </label>
                                <label class="full-span">
                                    <span>Automatické potvrzení z e-mailu</span>
                                    <input type="text" value="Admin notifikace obsahují odkazy pro okamžité potvrzení nebo zrušení rezervace." readonly>
                                </label>
                                <button class="button button-primary full-span" type="submit" name="save_email_settings" value="1">Uložit e-mailové notifikace</button>
                            </form>
                            <p class="form-hint">Citlivé SMTP údaje nastavte ideálně přes ENV proměnné serveru (soubor <code>config/email.php</code> je jen fallback). Pro potvrzovací odkazy musí být vyplněno i <code>PPSTUDIO_ACTION_SECRET</code>.</p>
                        </div>
                    </div>
                </section>
