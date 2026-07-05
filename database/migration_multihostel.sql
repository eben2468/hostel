-- ===========================================================================
-- Migration: Multi-hostel data isolation (RBAC)
-- Adds a hostel binding to users and students so every record can be scoped
-- to a single hostel. Safe to run on an existing chms_hostel database.
-- ===========================================================================
USE chms_hostel;

-- ---------------------------------------------------------------------------
-- users.hostel_id : NULL = global super admin; set = bound to one hostel
-- ---------------------------------------------------------------------------
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'chms_hostel' AND TABLE_NAME = 'users' AND COLUMN_NAME = 'hostel_id'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE users
        ADD COLUMN hostel_id INT NULL AFTER role,
        ADD INDEX idx_users_hostel (hostel_id),
        ADD CONSTRAINT fk_users_hostel FOREIGN KEY (hostel_id) REFERENCES hostels(id) ON DELETE SET NULL',
    'SELECT "users.hostel_id already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- students.hostel_id : the hostel a student is registered/assigned to
-- ---------------------------------------------------------------------------
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'chms_hostel' AND TABLE_NAME = 'students' AND COLUMN_NAME = 'hostel_id'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE students
        ADD COLUMN hostel_id INT NULL AFTER user_id,
        ADD INDEX idx_students_hostel (hostel_id),
        ADD CONSTRAINT fk_students_hostel FOREIGN KEY (hostel_id) REFERENCES hostels(id) ON DELETE SET NULL',
    'SELECT "students.hostel_id already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- Backfill students.hostel_id from their active allocation -> room -> hostel
-- ---------------------------------------------------------------------------
UPDATE students s
JOIN allocations a ON a.student_id = s.id AND a.status IN ('active', 'checked_in')
JOIN rooms r       ON r.id = a.room_id
SET s.hostel_id = r.hostel_id
WHERE s.hostel_id IS NULL;
