ALTER TABLE rezervace
    ADD COLUMN IF NOT EXISTS telefon VARCHAR(50) NULL AFTER email,
    ADD COLUMN IF NOT EXISTS zdroj VARCHAR(50) NULL AFTER telefon,
    ADD COLUMN IF NOT EXISTS poznamka_klienta TEXT NULL AFTER telefon,
    ADD COLUMN IF NOT EXISTS poznamka_admina TEXT NULL AFTER poznamka_klienta,
    ADD COLUMN IF NOT EXISTS cena_v_dobe_rezervace DECIMAL(10,2) NULL AFTER sluzba,
    ADD COLUMN IF NOT EXISTS stav ENUM('nova', 'potvrzena', 'dokoncena', 'zrusena') NOT NULL DEFAULT 'nova' AFTER datum_cas,
    ADD COLUMN IF NOT EXISTS duvod_zruseni VARCHAR(255) NULL AFTER stav,
    ADD COLUMN IF NOT EXISTS zruseno_kym VARCHAR(40) NULL AFTER duvod_zruseni,
    ADD COLUMN IF NOT EXISTS zruseno_uzivatel VARCHAR(120) NULL AFTER zruseno_kym,
    ADD COLUMN IF NOT EXISTS zruseno_at DATETIME NULL AFTER zruseno_uzivatel,
    ADD COLUMN IF NOT EXISTS reminder_sent_at DATETIME NULL AFTER zruseno_at;

CREATE INDEX IF NOT EXISTS idx_rezervace_zruseno_at ON rezervace (zruseno_at);
CREATE INDEX IF NOT EXISTS idx_rezervace_reminder_sent_at ON rezervace (reminder_sent_at);
CREATE INDEX IF NOT EXISTS idx_rezervace_stav_datum_cas ON rezervace (stav, datum_cas);
CREATE INDEX IF NOT EXISTS idx_rezervace_datum_cas_stav ON rezervace (datum_cas, stav);
CREATE INDEX IF NOT EXISTS idx_rezervace_sluzba_datum_cas_stav ON rezervace (sluzba, datum_cas, stav);
CREATE INDEX IF NOT EXISTS idx_rezervace_reminder_queue ON rezervace (reminder_sent_at, stav, datum_cas);

CREATE TABLE IF NOT EXISTS kategorie (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nazev VARCHAR(120) NOT NULL,
    poradi INT NULL,
    aktivni TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_kategorie_nazev (nazev)
);

ALTER TABLE sluzby
    ADD COLUMN IF NOT EXISTS kategorie_id INT UNSIGNED NULL AFTER nazev;

ALTER TABLE sluzby
    ADD COLUMN IF NOT EXISTS stitek VARCHAR(80) NULL AFTER kategorie_id;

ALTER TABLE kategorie
    ADD COLUMN IF NOT EXISTS aktivni TINYINT(1) NOT NULL DEFAULT 1 AFTER poradi;

ALTER TABLE sluzby
    ADD COLUMN IF NOT EXISTS aktivni TINYINT(1) NOT NULL DEFAULT 1 AFTER doba_trvani;

INSERT INTO kategorie (nazev, poradi)
SELECT
    COALESCE(NULLIF(TRIM(kategorie), ''), 'Ostatní služby') AS nazev,
    COALESCE(kategorie_poradi, 9999) AS poradi
FROM sluzby
GROUP BY COALESCE(NULLIF(TRIM(kategorie), ''), 'Ostatní služby'), COALESCE(kategorie_poradi, 9999)
ON DUPLICATE KEY UPDATE poradi = LEAST(COALESCE(poradi, 9999), COALESCE(VALUES(poradi), 9999));

INSERT INTO kategorie (nazev, poradi)
VALUES ('Ostatní služby', 9999)
ON DUPLICATE KEY UPDATE poradi = LEAST(COALESCE(poradi, 9999), 9999);

UPDATE sluzby s
LEFT JOIN kategorie k ON k.nazev = COALESCE(NULLIF(TRIM(s.kategorie), ''), 'Ostatní služby')
SET s.kategorie_id = k.id
WHERE s.kategorie_id IS NULL OR s.kategorie_id = 0;

ALTER TABLE sluzby
    MODIFY COLUMN kategorie_id INT UNSIGNED NOT NULL;

CREATE INDEX IF NOT EXISTS idx_sluzby_kategorie ON sluzby (kategorie_id);
CREATE INDEX IF NOT EXISTS idx_sluzby_kategorie_nazev ON sluzby (kategorie_id, nazev);

ALTER TABLE sluzby
    DROP COLUMN IF EXISTS kategorie,
    DROP COLUMN IF EXISTS kategorie_poradi;

CREATE TABLE IF NOT EXISTS nastaveni (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NULL
);

