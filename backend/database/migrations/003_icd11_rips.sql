-- ---------------------------------------------------------------------------
-- PsiClinic - ICD-11 catalog and RIPS reports (Colombia, Resolution 2275 of 2023)
-- Idempotent: it can run any number of times without further effects.
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;

-- ICD-11 MMS, release 2025-01 (adopted in Colombia by Resolution 1442 of
-- 2024). The installer loads it from database/data. icd10_code holds the WHO
-- equivalence (11To10MapToOneCategory) used for dual coding during the
-- transition, because RIPS still requires ICD-10 codes.
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

-- Generated RIPS reports. The JSON is kept exactly as it was sent.
CREATE TABLE IF NOT EXISTS rips_reports (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    note_number        VARCHAR(20) NOT NULL,
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
    UNIQUE KEY uq_rips_note_number (note_number),
    CONSTRAINT fk_rips_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_rips_sender FOREIGN KEY (sent_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Each completed appointment is reported only once.
CREATE TABLE IF NOT EXISTS rips_report_items (
    report_id       INT UNSIGNED NOT NULL,
    appointment_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (report_id, appointment_id),
    UNIQUE KEY uq_rips_item_appointment (appointment_id),
    CONSTRAINT fk_rips_item_report FOREIGN KEY (report_id) REFERENCES rips_reports (id) ON DELETE CASCADE,
    CONSTRAINT fk_rips_item_appointment FOREIGN KEY (appointment_id) REFERENCES appointments (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Databases created by the first draft of this migration used Spanish names.
SET @statement := IF(
    (SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = 'rips_reports' AND column_name = 'num_nota') = 1,
    'ALTER TABLE rips_reports RENAME COLUMN num_nota TO note_number, RENAME INDEX uq_rips_num_nota TO uq_rips_note_number',
    'SELECT 1'
);
PREPARE apply_change FROM @statement;
EXECUTE apply_change;
DEALLOCATE PREPARE apply_change;

-- New columns. MySQL has no ADD COLUMN IF NOT EXISTS, so information_schema
-- is checked and only what is missing runs.

-- Diagnoses: ICD-11 system, ICD-10 equivalent and primary diagnosis flag.
SET @statement := IF(
    (SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = 'diagnoses' AND column_name = 'icd10_code') = 0,
    'ALTER TABLE diagnoses MODIFY `system` ENUM(''icd10'',''icd11'',''dsm5'') NOT NULL DEFAULT ''icd11'', ADD COLUMN icd10_code VARCHAR(10) DEFAULT NULL AFTER code, ADD COLUMN is_primary TINYINT(1) NOT NULL DEFAULT 0 AFTER status',
    'SELECT 1'
);
PREPARE apply_change FROM @statement;
EXECUTE apply_change;
DEALLOCATE PREPARE apply_change;

-- Patients: data RIPS requires for each user (fields U01-U11).
SET @statement := IF(
    (SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = 'patients' AND column_name = 'biological_sex') = 0,
    'ALTER TABLE patients ADD COLUMN biological_sex ENUM(''M'',''F'',''I'') DEFAULT NULL AFTER gender, ADD COLUMN rips_user_type CHAR(2) NOT NULL DEFAULT ''12'' AFTER document_id, ADD COLUMN residence_country CHAR(3) NOT NULL DEFAULT ''170'' AFTER country, ADD COLUMN residence_municipality CHAR(5) DEFAULT NULL AFTER residence_country, ADD COLUMN residence_zone CHAR(2) NOT NULL DEFAULT ''01'' AFTER residence_municipality, ADD COLUMN origin_country CHAR(3) NOT NULL DEFAULT ''170'' AFTER residence_zone',
    'SELECT 1'
);
PREPARE apply_change FROM @statement;
EXECUTE apply_change;
DEALLOCATE PREPARE apply_change;

-- Staff: identity document of the professional (RIPS fields C15-C16).
SET @statement := IF(
    (SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'document_number') = 0,
    'ALTER TABLE users ADD COLUMN document_type CHAR(2) NOT NULL DEFAULT ''CC'' AFTER full_name, ADD COLUMN document_number VARCHAR(20) DEFAULT NULL AFTER document_type',
    'SELECT 1'
);
PREPARE apply_change FROM @statement;
EXECUTE apply_change;
DEALLOCATE PREPARE apply_change;

-- The first draft also added an unused users.locale column: the application
-- is English only, so it is removed.
SET @statement := IF(
    (SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'locale') = 1,
    'ALTER TABLE users DROP COLUMN locale',
    'SELECT 1'
);
PREPARE apply_change FROM @statement;
EXECUTE apply_change;
DEALLOCATE PREPARE apply_change;

-- Stored codes that used to be Spanish words. Rows created before the
-- English-only release are mapped to the new codes; already mapped rows are
-- left alone.
UPDATE documents
SET category = CASE category
    WHEN 'informe' THEN 'report'
    WHEN 'remision' THEN 'referral'
    WHEN 'soporte' THEN 'administrative'
    WHEN 'externo' THEN 'external'
    ELSE category END
WHERE category IN ('informe', 'remision', 'soporte', 'externo');

UPDATE consents
SET template_code = CASE template_code
    WHEN 'teleconsulta' THEN 'telehealth'
    WHEN 'menores' THEN 'minors'
    WHEN 'datos' THEN 'data_processing'
    ELSE 'recording' END
WHERE template_code IN ('teleconsulta', 'menores', 'datos', 'grabacion', 'grabación');

-- Session notes written while the app was in Spanish store intervention
-- names from the Spanish catalog (with or without accents). They are mapped
-- to the English catalog so filters and reports keep matching them.
UPDATE clinical_notes SET interventions = REPLACE(REPLACE(interventions, 'Reestructuracion cognitiva', 'Cognitive restructuring'), 'Reestructuración cognitiva', 'Cognitive restructuring') WHERE interventions LIKE '%Reestructuraci%';
UPDATE clinical_notes SET interventions = REPLACE(REPLACE(interventions, 'Activacion conductual', 'Behavioral activation'), 'Activación conductual', 'Behavioral activation') WHERE interventions LIKE '%conductual%';
UPDATE clinical_notes SET interventions = REPLACE(REPLACE(interventions, 'Exposicion gradual', 'Graded exposure'), 'Exposición gradual', 'Graded exposure') WHERE interventions LIKE '%gradual%';
UPDATE clinical_notes SET interventions = REPLACE(REPLACE(interventions, 'Entrenamiento en relajacion', 'Relaxation training'), 'Entrenamiento en relajación', 'Relaxation training') WHERE interventions LIKE '%relajaci%';
UPDATE clinical_notes SET interventions = REPLACE(REPLACE(interventions, 'Regulacion emocional', 'Emotion regulation'), 'Regulación emocional', 'Emotion regulation') WHERE interventions LIKE '%emocional%';
UPDATE clinical_notes SET interventions = REPLACE(interventions, 'Habilidades sociales', 'Social skills training') WHERE interventions LIKE '%Habilidades sociales%';
UPDATE clinical_notes SET interventions = REPLACE(REPLACE(interventions, 'Psicoeducacion', 'Psychoeducation'), 'Psicoeducación', 'Psychoeducation') WHERE interventions LIKE '%Psicoeducaci%';
UPDATE clinical_notes SET interventions = REPLACE(REPLACE(interventions, 'Terapia de aceptacion y compromiso', 'Acceptance and commitment therapy'), 'Terapia de aceptación y compromiso', 'Acceptance and commitment therapy') WHERE interventions LIKE '%Terapia de aceptaci%';
UPDATE clinical_notes SET interventions = REPLACE(interventions, 'Entrevista motivacional', 'Motivational interviewing') WHERE interventions LIKE '%Entrevista motivacional%';
