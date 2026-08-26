-- ===========================================================================
-- Two-Factor Authentication (email one-time codes)
--
-- Run once on an existing installation:
--     mysql -u root -p < database/migration_two_factor.sql
-- Fresh installs get this from schema.sql automatically.
-- ===========================================================================
USE chms_hostel;

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
INSERT IGNORE INTO settings (`key`, value) VALUES
    ('twofa_enabled',    '0'),   -- master switch
    ('twofa_roles',      ''),    -- CSV of roles that must complete 2FA, e.g. "admin,finance"
    ('twofa_recipients', '');    -- JSON map of role => override recipient email(s)
