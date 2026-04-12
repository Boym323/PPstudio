CREATE INDEX IF NOT EXISTS idx_rezervace_stav_datum_cas ON rezervace (stav, datum_cas);
CREATE INDEX IF NOT EXISTS idx_rezervace_datum_cas_stav ON rezervace (datum_cas, stav);
CREATE INDEX IF NOT EXISTS idx_rezervace_sluzba_datum_cas_stav ON rezervace (sluzba, datum_cas, stav);
CREATE INDEX IF NOT EXISTS idx_rezervace_reminder_queue ON rezervace (reminder_sent_at, stav, datum_cas);

CREATE INDEX IF NOT EXISTS idx_dostupnost_start_end ON dostupnost (start_at, end_at);

ALTER TABLE historie_cen_sluzeb
    ADD COLUMN IF NOT EXISTS otevrena_flag TINYINT
        AS (CASE WHEN platna_do IS NULL THEN 1 ELSE NULL END) STORED;

CREATE UNIQUE INDEX IF NOT EXISTS uniq_historie_cen_sluzeb_otevrena
    ON historie_cen_sluzeb (sluzba_id, otevrena_flag);

SET @ddl = (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.table_constraints
            WHERE table_schema = DATABASE()
              AND table_name = 'dostupnost'
              AND constraint_name = 'chk_dostupnost_time_order'
        ),
        'SELECT 1',
        'ALTER TABLE dostupnost ADD CONSTRAINT chk_dostupnost_time_order CHECK (end_at > start_at)'
    )
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @ddl = (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.table_constraints
            WHERE table_schema = DATABASE()
              AND table_name = 'historie_cen_sluzeb'
              AND constraint_name = 'chk_historie_cen_sluzeb_time_order'
        ),
        'SELECT 1',
        'ALTER TABLE historie_cen_sluzeb ADD CONSTRAINT chk_historie_cen_sluzeb_time_order CHECK (platna_do IS NULL OR platna_do > platna_od)'
    )
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @ddl = (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.table_constraints
            WHERE table_schema = DATABASE()
              AND table_name = 'poukazy'
              AND constraint_name = 'chk_poukazy_nonnegative'
        ),
        'SELECT 1',
        'ALTER TABLE poukazy ADD CONSTRAINT chk_poukazy_nonnegative CHECK (puvodni_hodnota >= 0 AND zustatek >= 0 AND zustatek <= puvodni_hodnota)'
    )
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @ddl = (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.table_constraints
            WHERE table_schema = DATABASE()
              AND table_name = 'poukaz_cerpani'
              AND constraint_name = 'chk_poukaz_cerpani_castka_positive'
        ),
        'SELECT 1',
        'ALTER TABLE poukaz_cerpani ADD CONSTRAINT chk_poukaz_cerpani_castka_positive CHECK (castka > 0)'
    )
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
