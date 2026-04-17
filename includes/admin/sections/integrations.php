                <section class="admin-single" id="recenze-napojeni">
                    <div class="admin-card">
                        <p class="eyebrow">Recenze a social</p>
                        <h2>Napojení na Google a Firmy.cz</h2>
                        <form method="post" class="admin-form admin-form-grid">
                            <?= csrfInputField() ?>
                            <label><span>Google recenze URL</span><input type="url" name="google_reviews_url" value="<?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\SettingsHelper::setting($siteSettings, 'google_reviews_url', '')) ?>"></label>
                            <label><span>Firmy.cz URL</span><input type="url" name="firmy_reviews_url" value="<?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\SettingsHelper::setting($siteSettings, 'firmy_reviews_url', '')) ?>"></label>
                            <label><span>Google Place ID</span><input type="text" name="google_place_id" value="<?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\SettingsHelper::setting($siteSettings, 'google_place_id', '')) ?>" placeholder="např. ChIJ..."></label>
                            <label><span>Jazyk recenzí (kód)</span><input type="text" name="google_reviews_language" value="<?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\SettingsHelper::setting($siteSettings, 'google_reviews_language', 'cs')) ?>" placeholder="cs"></label>
                            <label class="full-span"><span>Firmy.cz embed HTML</span><textarea name="firmy_reviews_embed" rows="4"><?= \PPStudio\Support\ViewHelper::escape(\PPStudio\Support\SettingsHelper::setting($siteSettings, 'firmy_reviews_embed', '')) ?></textarea></label>
                            <button class="button button-primary full-span" type="submit" name="save_integrations" value="1">Uložit napojení</button>
                        </form>
                        <p class="form-hint">Google Places API key je nyní načítán pouze z ENV (`PPSTUDIO_GOOGLE_PLACES_API_KEY`). Pro Firmy.cz lze použít embed HTML.</p>
                    </div>
                </section>
