                <?php
                    $activeMediaSection = 'profile';
                    if (isset($_POST['media_section']) && in_array((string) $_POST['media_section'], ['profile', 'gallery', 'certificates'], true)) {
                        $activeMediaSection = (string) $_POST['media_section'];
                    } elseif (isset($_GET['media_section']) && in_array((string) $_GET['media_section'], ['profile', 'gallery', 'certificates'], true)) {
                        $activeMediaSection = (string) $_GET['media_section'];
                    }
                ?>
                <section class="admin-single" id="media" data-media-root>
                    <div class="admin-card media-section-switcher" data-media-section-switcher data-initial-section="<?= \PPStudio\Support\ViewHelper::escape($activeMediaSection) ?>">
                        <p class="eyebrow">Fotky a galerie</p>
                        <h2>Správa fotek studia a certifikátů</h2>
                        <?php if ($mediaFeedback !== ''): ?>
                            <div class="alert <?= $mediaFeedbackType === 'success' ? 'alert-success' : 'alert-error' ?>"><?= \PPStudio\Support\ViewHelper::escape($mediaFeedback) ?></div>
                        <?php endif; ?>
                        <div class="media-section-tabs" role="tablist" aria-label="Podsekce fotek a galerie">
                            <button class="button button-secondary button-small<?= $activeMediaSection === 'profile' ? ' is-active' : '' ?>" type="button" data-media-section-trigger="profile" aria-pressed="<?= $activeMediaSection === 'profile' ? 'true' : 'false' ?>">Profilová fotka</button>
                            <button class="button button-secondary button-small<?= $activeMediaSection === 'gallery' ? ' is-active' : '' ?>" type="button" data-media-section-trigger="gallery" aria-pressed="<?= $activeMediaSection === 'gallery' ? 'true' : 'false' ?>">Galerie salonu</button>
                            <button class="button button-secondary button-small<?= $activeMediaSection === 'certificates' ? ' is-active' : '' ?>" type="button" data-media-section-trigger="certificates" aria-pressed="<?= $activeMediaSection === 'certificates' ? 'true' : 'false' ?>">Certifikáty</button>
                        </div>
                        <p class="form-hint">Každá podsekce obsahuje samostatné nahrání i správu už uložených položek.</p>
                    </div>

                    <div class="media-section-panel" data-media-section-panel="profile"<?= $activeMediaSection === 'profile' ? '' : ' hidden' ?>>
                        <div class="admin-layout media-admin-layout">
                            <div class="admin-card">
                                <p class="eyebrow">Profilová fotka</p>
                                <h2>Hlavní fotografie do sekce O mně</h2>
                                <p class="form-hint">Zde nahrajte hlavní portrétní fotku. U profilové fotky se vždy ponechá jen poslední uložený obrázek.</p>
                                <form method="post" action="<?= \PPStudio\Support\ViewHelper::escape($adminBasePath ?? '/admin.php') ?>?tab=media#media" class="admin-form media-upload-form" enctype="multipart/form-data">
                                    <?= csrfInputField() ?>
                                    <input type="hidden" name="category" value="profile">
                                    <input type="hidden" name="sort_order" value="0">
                                    <input type="hidden" name="title" value="">
                                    <input type="hidden" name="subtitle" value="">
                                    <input type="hidden" name="media_section" value="profile">
                                    <label><span>Fotka</span><input type="file" name="image" accept=".jpg,.jpeg,.png,.webp,.gif" required></label>
                                    <button class="button button-primary" type="submit" name="save_media" value="1">Uložit profilovou fotku</button>
                                </form>
                                <p class="form-hint">Aktuální serverový limit pro jeden nahrávaný soubor je <strong><?= \PPStudio\Support\ViewHelper::escape($uploadMaxFilesize) ?></strong>.</p>
                            </div>
                            <div class="admin-note media-admin-note">
                                <p class="eyebrow">Aktuální stav</p>
                                <h3>Co se zobrazuje na webu</h3>
                                <p class="form-hint">Tato fotka se používá jako hlavní vizuál v sekci O mně.</p>
                                <div class="media-admin-grid media-admin-grid-single">
                                    <?php if ($profileMedia === []): ?>
                                        <p class="form-hint">Zatím není nahraná žádná profilová fotka.</p>
                                    <?php else: ?>
                                        <?php foreach ($profileMedia as $item): ?>
                                            <article class="media-admin-card media-admin-profile-card">
                                                <div class="media-admin-profile-visual">
                                                    <img src="<?= \PPStudio\Support\ViewHelper::escape((string) $item['image_path']) ?>" alt="Profilová fotka" loading="lazy" decoding="async">
                                                </div>
                                                <div class="media-admin-card-body media-admin-profile-body">
                                                    <strong>Aktuální profilová fotka</strong>
                                                    <p class="media-admin-card-meta">Tento náhled odpovídá fotografii, která se používá jako hlavní vizuál v sekci O mně.</p>
                                                </div>
                                                <form method="post" class="media-admin-card-actions">
                                                    <?= csrfInputField() ?>
                                                    <input type="hidden" name="media_id" value="<?= \PPStudio\Support\ViewHelper::escape((string) $item['id']) ?>">
                                                    <input type="hidden" name="media_section" value="profile">
                                                    <button class="button button-danger button-small" type="submit" name="delete_media" value="1">Smazat</button>
                                                </form>
                                            </article>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="media-section-panel" data-media-section-panel="gallery"<?= $activeMediaSection === 'gallery' ? '' : ' hidden' ?>>
                        <div class="admin-layout media-admin-layout">
                            <div class="admin-card">
                                <p class="eyebrow">Galerie salonu</p>
                                <h2>Přidat nový snímek do galerie</h2>
                                <p class="form-hint">Galerie se zobrazuje na veřejném webu v části Prostory. Nadpis a podnadpis jsou volitelné, pořadí určuje výsledné řazení.</p>
                                <form method="post" action="<?= \PPStudio\Support\ViewHelper::escape($adminBasePath ?? '/admin.php') ?>?tab=media#media" class="admin-form media-upload-form" enctype="multipart/form-data">
                                    <?= csrfInputField() ?>
                                    <input type="hidden" name="category" value="gallery">
                                    <input type="hidden" name="media_section" value="gallery">
                                    <label><span>Fotka</span><input type="file" name="image" accept=".jpg,.jpeg,.png,.webp,.gif" required></label>
                                    <label><span>Nadpis</span><input type="text" name="title" placeholder="např. Interiér salonu"></label>
                                    <label><span>Podnadpis</span><input type="text" name="subtitle" placeholder="krátký doplňující text"></label>
                                    <label><span>Pořadí</span><input type="number" name="sort_order" min="0" step="1" value="0"></label>
                                    <label><span>Volitelný odkaz</span><input type="url" name="external_url" placeholder="např. odkaz na detail nebo externí stránku"></label>
                                    <button class="button button-primary" type="submit" name="save_media" value="1">Přidat do galerie</button>
                                </form>
                            </div>
                            <div class="admin-note media-admin-note">
                                <p class="eyebrow">Nahrané fotky</p>
                                <h3>Přehled galerie</h3>
                                <p class="form-hint">Fotky jsou řazené podle pole Pořadí, potom podle nejnovějšího vložení.</p>
                                <div class="media-admin-grid">
                                    <?php if ($galleryMedia === []): ?>
                                        <p class="form-hint">Zatím není nahraná žádná fotografie do galerie.</p>
                                    <?php else: ?>
                                        <?php foreach ($galleryMedia as $item): ?>
                                            <article class="media-admin-card">
                                                <img src="<?= \PPStudio\Support\ViewHelper::escape((string) $item['image_path']) ?>" alt="<?= \PPStudio\Support\ViewHelper::escape((string) ($item['title'] ?? 'Galerie')) ?>" loading="lazy" decoding="async">
                                                <div class="media-admin-card-body">
                                                    <strong><?= \PPStudio\Support\ViewHelper::escape((string) (($item['title'] ?? '') !== '' ? $item['title'] : 'Fotografie galerie')) ?></strong>
                                                    <?php if (($item['subtitle'] ?? '') !== ''): ?>
                                                        <p class="media-admin-card-meta"><?= \PPStudio\Support\ViewHelper::escape((string) $item['subtitle']) ?></p>
                                                    <?php endif; ?>
                                                    <div class="media-admin-card-badges">
                                                        <span class="media-admin-badge">Pořadí: <?= \PPStudio\Support\ViewHelper::escape((string) ((int) ($item['sort_order'] ?? 0))) ?></span>
                                                        <?php if (($item['external_url'] ?? '') !== ''): ?>
                                                            <span class="media-admin-badge">Má odkaz</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <form method="post" class="media-admin-card-actions">
                                                    <?= csrfInputField() ?>
                                                    <input type="hidden" name="media_id" value="<?= \PPStudio\Support\ViewHelper::escape((string) $item['id']) ?>">
                                                    <input type="hidden" name="media_section" value="gallery">
                                                    <button class="button button-danger button-small" type="submit" name="delete_media" value="1">Smazat</button>
                                                </form>
                                            </article>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="media-section-panel" data-media-section-panel="certificates"<?= $activeMediaSection === 'certificates' ? '' : ' hidden' ?>>
                        <div class="admin-layout media-admin-layout">
                            <div class="admin-card">
                                <p class="eyebrow">Certifikáty</p>
                                <h2>Nahrát nový certifikát</h2>
                                <p class="form-hint">Certifikáty se zobrazují v sekci O mně. Po nahrání doporučuji hned vyplnit název, který se ukáže na webu.</p>
                                <form method="post" action="<?= \PPStudio\Support\ViewHelper::escape($adminBasePath ?? '/admin.php') ?>?tab=media#media" class="admin-form media-upload-form" enctype="multipart/form-data">
                                    <?= csrfInputField() ?>
                                    <input type="hidden" name="media_section" value="certificates">
                                    <label><span>Soubor certifikátu</span><input type="file" name="certificate_file" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf" required></label>
                                    <label><span>Název certifikátu</span><input type="text" name="certificate_title" placeholder="např. Lymfatická masáž obličeje"></label>
                                    <button class="button button-primary" type="submit" name="save_certificate_file" value="1">Nahrát certifikát</button>
                                </form>
                            </div>
                            <div class="admin-note media-admin-note">
                                <p class="eyebrow">Nahrané certifikáty</p>
                                <h3>Co se zobrazuje na webu</h3>
                                <p class="form-hint">U každého certifikátu můžete upravit název na webu nebo soubor smazat.</p>
                                <div class="media-admin-grid">
                                    <?php if (empty($certificateFiles)): ?>
                                        <p class="form-hint">Zatím nejsou nahrané žádné certifikáty.</p>
                                    <?php else: ?>
                                        <?php foreach ($certificateFiles as $item): ?>
                                            <article class="media-admin-card media-admin-card-certificate">
                                                <?php if (!empty($item['is_image'])): ?>
                                                    <img class="media-admin-certificate-image" src="<?= \PPStudio\Support\ViewHelper::escape((string) (($item['preview_url'] ?? '') !== '' ? $item['preview_url'] : $item['url'])) ?>" alt="<?= \PPStudio\Support\ViewHelper::escape((string) ($item['label'] ?? 'Certifikát')) ?>" loading="lazy" decoding="async">
                                                <?php else: ?>
                                                    <div class="media-admin-pdf">PDF</div>
                                                <?php endif; ?>
                                                <div class="media-admin-card-body">
                                                    <strong><?= \PPStudio\Support\ViewHelper::escape((string) (($item['title'] ?? '') !== '' ? $item['title'] : 'Certifikát')) ?></strong>
                                                    <p class="media-admin-card-meta"><?= \PPStudio\Support\ViewHelper::escape((string) ($item['name'] ?? '')) ?></p>
                                                </div>
                                                <form method="post" class="admin-form media-admin-inline-form">
                                                    <?= csrfInputField() ?>
                                                    <input type="hidden" name="certificate_name" value="<?= \PPStudio\Support\ViewHelper::escape((string) ($item['name'] ?? '')) ?>">
                                                    <input type="hidden" name="media_section" value="certificates">
                                                    <label>
                                                        <span>Název na webu</span>
                                                        <input type="text" name="certificate_title" value="<?= \PPStudio\Support\ViewHelper::escape((string) ($item['title'] ?? '')) ?>" placeholder="např. Laminace obočí">
                                                    </label>
                                                    <button class="button button-secondary button-small" type="submit" name="save_certificate_title" value="1">Uložit název</button>
                                                </form>
                                                <form method="post" class="media-admin-card-actions">
                                                    <?= csrfInputField() ?>
                                                    <input type="hidden" name="certificate_name" value="<?= \PPStudio\Support\ViewHelper::escape((string) ($item['name'] ?? '')) ?>">
                                                    <input type="hidden" name="media_section" value="certificates">
                                                    <button class="button button-danger button-small" type="submit" name="delete_certificate_file" value="1">Smazat</button>
                                                </form>
                                            </article>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <p class="form-hint">Aktuální serverový limit pro jeden nahrávaný soubor je <strong><?= \PPStudio\Support\ViewHelper::escape($uploadMaxFilesize) ?></strong>. Pokud se po odeslání nic nestane, bývá soubor větší než povolený limit.</p>
                    </div>
                </section>
