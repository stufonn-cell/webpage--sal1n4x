-- ---------------------------------------------------------------------------
-- PsiClinic - catalogo CIE-11 y RIPS (Resolucion 2275 de 2023)
-- Idempotente: se puede ejecutar varias veces sin efectos adicionales.
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;

-- CIE-11 MMS, version 2025-01 en espanol (la adoptada por Colombia,
-- Resolucion 1442 de 2024). Se carga desde database/data con el instalador.
-- icd10_code: equivalencia de la OMS (11To10MapToOneCategory) para la
-- codificacion dual que exige la transicion.
CREATE TABLE IF NOT EXISTS icd11_codes (
    code        VARCHAR(20) NOT NULL,
    title_es    VARCHAR(400) NOT NULL,
    title_en    VARCHAR(400) NOT NULL,
    chapter     CHAR(2) NOT NULL,
    is_leaf     TINYINT(1) NOT NULL DEFAULT 1,
    icd10_code  VARCHAR(10) DEFAULT NULL,
    PRIMARY KEY (code),
    KEY idx_icd11_chapter (chapter)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reportes RIPS generados. El JSON se conserva tal como se envio.
CREATE TABLE IF NOT EXISTS rips_reports (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    num_nota           VARCHAR(20) NOT NULL,
    period_start       DATE NOT NULL,
    period_end         DATE NOT NULL,
    payload            LONGTEXT NOT NULL,
    status             ENUM('generated','validated','rejected') NOT NULL DEFAULT 'generated',
    cuv                VARCHAR(255) DEFAULT NULL,
    validation_result  LONGTEXT DEFAULT NULL,
    users_count        INT UNSIGNED NOT NULL DEFAULT 0,
    services_count     INT UNSIGNED NOT NULL DEFAULT 0,
    created_by         INT UNSIGNED DEFAULT NULL,
    sent_by            INT UNSIGNED DEFAULT NULL,
    sent_at            DATETIME DEFAULT NULL,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rips_num_nota (num_nota),
    CONSTRAINT fk_rips_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_rips_sender FOREIGN KEY (sent_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cada cita realizada se reporta una sola vez.
CREATE TABLE IF NOT EXISTS rips_report_items (
    report_id       INT UNSIGNED NOT NULL,
    appointment_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (report_id, appointment_id),
    UNIQUE KEY uq_rips_item_appointment (appointment_id),
    CONSTRAINT fk_rips_item_report FOREIGN KEY (report_id) REFERENCES rips_reports (id) ON DELETE CASCADE,
    CONSTRAINT fk_rips_item_appointment FOREIGN KEY (appointment_id) REFERENCES appointments (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Columnas nuevas. MySQL no admite ADD COLUMN IF NOT EXISTS: se consulta
-- information_schema y se ejecuta solo lo que falta.

-- Diagnosticos: sistema CIE-11, equivalente CIE-10 y diagnostico principal.
SET @statement := IF(
    (SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = 'diagnoses' AND column_name = 'icd10_code') = 0,
    'ALTER TABLE diagnoses MODIFY `system` ENUM(''icd10'',''icd11'',''dsm5'') NOT NULL DEFAULT ''icd11'', ADD COLUMN icd10_code VARCHAR(10) DEFAULT NULL AFTER code, ADD COLUMN is_primary TINYINT(1) NOT NULL DEFAULT 0 AFTER status',
    'SELECT 1'
);
PREPARE apply_change FROM @statement;
EXECUTE apply_change;
DEALLOCATE PREPARE apply_change;

-- Pacientes: datos que exige el RIPS (U01-U11).
SET @statement := IF(
    (SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = 'patients' AND column_name = 'biological_sex') = 0,
    'ALTER TABLE patients ADD COLUMN biological_sex ENUM(''M'',''F'',''I'') DEFAULT NULL AFTER gender, ADD COLUMN rips_user_type CHAR(2) NOT NULL DEFAULT ''12'' AFTER document_id, ADD COLUMN residence_country CHAR(3) NOT NULL DEFAULT ''170'' AFTER country, ADD COLUMN residence_municipality CHAR(5) DEFAULT NULL AFTER residence_country, ADD COLUMN residence_zone CHAR(2) NOT NULL DEFAULT ''01'' AFTER residence_municipality, ADD COLUMN origin_country CHAR(3) NOT NULL DEFAULT ''170'' AFTER residence_zone',
    'SELECT 1'
);
PREPARE apply_change FROM @statement;
EXECUTE apply_change;
DEALLOCATE PREPARE apply_change;

-- Profesionales: documento de identidad (C15-C16 del RIPS).
SET @statement := IF(
    (SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'document_number') = 0,
    'ALTER TABLE users ADD COLUMN document_type CHAR(2) NOT NULL DEFAULT ''CC'' AFTER full_name, ADD COLUMN document_number VARCHAR(20) DEFAULT NULL AFTER document_type, ADD COLUMN locale CHAR(2) NOT NULL DEFAULT ''es'' AFTER theme',
    'SELECT 1'
);
PREPARE apply_change FROM @statement;
EXECUTE apply_change;
DEALLOCATE PREPARE apply_change;
