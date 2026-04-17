                <div class="section-heading">
                    <p class="eyebrow">Provozní správa</p>
                    <h1>Rychlé řízení termínů, dostupnosti a služeb</h1>
                    <p>Zjednodušené rozhraní pro každodenní provoz: rezervace, kalendář dostupnosti a služby na jednom místě.</p>
                </div>

                <?php if ($message !== ''): ?>
                    <div class="alert alert-success"><?= \PPStudio\Support\ViewHelper::escape($message) ?></div>
                <?php endif; ?>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-error"><?= \PPStudio\Support\ViewHelper::escape($error) ?></div>
                <?php endif; ?>

                <div class="admin-toast" data-admin-toast hidden aria-live="polite" aria-atomic="true"></div>
