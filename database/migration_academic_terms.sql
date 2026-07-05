-- ===========================================================================
-- Migration: academic-term stamps on transactional records.
-- A "term" = academic_year + semester. Records are stamped with the hostel's
-- active term at creation so history survives a term rollover. Safe to re-run.
-- ===========================================================================
USE chms_hostel;

-- allocations.semester (academic_year already present) ----------------------
SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA='chms_hostel' AND TABLE_NAME='allocations' AND COLUMN_NAME='semester');
SET @sql := IF(@has=0,
    'ALTER TABLE allocations ADD COLUMN semester VARCHAR(40) NULL AFTER academic_year',
    'SELECT "allocations.semester exists"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- invoices.academic_year / semester -----------------------------------------
SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA='chms_hostel' AND TABLE_NAME='invoices' AND COLUMN_NAME='academic_year');
SET @sql := IF(@has=0,
    'ALTER TABLE invoices ADD COLUMN academic_year VARCHAR(40) NULL AFTER allocation_id, ADD COLUMN semester VARCHAR(40) NULL AFTER academic_year',
    'SELECT "invoices term columns exist"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- payments.academic_year / semester -----------------------------------------
SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA='chms_hostel' AND TABLE_NAME='payments' AND COLUMN_NAME='academic_year');
SET @sql := IF(@has=0,
    'ALTER TABLE payments ADD COLUMN academic_year VARCHAR(40) NULL AFTER student_id, ADD COLUMN semester VARCHAR(40) NULL AFTER academic_year',
    'SELECT "payments term columns exist"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Best-effort backfill: invoices <- their allocation's term ------------------
UPDATE invoices i
JOIN allocations a ON a.id = i.allocation_id
SET i.academic_year = a.academic_year, i.semester = a.semester
WHERE i.academic_year IS NULL AND a.academic_year IS NOT NULL;

-- Best-effort backfill: payments <- their invoice's term ---------------------
UPDATE payments p
JOIN invoices i ON i.id = p.invoice_id
SET p.academic_year = i.academic_year, p.semester = i.semester
WHERE p.academic_year IS NULL AND i.academic_year IS NOT NULL;
