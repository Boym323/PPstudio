CREATE TABLE IF NOT EXISTS kategorie (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nazev VARCHAR(120) NOT NULL,
    poradi INT NULL,
    aktivni TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_kategorie_nazev (nazev)
);

CREATE TABLE IF NOT EXISTS sluzby (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nazev VARCHAR(150) NOT NULL,
    kategorie_id INT UNSIGNED NOT NULL,
    stitek VARCHAR(80) NULL,
    popis TEXT NULL,
    cena DECIMAL(10,2) NULL,
    doba_trvani INT NULL,
    aktivni TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sluzby_kategorie (kategorie_id),
    KEY idx_sluzby_kategorie_nazev (kategorie_id, nazev),
    CONSTRAINT fk_sluzby_kategorie
        FOREIGN KEY (kategorie_id) REFERENCES kategorie(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS rezervace (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    jmeno VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    telefon VARCHAR(50) NULL,
    zdroj VARCHAR(50) NULL,
    poznamka_klienta TEXT NULL,
    poznamka_admina TEXT NULL,
    sluzba INT UNSIGNED NOT NULL,
    cena_v_dobe_rezervace DECIMAL(10,2) NULL,
    doba_trvani_v_dobe_rezervace SMALLINT UNSIGNED NOT NULL,
    datum_cas DATETIME NOT NULL,
    stav ENUM('nova', 'potvrzena', 'dokoncena', 'zrusena') NOT NULL DEFAULT 'nova',
    duvod_zruseni VARCHAR(255) NULL,
    zruseno_kym VARCHAR(40) NULL,
    zruseno_uzivatel VARCHAR(120) NULL,
    zruseno_at DATETIME NULL,
    reminder_sent_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_rezervace_zruseno_at (zruseno_at),
    KEY idx_rezervace_reminder_sent_at (reminder_sent_at),
    KEY idx_rezervace_stav_datum_cas (stav, datum_cas),
    KEY idx_rezervace_datum_cas_stav (datum_cas, stav),
    KEY idx_rezervace_sluzba_datum_cas_stav (sluzba, datum_cas, stav),
    KEY idx_rezervace_reminder_queue (reminder_sent_at, stav, datum_cas),
    CONSTRAINT fk_rezervace_sluzba
        FOREIGN KEY (sluzba) REFERENCES sluzby(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS historie_cen_sluzeb (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sluzba_id INT UNSIGNED NOT NULL,
    cena DECIMAL(10,2) NOT NULL,
    platna_od DATETIME NOT NULL,
    platna_do DATETIME NULL,
    otevrena_flag TINYINT
        AS (CASE WHEN platna_do IS NULL THEN 1 ELSE NULL END) STORED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_historie_cen_sluzeb_sluzba_platna_od (sluzba_id, platna_od),
    KEY idx_historie_cen_sluzeb_sluzba_platna_do (sluzba_id, platna_do),
    UNIQUE KEY uniq_historie_cen_sluzeb_otevrena (sluzba_id, otevrena_flag),
    CONSTRAINT chk_historie_cen_sluzeb_time_order CHECK (platna_do IS NULL OR platna_do > platna_od),
    CONSTRAINT fk_historie_cen_sluzeb_sluzba
        FOREIGN KEY (sluzba_id) REFERENCES sluzby(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS dostupnost (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    start_at DATETIME NOT NULL,
    end_at DATETIME NOT NULL,
    poznamka VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_dostupnost_start_end (start_at, end_at),
    KEY idx_dostupnost_end_start (end_at, start_at),
    CONSTRAINT chk_dostupnost_time_order CHECK (end_at > start_at)
);

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
    KEY idx_poukazy_emailed_at (emailed_at),
    CONSTRAINT chk_poukazy_nonnegative CHECK (puvodni_hodnota >= 0 AND zustatek >= 0 AND zustatek <= puvodni_hodnota)
);

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
    CONSTRAINT chk_poukaz_cerpani_castka_positive CHECK (castka > 0),
    CONSTRAINT fk_poukaz_cerpani_poukaz
        FOREIGN KEY (poukaz_id) REFERENCES poukazy(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_poukaz_cerpani_rezervace
        FOREIGN KEY (rezervace_id) REFERENCES rezervace(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
);

INSERT INTO kategorie (nazev, poradi)
VALUES
    ('Pletova pece', 1),
    ('Oboci a rasy', 2)
ON DUPLICATE KEY UPDATE poradi = VALUES(poradi);

INSERT INTO sluzby (nazev, kategorie_id, popis, cena, doba_trvani)
VALUES
    ('Hloubkove cisteni pleti', (SELECT id FROM kategorie WHERE nazev = 'Pletova pece' LIMIT 1), 'Setrne osetreni pro sjednoceni, zklidneni a osvezeni pleti.', 1290, 75),
    ('Hydratacni ritual', (SELECT id FROM kategorie WHERE nazev = 'Pletova pece' LIMIT 1), 'Intenzivni hydratace s jemnou masazi a zaverem pro rozzareny vzhled.', 1490, 90),
    ('Lash & brow care', (SELECT id FROM kategorie WHERE nazev = 'Oboci a rasy' LIMIT 1), 'Uprava oboci a ras pro cisty a prirozene elegantni vysledek.', 890, 45);

INSERT INTO historie_cen_sluzeb (sluzba_id, cena, platna_od, platna_do)
SELECT s.id, s.cena, COALESCE(s.created_at, NOW()), NULL
FROM sluzby s
LEFT JOIN historie_cen_sluzeb h ON h.sluzba_id = s.id AND h.platna_do IS NULL
WHERE s.cena IS NOT NULL
  AND h.id IS NULL;

INSERT INTO nastaveni (setting_key, setting_value)
VALUES
    ('site_name', 'PPStudio'),
    ('site_url', 'https://www.ppstudio.cz'),
    ('google_reviews_url', ''),
    ('firmy_reviews_url', ''),
    ('firmy_reviews_embed', ''),
    ('google_place_id', ''),
    ('google_reviews_language', 'cs'),
    ('notification_emails', 'info@ppstudio.cz'),
    ('availability_story_background', '')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
