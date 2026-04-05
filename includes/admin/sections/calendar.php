                <section class="admin-single" id="kalendar">
                    <div class="admin-card">
                        <p class="eyebrow">Kalendář</p>
                        <h2>Přehled rezervací v kalendáři</h2>
                        <div class="admin-form">
                            <label class="full-span">
                                <span>URL kalendářového feedu rezervací</span>
                                <input type="text" value="<?= escape(webcalToHttps($subscriptionCalendarUrl)) ?>" readonly>
                            </label>
                        </div>
                        <div class="table-actions">
                            <?php if ($subscriptionCalendarUrl !== ''): ?>
                                <a class="button button-primary button-small" href="<?= escape(webcalToHttps($subscriptionCalendarUrl)) ?>" target="_blank" rel="noreferrer">Náhled feedu</a>
                            <?php endif; ?>
                        </div>
                        <p class="form-hint">Rezervace se propisují do vlastního kalendářového feedu automaticky.</p>
                    </div>
                </section>
