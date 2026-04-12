        <aside class="admin-sidebar">
            <div>
                <p class="eyebrow">PPStudio admin</p>
                <h2><?= escape(setting($siteSettings, 'site_name', defaultSiteName())) ?></h2>
            </div>
            <nav class="admin-side-nav">
                <p class="admin-nav-group">Provoz</p>
                <a href="admin.php?tab=dashboard" <?= $adminTab === 'dashboard' ? 'aria-current="page"' : '' ?>>Dashboard</a>
                <a href="admin.php?tab=rezervace-list" <?= $adminTab === 'rezervace-list' ? 'aria-current="page"' : '' ?>>Rezervace</a>
                <a href="admin.php?tab=dostupnost" <?= $adminTab === 'dostupnost' ? 'aria-current="page"' : '' ?>>Dostupnost</a>
                <a href="admin.php?tab=poukazy" <?= $adminTab === 'poukazy' ? 'aria-current="page"' : '' ?>>Poukazy</a>

                <p class="admin-nav-group">Obsah</p>
                <a href="admin.php?tab=sluzby-admin" <?= $adminTab === 'sluzby-admin' ? 'aria-current="page"' : '' ?>>Služby</a>
                <a href="admin.php?tab=media" <?= $adminTab === 'media' ? 'aria-current="page"' : '' ?>>Fotky a galerie</a>

                <p class="admin-nav-group">Nastavení</p>
                <a href="admin.php?tab=nastaveni" <?= $adminTab === 'nastaveni' ? 'aria-current="page"' : '' ?>>Nastavení studia a webu</a>
                <a href="admin.php?tab=antispam-log" <?= $adminTab === 'antispam-log' ? 'aria-current="page"' : '' ?>>Antispam log</a>
                <a href="index.php" target="_blank" rel="noreferrer">Otevřít web</a>
            </nav>
            <form method="post">
                <?= csrfInputField() ?>
                <button class="button button-secondary button-small admin-logout" type="submit" name="admin_logout" value="1">Odhlásit</button>
            </form>
        </aside>
