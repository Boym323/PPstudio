ALTER TABLE rezervace
    ADD COLUMN IF NOT EXISTS telefon VARCHAR(50) NULL AFTER email,
    ADD COLUMN IF NOT EXISTS zdroj VARCHAR(50) NULL AFTER telefon,
    ADD COLUMN IF NOT EXISTS poznamka_klienta TEXT NULL AFTER telefon,
    ADD COLUMN IF NOT EXISTS poznamka_admina TEXT NULL AFTER poznamka_klienta,
    ADD COLUMN IF NOT EXISTS cena_v_dobe_rezervace DECIMAL(10,2) NULL AFTER sluzba,
    ADD COLUMN IF NOT EXISTS stav ENUM('nova', 'potvrzena', 'dokoncena', 'zrusena') NOT NULL DEFAULT 'nova' AFTER datum_cas;

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
    ('site_tagline', 'Jemna kosmeticka pece v klidnem, elegantnim prostredi'),
    ('hero_eyebrow', 'PPStudio'),
    ('hero_title', 'Misto, kde pece o plet dostava svuj cas, klid a pozornost.'),
    ('hero_text', 'Kazda navsteva je navrzena jako prijemny ritual s durazem na pohodli, individualni pristup a prirozene sebevedomy vysledek.'),
    ('owner_name', 'Vase jmeno'),
    ('owner_role', 'Zakladatelka a specialistka pece o plet'),
    ('owner_intro', 'Tady bude kratke predstaveni vas, vaseho pristupu a toho, co je pro vas v peci o klientky dulezite.'),
    ('owner_photo_caption', 'Profilova fotografie'),
    ('contact_email', 'info@ppstudio.cz'),
    ('contact_phone', '+420 777 000 000'),
    ('contact_instagram', '@ppstudio.cz'),
    ('instagram_url', 'https://www.instagram.com/beauty_touch_by_vp/'),
    ('google_reviews_url', ''),
    ('firmy_reviews_url', ''),
    ('google_reviews_embed', ''),
    ('firmy_reviews_embed', ''),
    ('google_place_id', ''),
    ('google_reviews_language', 'cs'),
    ('notification_emails', 'info@ppstudio.cz'),
    ('opening_hours', 'Po - Pa: 8:00 - 18:00 | So: dle objednani')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

DELETE FROM nastaveni WHERE setting_key = 'google_places_api_key';
