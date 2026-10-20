SET FOREIGN_KEY_CHECKS = 0;

SELECT IFNULL(GROUP_CONCAT(CONCAT('TRUNCATE TABLE `', TABLE_SCHEMA, '`.`', TABLE_NAME, '`') SEPARATOR '; '), 'SELECT "NOOP"')
INTO @trunc
FROM information_schema.tables
WHERE table_schema = 'raflora_db'
  AND table_name IN (
    'booking_items',
    'booking_proposals',
    'payments',
    'bookings',
    'audit_logs',
    'inventory_movements',
    'inventory_logs'
  );

PREPARE stmt FROM @trunc;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;
