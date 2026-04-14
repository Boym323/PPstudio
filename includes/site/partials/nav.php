<nav>
    <div class="container">
        <a href="/index.php" class="logo">
            <span class="logo-title">PP Studio</span>
            <span class="logo-subtitle">Cosmetics &amp; Laminations</span>
        </a>
        <button
            class="menu-toggle"
            type="button"
            aria-label="Otevřít menu"
            aria-controls="primary-nav-links"
            aria-expanded="false"
        >&#9776;</button>
        <ul class="nav-links" id="primary-nav-links">
            <li><a href="/index.php" class="<?= $activeNav === 'home' ? 'active' : '' ?>">Domů</a></li>
            <li><a href="/sluzby.php" class="<?= $activeNav === 'services' ? 'active' : '' ?>">Služby</a></li>
            <li><a href="/cenik.php" class="<?= $activeNav === 'pricing' ? 'active' : '' ?>">Ceník</a></li>
            <li><a href="/o-studiu.php" class="<?= $activeNav === 'about' ? 'active' : '' ?>">O mně</a></li>
            <li><a href="/galerie.php" class="<?= $activeNav === 'spaces' ? 'active' : '' ?>">Prostory</a></li>
            <li><a href="/recenze.php" class="<?= $activeNav === 'reviews' ? 'active' : '' ?>">Recenze</a></li>
            <li><a href="/rezervace.php" class="<?= $activeNav === 'reservation' ? 'active' : '' ?>">Rezervace & kontakt</a></li>
        </ul>
    </div>
</nav>
