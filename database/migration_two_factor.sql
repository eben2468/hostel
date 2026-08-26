-- ===========================================================================
-- Two-Factor Authentication (email one-time codes)
--
-- Deliberately contains NO "USE <database>" line so it runs against whichever
-- database is already selected. On shared hosting the database is named by the
-- control panel (hostel, cpaneluser_hostel, ...) and the MySQL user has no
-- rights to any other one, so a hardcoded name fails with
--     #1044 - Access denied for user '...' to database 'chms_hostel'
--
-- phpMyAdmin : select your database in the left sidebar first, then
--              Import -> choose this file -> Go.
-- Command line: mysql -u USER -p DATABASE < database/migration_two_factor.sql
--
-- Safe to run more than once.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS two_factor_codes (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          NOT NULL,
    code_hash  VARCHAR(255) NOT NULL,        -- the OTP is hashed, never stored in clear
    sent_to    VARCHAR(255) NOT NULL,        -- address(es) the code was delivered to
    attempts   TINYINT      NOT NULL DEFAULT 0,
    consumed_at DATETIME    NULL,            -- set the moment a code is accepted
    expires_at DATETIME     NOT NULL,
    ip_address VARCHAR(64)  NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_2fa_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_2fa_user (user_id, consumed_at)
) ENGINE=InnoDB;

-- Defaults for the System Settings screen. INSERT IGNORE keeps any values an
-- administrator has already saved.
--   twofa_enabled    master switch, "1" or "0"
--   twofa_roles      CSV of roles that must complete 2FA, e.g. "admin,finance"
--   twofa_recipients JSON map of role => override recipient email(s)
INSERT IGNORE INTO settings (`key`, value) VALUES
    ('twofa_enabled',    '0'),
    ('twofa_roles',      ''),
    ('twofa_recipients', '');

-- Confirms the migration applied: expect one table row and three setting rows.
SELECT
    (SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'two_factor_codes')  AS two_factor_codes_table,
    (SELECT COUNT(*) FROM settings WHERE `key` LIKE 'twofa%')               AS twofa_settings;
