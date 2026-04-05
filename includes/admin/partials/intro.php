                <div class="section-heading">
                    <p class="eyebrow">Správa webu</p>
                    <h1>Přehledné řízení obsahu, termínů i rezervací</h1>
                    <p>Boční lišta drží hlavní pracovní sekce po ruce a nastavení studia zůstává bokem, dokud ho zrovna nepotřebujete.</p>
                </div>

                <?php if ($message !== ''): ?>
                    <div class="alert alert-success"><?= escape($message) ?></div>
                <?php endif; ?>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-error"><?= escape($error) ?></div>
                <?php endif; ?>
