        <aside class="admin-sidebar">
            <div>
                <p class="eyebrow">PPStudio admin</p>
                <h2><?= escape(setting($siteSettings, 'site_name', defaultSiteName())) ?></h2>
            </div>
            <nav class="admin-side-nav">
                <a href="admin.php?tab=dashboard" <?= $adminTab === 'dashboard' ? 'aria-current="page"' : '' ?>>Dashboard</a>
                <a href="admin.php?tab=rezervace-list" <?= $adminTab === 'rezervace-list' ? 'aria-current="page"' : '' ?>>Rezervace</a>
                <a href="admin.php?tab=kalendar" <?= $adminTab === 'kalendar' ? 'aria-current="page"' : '' ?>>Kalendář</a>
                <a href="admin.php?tab=emaily" <?= $adminTab === 'emaily' ? 'aria-current="page"' : '' ?>>E-mail</a>
                <a href="admin.php?tab=antispam-log" <?= $adminTab === 'antispam-log' ? 'aria-current="page"' : '' ?>>Antispam log</a>
                <a href="admin.php?tab=dostupnost" <?= $adminTab === 'dostupnost' ? 'aria-current="page"' : '' ?>>Dostupnost</a>
                <a href="admin.php?tab=sluzby-admin" <?= $adminTab === 'sluzby-admin' ? 'aria-current="page"' : '' ?>>Služby</a>
                <a href="admin.php?tab=poukazy" <?= $adminTab === 'poukazy' ? 'aria-current="page"' : '' ?>>Poukazy</a>
                <a href="admin.php?tab=media" <?= $adminTab === 'media' ? 'aria-current="page"' : '' ?>>Fotky a galerie</a>
                <a href="admin.php?tab=recenze-napojeni" <?= $adminTab === 'recenze-napojeni' ? 'aria-current="page"' : '' ?>>Recenze a social</a>
                <a href="admin.php?tab=nastaveni" <?= $adminTab === 'nastaveni' ? 'aria-current="page"' : '' ?>>Nastavení studia</a>
                <a href="index.php" target="_blank" rel="noreferrer">Otevřít web</a>
            </nav>
            <form method="post">
                <?= csrfInputField() ?>
                <button class="button button-secondary button-small admin-logout" type="submit" name="admin_logout" value="1">Odhlásit</button>
            </form>
        </aside>