CREATE TABLE IF NOT EXISTS media (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(40) NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    title VARCHAR(190) NULL,
    subtitle VARCHAR(255) NULL,
    external_url VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE dostupnost
    ADD COLUMN IF NOT EXISTS poznamka VARCHAR(255) NULL AFTER end_at;

CREATE INDEX IF NOT EXISTS idx_dostupnost_start_end ON dostupnost (start_at, end_at);

CREATE TABLE IF NOT EXISTS security_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(100) NOT NULL,
    event_source VARCHAR(80) NOT NULL,
    severity ENUM('info', 'warning', 'error') NOT NULL DEFAULT 'info',
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NULL,
    context_json TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_security_events_created_at (created_at),
    KEY idx_security_events_type_created (event_type, created_at),
    KEY idx_security_events_source_created (event_source, created_at)
);

CREATE TABLE IF NOT EXISTS reservation_reminder_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    run_token VARCHAR(64) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    severity ENUM('info', 'warning', 'error') NOT NULL DEFAULT 'info',
    reservation_id INT UNSIGNED NULL,
    context_json TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_reminder_logs_run_token_created (run_token, created_at),
    KEY idx_reminder_logs_event_created (event_type, created_at),
    KEY idx_reminder_logs_reservation_created (reservation_id, created_at),
    CONSTRAINT fk_reminder_logs_reservation
        FOREIGN KEY (reservation_id) REFERENCES rezervace(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS poukazy (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kod VARCHAR(40) NOT NULL,
    puvodni_hodnota DECIMAL(10,2) NOT NULL,
    zustatek DECIMAL(10,2) NOT NULL,
    status ENUM('aktivni', 'vycerpan', 'storno') NOT NULL DEFAULT 'aktivni',
    issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATE NULL,
    recipient_name VARCHAR(180) NULL,
    recipient_email VARCHAR(190) NULL,
    note TEXT NULL,
    emailed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_poukazy_kod (kod),
    KEY idx_poukazy_status (status),
    KEY idx_poukazy_expires_at (expires_at),
    KEY idx_poukazy_recipient_email (recipient_email),
    KEY idx_poukazy_emailed_at (emailed_at)
);

ALTER TABLE poukazy
    ADD COLUMN IF NOT EXISTS recipient_email VARCHAR(190) NULL AFTER recipient_name,
    ADD COLUMN IF NOT EXISTS emailed_at DATETIME NULL AFTER note;

CREATE TABLE IF NOT EXISTS poukaz_cerpani (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    poukaz_id INT UNSIGNED NOT NULL,
    castka DECIMAL(10,2) NOT NULL,
    typ ENUM('cerpani', 'korekce_plus', 'korekce_minus') NOT NULL DEFAULT 'cerpani',
    rezervace_id INT UNSIGNED NULL,
    poznamka VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_poukaz_cerpani_poukaz (poukaz_id, created_at),
    KEY idx_poukaz_cerpani_rezervace (rezervace_id),
    CONSTRAINT fk_poukaz_cerpani_poukaz
        FOREIGN KEY (poukaz_id) REFERENCES poukazy(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_poukaz_cerpani_rezervace
        FOREIGN KEY (rezervace_id) REFERENCES rezervace(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS historie_cen_sluzeb (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sluzba_id INT UNSIGNED NOT NULL,
    cena DECIMAL(10,2) NOT NULL,
    platna_od DATETIME NOT NULL,
    platna_do DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_historie_cen_sluzeb_sluzba_platna_od (sluzba_id, platna_od),
    KEY idx_historie_cen_sluzeb_sluzba_platna_do (sluzba_id, platna_do),
    CONSTRAINT fk_historie_cen_sluzeb_sluzba
        FOREIGN KEY (sluzba_id) REFERENCES sluzby(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
);

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

INSERT INTO historie_cen_sluzeb (sluzba_id, cena, platna_od, platna_do)
SELECT s.id, s.cena, COALESCE(s.created_at, NOW()), NULL
FROM sluzby s
LEFT JOIN historie_cen_sluzeb h ON h.sluzba_id = s.id AND h.platna_do IS NULL
WHERE s.cena IS NOT NULL
  AND h.id IS NULL;

UPDATE rezervace r
INNER JOIN sluzby s ON s.id = r.sluzba
SET r.cena_v_dobe_rezervace = s.cena
WHERE r.cena_v_dobe_rezervace IS NULL;

INSERT INTO nastaveni (setting_key, setting_value)
VALUES
    ('site_name', 'PPStudio'),
    ('google_reviews_url', ''),
    ('firmy_reviews_url', ''),
    ('firmy_reviews_embed', ''),
    ('google_place_id', ''),
    ('google_reviews_language', 'cs'),
    ('notification_emails', 'info@ppstudio.cz'),
    ('availability_story_background', '')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

DELETE FROM nastaveni WHERE setting_key = 'google_places_api_key';
DELETE FROM nastaveni WHERE setting_key IN (
    'site_tagline',
    'contact_email',
    'contact_phone',
    'contact_instagram',
    'opening_hours',
    'google_reviews_embed',
    'hero_eyebrow',
    'hero_title',
    'hero_text',
    'owner_name',
    'owner_role',
    'owner_intro',
    'owner_photo_caption',
    'contact_transport',
    'contact_parking',
    'first_visit_note',
    'instagram_url',
    'instagram_feed_embed'
);
