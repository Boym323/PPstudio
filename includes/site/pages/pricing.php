<?php
$contactPhone = setting($siteSettings, 'contact_phone', '+420 732 856 036');
$contactEmail = setting($siteSettings, 'contact_email', 'pavlina@pomykal.cz');
$contactInstagramUrl = setting($siteSettings, 'contact_instagram_url', '');
$contactPhoneHref = contactPhoneHref($contactPhone);
$contactEmailHref = contactEmailHref($contactEmail);
$contactInstagramHandle = contactInstagramHandle($contactInstagramUrl);
$contactInstagramDmUrl = contactInstagramDmHref($contactInstagramUrl);
?>
<section class="pricing-gallery" id="ceniky" style="padding-top:8.5rem;">
    <div class="container">
        <h1 class="section-title">Náš <span>Ceník</span></h1>
        <p class="pricing-intro">Vyberte si kategorii, která je vám nejbližší. U každé služby najdete stručný popis, délku i cenu, a pokud si nebudete jistá, můžete to napsat do poznámky v rezervaci.</p>
        <div class="pricing-guide">
            <div class="pricing-guide-card">
                <strong>Nejste si jistá výběrem?</strong>
                <span>Zvolte službu, která je vašemu cíli nejblíž. Na místě ji případně společně doladíme podle pleti, očekávání a času.</span>
            </div>
            <div class="pricing-guide-card">
                <strong>Jak číst ceník</strong>
                <span>Štítky jako <em>První návštěva</em> nebo <em>Doporučeno</em> pomáhají rychle najít vhodný začátek.</span>
            </div>
            <div class="pricing-guide-card">
                <strong>Rezervace bez nejistoty</strong>
                <span>Po odeslání formuláře vám dorazí potvrzení e-mailem a termín lze přesunout nebo zrušit do 24 hodin předem.</span>
            </div>
        </div>
        <div class="pricing-category-nav" id="pricing-category-nav" aria-label="Rychlé skoky v ceníku"></div>
        <div class="pricing-text-wrap" id="pricing-list">
            <div class="pricing-empty">Načítám ceník…</div>
            <section class="pricing-fallback" data-pricing-fallback hidden>
                <div class="pricing-fallback-icon" aria-hidden="true">!</div>
                <div class="pricing-fallback-copy">
                    <h3>Ceník se právě nenačetl</h3>
                    <p>Aktuální ceny jsou dočasně nedostupné. Pokud potřebujete rychle poradit, napište nebo zavolejte a pošlu vám přehled osobně.</p>
                </div>
                <div class="pricing-fallback-actions">
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
        </div>
    </div>
</section>
