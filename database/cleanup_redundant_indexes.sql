SET @ddl = (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'rezervace'
              AND index_name = 'idx_rezervace_stav_datum'
        ),
        'DROP INDEX idx_rezervace_stav_datum ON rezervace',
        'SELECT 1'
    )
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @ddl = (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'dostupnost'
              AND index_name = 'idx_dostupnost_start_at'
        ),
        'DROP INDEX idx_dostupnost_start_at ON dostupnost',
        'SELECT 1'
    )
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
