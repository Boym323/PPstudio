<nav>
    <div class="container">
        <a href="/" class="logo">
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
            <li><a href="/" class="<?= $activeNav === 'home' ? 'active' : '' ?>">Domů</a></li>
            <li><a href="/sluzby" class="<?= $activeNav === 'services' ? 'active' : '' ?>">Služby</a></li>
            <li><a href="/cenik" class="<?= $activeNav === 'pricing' ? 'active' : '' ?>">Ceník</a></li>
            <li><a href="/o-mne" class="<?= $activeNav === 'about' ? 'active' : '' ?>">O mně</a></li>
            <li><a href="/prostory" class="<?= $activeNav === 'spaces' ? 'active' : '' ?>">Prostory</a></li>
            <li><a href="/recenze" class="<?= $activeNav === 'reviews' ? 'active' : '' ?>">Recenze</a></li>
            <li><a href="/rezervace" class="<?= $activeNav === 'reservation' ? 'active' : '' ?>">Rezervace & kontakt</a></li>
        </ul>
    </div>
</nav>
