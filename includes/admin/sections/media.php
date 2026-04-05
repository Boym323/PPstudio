                <section class="admin-layout" id="media">
                    <div class="admin-card">
                        <p class="eyebrow">Fotky, galerie a certifikáty</p>
                        <h2>Nahrát profilovou fotku, galerii salonu a certifikáty</h2>
                        <?php if ($mediaFeedback !== ''): ?>
                            <div class="alert <?= $mediaFeedbackType === 'success' ? 'alert-success' : 'alert-error' ?>"><?= escape($mediaFeedback) ?></div>
                        <?php endif; ?>
                        <form method="post" action="admin.php?tab=media#media" class="admin-form" enctype="multipart/form-data">
                            <?= csrfInputField() ?>
                            <label>
                                <span>Kategorie</span>
                                <select name="category" required>
                                    <option value="profile">Profilová fotka</option>
                                    <option value="gallery">Galerie salonu</option>
                                </select>
                            </label>
                            <label><span>Obrázek</span><input type="file" name="image" accept=".jpg,.jpeg,.png,.webp,.gif" required></label>
                            <label><span>Nadpis</span><input type="text" name="title" placeholder="např. Interiér salonu"></label>
                            <label><span>Podnadpis</span><input type="text" name="subtitle" placeholder="krátký doplňující text"></label>
                            <label><span>Odkaz</span><input type="url" name="external_url" placeholder="volitelně odkaz na detail nebo externí stránku"></label>
                            <label><span>Pořadí</span><input type="number" name="sort_order" min="0" step="1" value="0"></label>
                            <button class="button button-primary" type="submit" name="save_media" value="1">Uložit obrázek</button>
                        </form>
                        <hr style="border:0;border-top:1px solid #e6d6c4;margin:1rem 0;">
                        <form method="post" action="admin.php?tab=media#media" class="admin-form" enctype="multipart/form-data">
                            <?= csrfInputField() ?>
                            <label><span>Certifikát (JPG/PNG/WEBP/GIF/PDF)</span><input type="file" name="certificate_file" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf" required></label>
                            <label><span>Název certifikátu</span><input type="text" name="certificate_title" placeholder="např. Lymfatická masáž obličeje"></label>
                            <button class="button button-primary" type="submit" name="save_certificate_file" value="1">Nahrát certifikát</button>
                        </form>
                        <p class="form-hint">Aktuální serverový limit pro jeden nahrávaný soubor je <strong><?= escape($uploadMaxFilesize) ?></strong>. Pokud se po odeslání nic nestane, bývá fotka větší než povolený limit.</p>
                    </div>
                    <div class="admin-note">
                        <p class="eyebrow">Nahrané soubory</p>
                        <div class="media-admin-group">
                            <h3>Profilová fotka</h3>
                            <div class="media-admin-grid">
                                <?php foreach ($profileMedia as $item): ?>
                                    <article class="media-admin-card">
                                        <img src="<?= escape((string) $item['image_path']) ?>" alt="<?= escape((string) ($item['title'] ?? 'Profil')) ?>" loading="lazy" decoding="async">
                                        <strong><?= escape((string) ($item['title'] ?? 'Profil')) ?></strong>
                                        <form method="post"><?= csrfInputField() ?><input type="hidden" name="media_id" value="<?= escape((string) $item['id']) ?>"><button class="button button-danger button-small" type="submit" name="delete_media" value="1">Smazat</button></form>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="media-admin-group">
                            <h3>Galerie salonu</h3>
                            <div class="media-admin-grid">
                                <?php foreach ($galleryMedia as $item): ?>
                                    <article class="media-admin-card">
                                        <img src="<?= escape((string) $item['image_path']) ?>" alt="<?= escape((string) ($item['title'] ?? 'Galerie')) ?>" loading="lazy" decoding="async">
                                        <strong><?= escape((string) ($item['title'] ?? 'Galerie')) ?></strong>
                                        <form method="post"><?= csrfInputField() ?><input type="hidden" name="media_id" value="<?= escape((string) $item['id']) ?>"><button class="button button-danger button-small" type="submit" name="delete_media" value="1">Smazat</button></form>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="media-admin-group">
                            <h3>Certifikáty (sekce O mně)</h3>
                            <div class="media-admin-grid">
                                <?php if (empty($certificateFiles)): ?>
                                    <p class="form-hint">Zatím nejsou nahrané žádné certifikáty.</p>
                                <?php else: ?>
                                    <?php foreach ($certificateFiles as $item): ?>
                                        <article class="media-admin-card media-admin-card-certificate">
                                            <?php if (!empty($item['is_image'])): ?>
                                                <img class="media-admin-certificate-image" src="<?= escape((string) (($item['preview_url'] ?? '') !== '' ? $item['preview_url'] : $item['url'])) ?>" alt="<?= escape((string) ($item['label'] ?? 'Certifikát')) ?>" loading="lazy" decoding="async">
                                            <?php else: ?>
                                                <div class="media-admin-pdf">PDF</div>
                                            <?php endif; ?>
                                            <strong><?= escape((string) (($item['title'] ?? '') !== '' ? $item['title'] : 'Certifikát')) ?></strong>
                                            <form method="post" class="admin-form">
                                                <?= csrfInputField() ?>
                                                <input type="hidden" name="certificate_name" value="<?= escape((string) ($item['name'] ?? '')) ?>">
                                                <label>
                                                    <span>Název na webu</span>
                                                    <input type="text" name="certificate_title" value="<?= escape((string) ($item['title'] ?? '')) ?>" placeholder="např. Laminace obočí">
                                                </label>
                                                <button class="button button-secondary button-small" type="submit" name="save_certificate_title" value="1">Uložit název</button>
                                            </form>
                                            <form method="post">
                                                <?= csrfInputField() ?>
                                                <input type="hidden" name="certificate_name" value="<?= escape((string) ($item['name'] ?? '')) ?>">
                                                <button class="button button-danger button-small" type="submit" name="delete_certificate_file" value="1">Smazat</button>
                                            </form>
                                        </article>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </section>
