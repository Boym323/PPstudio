<section class="reviews-section" id="reviews">
    <div class="reviews-container">
        <h1 class="reviews-title">Hodnocení našich klientek</h1>
        <p class="reviews-lead">Autentické recenze z Google a možnost přidat vlastní hodnocení na Google i Firmy.cz.</p>
        <div class="reviews-links">
            <?php if ($googleReviewsUrl !== ''): ?>
                <a class="review-source-link" href="<?= \PPStudio\Support\ViewHelper::escape($googleReviewsUrl) ?>" target="_blank" rel="noreferrer noopener">Přidat recenzi na Google</a>
            <?php endif; ?>
            <?php if ($firmyReviewsUrl !== ''): ?>
                <a class="review-source-link" href="<?= \PPStudio\Support\ViewHelper::escape($firmyReviewsUrl) ?>" target="_blank" rel="noreferrer noopener">Přidat recenzi na Firmy.cz</a>
            <?php endif; ?>
        </div>

        <div class="reviews-embed-grid">
            <article class="review-embed-card review-embed-card-google">
                <h3>Google recenze</h3>
                <div class="google-reviews-summary" id="google-reviews-summary">Načítám přehled Google recenzí…</div>
                <div class="reviews-list" id="google-reviews-list" data-google-reviews-widget>
                    <div class="reviews-empty">Načítám recenze…</div>
                </div>
            </article>

            <?php if ($firmyEmbed !== ''): ?>
                <article class="review-embed-card">
                    <h3>Firmy.cz recenze</h3>
                    <div class="review-embed-wrap"><?= $firmyEmbed ?></div>
                </article>
            <?php endif; ?>
        </div>

        <?php if ($firmyEmbed === ''): ?>
            <div class="reviews-empty">Firmy.cz widget zatím není vyplněný v administraci.</div>
        <?php endif; ?>

    </div>
</section>
