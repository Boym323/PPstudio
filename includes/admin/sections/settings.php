                <section class="admin-single" id="nastaveni">
                    <div class="admin-card">
                        <p class="eyebrow">Nastavení studia</p>
                        <h2>Základní provozní údaje</h2>
                        <form method="post" class="admin-form admin-form-grid">
                            <?= csrfInputField() ?>
                            <?php
                            foreach ($studioSettingFields as $fieldKey => $fieldLabel):
                                $isLong = $fieldKey === 'contact_map_url';
                                $inputType = str_contains($fieldKey, 'email')
                                    ? 'email'
                                    : (str_contains($fieldKey, 'url') && $fieldKey !== 'contact_map_url' ? 'url' : 'text');
                            ?>
                                <label class="<?= $isLong ? 'full-span' : '' ?>">
                                    <span><?= escape($fieldLabel) ?></span>
                                    <?php if ($isLong): ?>
                                        <textarea name="<?= escape($fieldKey) ?>" rows="3" placeholder="Vložte URL nebo celý iframe kód z Mapy.cz"><?= escape(setting($siteSettings, $fieldKey, '')) ?></textarea>
                                    <?php else: ?>
                                        <input type="<?= $inputType ?>" name="<?= escape($fieldKey) ?>" value="<?= escape(setting($siteSettings, $fieldKey, '')) ?>">
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                            <button class="button button-primary full-span" type="submit" name="save_settings" value="1">Uložit nastavení</button>
                        </form>
                    </div>
                </section>
