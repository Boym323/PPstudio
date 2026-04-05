ALTER TABLE rezervace
    ADD COLUMN IF NOT EXISTS cena_v_dobe_rezervace DECIMAL(10,2) NULL AFTER sluzba;

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
