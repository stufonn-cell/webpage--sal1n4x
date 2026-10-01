-- ---------------------------------------------------------------------------
-- PsiClinic - language of the documents handed to patients
-- Idempotent: it can run any number of times without further effects.
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;

-- Each consent keeps the language it was created in, so the signature block
-- and the printed copy match the text the patient read and signed.
SET @statement := IF(
    (SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = 'consents' AND column_name = 'language') = 0,
    'ALTER TABLE consents ADD COLUMN language CHAR(2) NOT NULL DEFAULT ''en'' AFTER template_code',
    'SELECT 1'
);
PREPARE apply_change FROM @statement;
EXECUTE apply_change;
DEALLOCATE PREPARE apply_change;

-- Consents created while the app was in Spanish carry Spanish titles.
UPDATE consents
SET language = 'es'
WHERE language = 'en' AND (title LIKE 'Consentimiento%' OR title LIKE 'Autorizaci%');
