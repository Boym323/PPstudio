<?php
$aboutView = (new \PPStudio\Http\View\SiteAboutPageContextBuilder())->build();
$certificateItems = $aboutView['certificateItems'];
?>

<section class="team" id="team">
    <div class="container">
        <h1 class="section-title">O <span>mně</span></h1>
        <div class="team-grid">
            <div class="team-member">
                <div class="about-profile-head">
                    <div class="team-member-image-veru">
                        <img src="/assets/images/Paji.jpeg" alt="Pavlína Pomykalová">
                    </div>
                    <div class="about-profile-meta">
                        <h3>Pavlína Pomykalová</h3>
                        <p class="about-role">Majitelka PP Studia (Cosmetics &amp; Laminations)</p>
                        <p class="about-intro">Vytvořila jsem prostor, kde se můžete zastavit, nadechnout a dopřát si chvíli jen pro sebe.</p>
                        <div class="specialties">
                            <span class="specialty-tag">Lash lifting</span>
                            <span class="specialty-tag">Laminace obočí</span>
                            <span class="specialty-tag">Kosmetické ošetření</span>
                        </div>
                    </div>
                </div>
                <div class="team-bio">
                    <div class="about-story-grid">
                        <article class="about-story-block">
                            <h4>Můj příběh</h4>
                            <p>Moje profesní cesta v beauty světě se začala psát v roce 2024. Tehdy jsem se rozhodla v tomto krásném oboru pokračovat a v roce 2025, po dosažení všech kvalifikací, jsem zahájila aktivní praxi.</p>
                        </article>
                        <article class="about-story-block">
                            <h4>Můj přístup</h4>
                            <p>Věřím, že obličej je zrcadlem naší duše. Mým cílem je dělat ženy sebevědomé, ušetřit jim každé ráno drahocenné minuty času a podpořit jejich fyzickou i duševní pohodu.</p>
                            <p>Ke každé klientce přistupuji s maximálním respektem a empatií. Doporučuji jen to, co skutečně funguje a přináší výsledky.</p>
                        </article>
                        <article class="about-story-block about-story-block-wide">
                            <h4>Na co se můžete těšit</h4>
                            <p>Pracuji s českou kosmetikou FOR LIFE &amp; MADAGA. Tato značka je postavena na francouzských základech a je symbolem kvality a šetrnosti.</p>
                            <ul>
                                <li>profesionální péče o pleť,</li>
                                <li>laminace obočí,</li>
                                <li>lash lifting,</li>
                                <li>uvolňovací lymfatická masáž obličeje.</li>
                            </ul>
                        </article>
                    </div>
                    <p class="about-closing">Těším se na každé naše setkání. I když je několikaletá praxe v oboru výhodou, lze ji vyvážit vášní, talentem, tvrdou dřinou, estetickým cítěním a odhodláním, které do každé své práce dávám.</p>
                </div>
                <div class="about-divider" aria-hidden="true">
                    <span></span>
                </div>

                <div class="about-certificates">
                    <h4>Certifikáty</h4>
                    <p class="about-certificates-intro">Průběžně se vzdělávám a zde najdete mé odborné certifikace.</p>
                    <?php if (!empty($certificateItems)): ?>
                        <div class="about-certificates-grid">
                            <?php foreach ($certificateItems as $certificate): ?>
                                <a
                                    class="about-certificate-card"
                                    href="<?= htmlspecialchars((string) ($certificate['certificate_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    data-certificate-trigger="1"
                                    data-certificate-type="<?= !empty($certificate['is_image_certificate']) ? 'image' : 'pdf' ?>"
                                    data-certificate-url="<?= htmlspecialchars((string) ($certificate['certificate_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    data-certificate-title="<?= htmlspecialchars((string) ($certificate['certificate_title'] ?? 'Certifikát'), ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    <div class="about-certificate-media">
                                        <?php if (!empty($certificate['is_image_certificate'])): ?>
                                            <img
                                                src="<?= htmlspecialchars((string) (($certificate['certificate_preview_url'] ?? '') !== '' ? $certificate['certificate_preview_url'] : $certificate['certificate_url']), ENT_QUOTES, 'UTF-8') ?>"
                                                alt="<?= htmlspecialchars((string) ($certificate['certificate_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                loading="lazy"
                                            >
                                        <?php else: ?>
                                            <div class="about-certificate-doc">PDF</div>
                                        <?php endif; ?>
                                    </div>
                                    <span class="about-certificate-title"><?= htmlspecialchars((string) ($certificate['certificate_title'] ?? 'Certifikát'), ENT_QUOTES, 'UTF-8') ?></span>
                                    <small class="about-certificate-meta">
                                        <?= htmlspecialchars((string) ($certificate['certificate_type'] ?? 'PDF certifikát'), ENT_QUOTES, 'UTF-8') ?>
                                        <?= !empty($certificate['uploaded_label']) ? ' • nahráno ' . htmlspecialchars((string) $certificate['uploaded_label'], ENT_QUOTES, 'UTF-8') : '' ?>
                                    </small>
                                    <span class="about-certificate-action">Otevřít certifikát</span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="about-certificates-empty">
                            Certifikáty budou brzy doplněny.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="certificate-modal" data-certificate-modal hidden>
    <div class="certificate-modal-backdrop" data-certificate-close></div>
    <div class="certificate-modal-dialog" role="dialog" aria-modal="true" aria-label="Náhled certifikátu">
        <button type="button" class="certificate-modal-close" data-certificate-close aria-label="Zavřít náhled certifikátu">&times;</button>
        <button type="button" class="certificate-modal-nav certificate-modal-nav-prev" data-certificate-prev aria-label="Předchozí certifikát">‹</button>
        <button type="button" class="certificate-modal-nav certificate-modal-nav-next" data-certificate-next aria-label="Další certifikát">›</button>
        <div class="certificate-modal-header">
            <h5 data-certificate-modal-title>Certifikát</h5>
        </div>
        <div class="certificate-modal-body">
            <img src="" alt="" data-certificate-modal-image hidden>
            <iframe src="about:blank" title="PDF certifikát" loading="lazy" data-certificate-modal-pdf hidden></iframe>
        </div>
    </div>
</div>
