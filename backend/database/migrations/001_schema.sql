-- ---------------------------------------------------------------------------
-- PsiClinic - base schema (MySQL 8 / MariaDB 10.6+)
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS users (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid            CHAR(36) NOT NULL,
    username        VARCHAR(60) NOT NULL,
    email           VARCHAR(180) NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    full_name       VARCHAR(160) NOT NULL,
    role            ENUM('admin','psychologist','assistant','patient') NOT NULL DEFAULT 'psychologist',
    license_number  VARCHAR(60) DEFAULT NULL,
    specialty       VARCHAR(120) DEFAULT NULL,
    phone           VARCHAR(40) DEFAULT NULL,
    theme           ENUM('light','dark') NOT NULL DEFAULT 'light',
    patient_id      INT UNSIGNED DEFAULT NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at   DATETIME DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS patients (
    id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid                    CHAR(36) NOT NULL,
    record_number           VARCHAR(20) NOT NULL,
    first_name              VARCHAR(80) NOT NULL,
    last_name               VARCHAR(80) NOT NULL,
    birth_date              DATE DEFAULT NULL,
    gender                  ENUM('female','male','non_binary','undisclosed') NOT NULL DEFAULT 'undisclosed',
    document_type           VARCHAR(20) DEFAULT NULL,
    document_id             VARCHAR(40) DEFAULT NULL,
    email                   VARCHAR(180) DEFAULT NULL,
    phone                   VARCHAR(40) DEFAULT NULL,
    address                 VARCHAR(220) DEFAULT NULL,
    city                    VARCHAR(80) DEFAULT NULL,
    country                 VARCHAR(80) DEFAULT NULL,
    occupation              VARCHAR(120) DEFAULT NULL,
    marital_status          VARCHAR(40) DEFAULT NULL,
    emergency_contact_name  VARCHAR(140) DEFAULT NULL,
    emergency_contact_phone VARCHAR(40) DEFAULT NULL,
    referred_by             VARCHAR(140) DEFAULT NULL,
    reason_for_consult      TEXT DEFAULT NULL,
    relevant_history        TEXT DEFAULT NULL,
    current_medication      TEXT DEFAULT NULL,
    risk_level              ENUM('none','low','moderate','high') NOT NULL DEFAULT 'none',
    status                  ENUM('active','on_hold','discharged') NOT NULL DEFAULT 'active',
    psychologist_id         INT UNSIGNED DEFAULT NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_patients_record (record_number),
    KEY idx_patients_name (last_name, first_name),
    KEY idx_patients_status (status),
    CONSTRAINT fk_patients_psychologist FOREIGN KEY (psychologist_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The users -> patients relation is added afterwards because both tables
-- reference each other. The block is idempotent so the migration can run
-- again on an existing database.
SET @fk_users_patient := (
    SELECT COUNT(*) FROM information_schema.table_constraints
    WHERE constraint_schema = DATABASE() AND constraint_name = 'fk_users_patient'
);

SET @statement := IF(
    @fk_users_patient = 0,
    'ALTER TABLE users ADD CONSTRAINT fk_users_patient FOREIGN KEY (patient_id) REFERENCES patients (id) ON DELETE SET NULL',
    'SELECT 1'
);

PREPARE apply_fk FROM @statement;
EXECUTE apply_fk;
DEALLOCATE PREPARE apply_fk;

CREATE TABLE IF NOT EXISTS appointments (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid            CHAR(36) NOT NULL,
    patient_id      INT UNSIGNED NOT NULL,
    psychologist_id INT UNSIGNED NOT NULL,
    starts_at       DATETIME NOT NULL,
    ends_at         DATETIME NOT NULL,
    modality        ENUM('in_person','online','phone') NOT NULL DEFAULT 'in_person',
    status          ENUM('scheduled','confirmed','completed','cancelled','no_show') NOT NULL DEFAULT 'scheduled',
    session_type    VARCHAR(60) DEFAULT NULL,
    location        VARCHAR(160) DEFAULT NULL,
    meeting_url     VARCHAR(255) DEFAULT NULL,
    fee             DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    notes           TEXT DEFAULT NULL,
    created_by      INT UNSIGNED DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_appointments_range (starts_at, ends_at),
    KEY idx_appointments_patient (patient_id),
    CONSTRAINT fk_appointments_patient FOREIGN KEY (patient_id) REFERENCES patients (id) ON DELETE CASCADE,
    CONSTRAINT fk_appointments_psychologist FOREIGN KEY (psychologist_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clinical_notes (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid            CHAR(36) NOT NULL,
    patient_id      INT UNSIGNED NOT NULL,
    appointment_id  INT UNSIGNED DEFAULT NULL,
    author_id       INT UNSIGNED NOT NULL,
    format          ENUM('soap','dap','free') NOT NULL DEFAULT 'soap',
    session_number  SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    session_date    DATE NOT NULL,
    subjective      TEXT DEFAULT NULL,
    objective       TEXT DEFAULT NULL,
    assessment      TEXT DEFAULT NULL,
    plan            TEXT DEFAULT NULL,
    interventions   VARCHAR(255) DEFAULT NULL,
    homework        TEXT DEFAULT NULL,
    mood_score      TINYINT UNSIGNED DEFAULT NULL,
    risk_level      ENUM('none','low','moderate','high') NOT NULL DEFAULT 'none',
    is_locked       TINYINT(1) NOT NULL DEFAULT 0,
    locked_at       DATETIME DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notes_patient (patient_id, session_date),
    CONSTRAINT fk_notes_patient FOREIGN KEY (patient_id) REFERENCES patients (id) ON DELETE CASCADE,
    CONSTRAINT fk_notes_appointment FOREIGN KEY (appointment_id) REFERENCES appointments (id) ON DELETE SET NULL,
    CONSTRAINT fk_notes_author FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS diagnoses (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    patient_id  INT UNSIGNED NOT NULL,
    `system`    ENUM('icd10','dsm5') NOT NULL DEFAULT 'icd10',
    code        VARCHAR(20) NOT NULL,
    title       VARCHAR(200) NOT NULL,
    status      ENUM('active','remission','resolved','ruled_out') NOT NULL DEFAULT 'active',
    onset_date  DATE DEFAULT NULL,
    notes       TEXT DEFAULT NULL,
    created_by  INT UNSIGNED DEFAULT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_diagnoses_patient (patient_id),
    CONSTRAINT fk_diagnoses_patient FOREIGN KEY (patient_id) REFERENCES patients (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assessments (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid             CHAR(36) NOT NULL,
    patient_id       INT UNSIGNED NOT NULL,
    instrument_code  VARCHAR(30) NOT NULL,
    assigned_by      INT UNSIGNED DEFAULT NULL,
    status           ENUM('pending','completed') NOT NULL DEFAULT 'pending',
    answers          JSON DEFAULT NULL,
    total_score      SMALLINT DEFAULT NULL,
    subscale_scores  JSON DEFAULT NULL,
    severity         VARCHAR(60) DEFAULT NULL,
    interpretation   TEXT DEFAULT NULL,
    clinician_notes  TEXT DEFAULT NULL,
    administered_at  DATETIME DEFAULT NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_assessments_patient (patient_id, instrument_code),
    CONSTRAINT fk_assessments_patient FOREIGN KEY (patient_id) REFERENCES patients (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS consents (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid          CHAR(36) NOT NULL,
    patient_id    INT UNSIGNED NOT NULL,
    template_code VARCHAR(40) NOT NULL,
    title         VARCHAR(180) NOT NULL,
    body          MEDIUMTEXT NOT NULL,
    status        ENUM('pending','signed','revoked') NOT NULL DEFAULT 'pending',
    signed_name   VARCHAR(160) DEFAULT NULL,
    signature_svg MEDIUMTEXT DEFAULT NULL,
    signed_at     DATETIME DEFAULT NULL,
    signed_ip     VARCHAR(45) DEFAULT NULL,
    created_by    INT UNSIGNED DEFAULT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_consents_patient (patient_id),
    CONSTRAINT fk_consents_patient FOREIGN KEY (patient_id) REFERENCES patients (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS documents (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid          CHAR(36) NOT NULL,
    patient_id    INT UNSIGNED NOT NULL,
    title         VARCHAR(180) NOT NULL,
    category      VARCHAR(60) NOT NULL DEFAULT 'general',
    stored_name   VARCHAR(180) NOT NULL,
    original_name VARCHAR(180) NOT NULL,
    mime_type     VARCHAR(120) NOT NULL,
    size_bytes    INT UNSIGNED NOT NULL DEFAULT 0,
    uploaded_by   INT UNSIGNED DEFAULT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_documents_patient (patient_id),
    CONSTRAINT fk_documents_patient FOREIGN KEY (patient_id) REFERENCES patients (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoices (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid        CHAR(36) NOT NULL,
    patient_id  INT UNSIGNED NOT NULL,
    number      VARCHAR(30) NOT NULL,
    issued_at   DATE NOT NULL,
    due_at      DATE DEFAULT NULL,
    subtotal    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    tax         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status      ENUM('draft','issued','paid','void') NOT NULL DEFAULT 'issued',
    notes       TEXT DEFAULT NULL,
    created_by  INT UNSIGNED DEFAULT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_invoices_number (number),
    CONSTRAINT fk_invoices_patient FOREIGN KEY (patient_id) REFERENCES patients (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_items (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_id  INT UNSIGNED NOT NULL,
    description VARCHAR(200) NOT NULL,
    quantity    DECIMAL(8,2) NOT NULL DEFAULT 1.00,
    unit_price  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    amount      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (id),
    KEY idx_items_invoice (invoice_id),
    CONSTRAINT fk_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_id INT UNSIGNED NOT NULL,
    paid_at    DATE NOT NULL,
    amount     DECIMAL(10,2) NOT NULL,
    method     ENUM('cash','card','transfer','insurance','other') NOT NULL DEFAULT 'transfer',
    reference  VARCHAR(80) DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_payments_invoice (invoice_id),
    CONSTRAINT fk_payments_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED DEFAULT NULL,
    action     VARCHAR(60) NOT NULL,
    entity     VARCHAR(60) NOT NULL,
    entity_id  INT UNSIGNED DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_created (created_at),
    KEY idx_audit_entity (entity, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    setting_key   VARCHAR(60) NOT NULL,
    setting_value TEXT DEFAULT NULL,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
