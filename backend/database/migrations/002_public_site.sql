-- ---------------------------------------------------------------------------
-- PsiClinic - public site, appointment requests and login hardening
-- Idempotent: it can run any number of times without further effects.
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;

-- Requests sent from the public form. They are not patients yet: the team
-- reviews them and, when it applies, creates the record and books the
-- appointment.
CREATE TABLE IF NOT EXISTS appointment_requests (
    id                         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid                       CHAR(36) NOT NULL,
    full_name                  VARCHAR(160) NOT NULL,
    email                      VARCHAR(180) NOT NULL,
    phone                      VARCHAR(40) DEFAULT NULL,
    contact_preference         ENUM('email','phone','whatsapp') NOT NULL DEFAULT 'email',
    modality                   ENUM('in_person','online','no_preference') NOT NULL DEFAULT 'no_preference',
    attendee                   ENUM('self','minor','other') NOT NULL DEFAULT 'self',
    preferred_professional_id  INT UNSIGNED DEFAULT NULL,
    preferred_times            VARCHAR(120) DEFAULT NULL,
    message                    VARCHAR(600) DEFAULT NULL,
    status                     ENUM('new','contacted','scheduled','dismissed') NOT NULL DEFAULT 'new',
    privacy_accepted_at        DATETIME NOT NULL,
    ip_hash                    CHAR(64) DEFAULT NULL,
    handled_by                 INT UNSIGNED DEFAULT NULL,
    handled_at                 DATETIME DEFAULT NULL,
    created_at                 DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                 DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_requests_uuid (uuid),
    KEY idx_requests_status (status, created_at),
    KEY idx_requests_ip (ip_hash, created_at),
    CONSTRAINT fk_requests_professional FOREIGN KEY (preferred_professional_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_requests_handler FOREIGN KEY (handled_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Failed login attempts. They used to live in the session, so deleting the
-- cookie was enough to reset the counter.
CREATE TABLE IF NOT EXISTS login_attempts (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    throttle_key  CHAR(64) NOT NULL,
    ip_address    VARCHAR(45) NOT NULL DEFAULT '',
    attempted_at  DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_attempts_key (throttle_key, attempted_at),
    KEY idx_attempts_ip (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Public profile of the professional: it only shows on the site when enabled.
SET @has_show_on_site := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'show_on_site'
);

SET @statement := IF(
    @has_show_on_site = 0,
    'ALTER TABLE users ADD COLUMN show_on_site TINYINT(1) NOT NULL DEFAULT 0 AFTER specialty, ADD COLUMN public_bio VARCHAR(400) DEFAULT NULL AFTER show_on_site',
    'SELECT 1'
);

PREPARE apply_show_on_site FROM @statement;
EXECUTE apply_show_on_site;
DEALLOCATE PREPARE apply_show_on_site;
