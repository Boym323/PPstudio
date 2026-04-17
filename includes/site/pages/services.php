<section class="services services-primary" id="services">
    <div class="container">
        <h1 class="section-title">Naše <span>Služby</span></h1>
        <?php if ($serviceCards !== []): ?>
            <div class="services-grid">
                <?php foreach ($serviceCards as $serviceCard): ?>
                    <div class="service-card">
                        <span class="service-badge"><?= \PPStudio\Support\ViewHelper::escape((string) ($serviceCard['badge'] ?? 'Doporučená péče')) ?></span>
                        <i class="fas <?= \PPStudio\Support\ViewHelper::escape((string) ($serviceCard['icon'] ?? 'fa-leaf')) ?>" aria-hidden="true"></i>
                        <h3><?= \PPStudio\Support\ViewHelper::escape((string) ($serviceCard['name'] ?? 'Procedura')) ?></h3>
                        <p><?= \PPStudio\Support\ViewHelper::escape((string) ($serviceCard['description'] ?? '')) ?></p>
                        <div class="service-highlights">
                            <p><strong>Kategorie:</strong> <?= \PPStudio\Support\ViewHelper::escape((string) ($serviceCard['category'] ?? 'Ostatní služby')) ?></p>
                            <p><strong>Délka:</strong> <?= \PPStudio\Support\ViewHelper::escape((string) ($serviceCard['duration'] ?? 'Dle vybrané procedury')) ?></p>
                            <p><strong>Cena:</strong> <?= \PPStudio\Support\ViewHelper::escape((string) ($serviceCard['price'] ?? 'Cena na dotaz')) ?></p>
                        </div>
                        <div class="service-card-actions">
                            <a href="/cenik.php#ceniky" class="service-link">Zobrazit v ceníku</a>
                            <a href="/rezervace.php" class="service-link service-link-primary">Rezervovat</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="services-grid">
                <div class="service-card">
                    <span class="service-badge">Aktuálně připravujeme</span>
                    <i class="fas fa-leaf" aria-hidden="true"></i>
                    <h3>Přehled služeb se načítá</h3>
                    <p>Aktuální nabídku právě nelze načíst. Ceník a rezervace ale zůstávají k dispozici.</p>
                    <div class="service-highlights">
                        <p><strong>Ceník:</strong> přehled procedur najdete i v detailním ceníku</p>
                        <p><strong>Rezervace:</strong> termín můžete vybrat hned online</p>
                        <p><strong>Pomoc s výběrem:</strong> službu společně doladíme podle vašich potřeb</p>
                    </div>
                    <div class="service-card-actions">
                        <a href="/cenik.php#ceniky" class="service-link">Otevřít ceník</a>
                        <a href="/rezervace.php" class="service-link service-link-primary">Rezervovat</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <div class="services-help">
            <p>Nejste si jistá výběrem služby? Vyberte orientačně termín a vše doladíme společně na místě.</p>
            <a href="/rezervace.php" class="cta-button cta-button-primary">Chci doporučit službu a rezervovat</a>
        </div>
    </div>
</section>
