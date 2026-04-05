                <section class="admin-single" id="emaily">
                    <div class="admin-card">
                        <p class="eyebrow">E-mail</p>
                        <h2>Notifikace rezervací a potvrzovací odkazy</h2>
                        <form method="post" class="admin-form admin-form-grid">
                            <?= csrfInputField() ?>
                            <label>
                                <span>Notifikační e-maily</span>
                                <input type="text" name="notification_emails" value="<?= escape(setting($siteSettings, 'notification_emails', '')) ?>" placeholder="info@ppstudio.cz, druha@adresa.cz">
                            </label>
                            <label>
                                <span>Odesílatel</span>
                                <input type="text" value="<?= escape((string) ($emailConfig['from_email'] ?? '')) ?>" readonly>
                            </label>
                            <label>
                                <span>SMTP server</span>
                                <input type="text" value="<?= escape((string) ($emailConfig['host'] ?? '')) ?>" readonly>
                            </label>
                            <label>
                                <span>Reply-to</span>
                                <input type="text" value="<?= escape((string) ($emailConfig['reply_to'] ?? '')) ?>" readonly>
                            </label>
                            <label class="full-span">
                                <span>Automatické potvrzení z e-mailu</span>
                                <input type="text" value="Admin notifikace obsahují odkazy pro okamžité potvrzení nebo zrušení rezervace." readonly>
                            </label>
                            <button class="button button-primary full-span" type="submit" name="save_email_settings" value="1">Uložit e-mailové notifikace</button>
                        </form>
                        <p class="form-hint">Citlivé SMTP údaje nastavte ideálně přes ENV proměnné serveru (soubor <code>config/email.php</code> je jen fallback). Pro potvrzovací odkazy musí být vyplněno i <code>PPSTUDIO_ACTION_SECRET</code>.</p>
                    </div>
                </section>
