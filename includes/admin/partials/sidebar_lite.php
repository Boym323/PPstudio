        <aside class="admin-sidebar">
            <div>
                <p class="eyebrow">PPStudio user</p>
                <h2><?= escape(setting($siteSettings, 'site_name', defaultSiteName())) ?></h2>
            </div>
            <nav class="admin-side-nav">
                <a href="/admin-lite.php?tab=dashboard" <?= $adminTab === 'dashboard' ? 'aria-current="page"' : '' ?>>Dashboard</a>
                <a href="/admin-lite.php?tab=rezervace-list" <?= $adminTab === 'rezervace-list' ? 'aria-current="page"' : '' ?>>Rezervace</a>
                <a href="/admin-lite.php?tab=dostupnost" <?= $adminTab === 'dostupnost' ? 'aria-current="page"' : '' ?>>Dostupnost</a>
                <a href="/admin-lite.php?tab=sluzby-admin" <?= $adminTab === 'sluzby-admin' ? 'aria-current="page"' : '' ?>>Služby</a>
                <a href="/index.php" target="_blank" rel="noreferrer">Otevřít web</a>
            </nav>
            <form method="post">
                <?= csrfInputField() ?>
                <button class="button button-secondary button-small admin-logout" type="submit" name="admin_logout" value="1">Odhlásit</button>
            </form>
        </aside>
