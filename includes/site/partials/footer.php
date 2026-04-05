<footer>
    <div class="footer-bottom">
        <?php
        $startYear = 2025;
        $currentYear = (int) date('Y');
        $yearLabel = $currentYear > $startYear ? $startYear . '–' . $currentYear : (string) $startYear;
        ?>
        &copy; <?= htmlspecialchars($yearLabel, ENT_QUOTES, 'UTF-8') ?> PP Studio &nbsp; | &nbsp; Všechna práva vyhrazena.
    </div>
</footer>
