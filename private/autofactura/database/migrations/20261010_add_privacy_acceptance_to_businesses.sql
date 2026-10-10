-- Evidencia de aceptación del Aviso de Privacidad durante el registro público.
ALTER TABLE `businesses`
    ADD COLUMN IF NOT EXISTS `privacy_accepted_at` DATETIME NULL AFTER `email_verified_at`,
    ADD COLUMN IF NOT EXISTS `privacy_version` VARCHAR(20) NULL AFTER `privacy_accepted_at`,
    ADD COLUMN IF NOT EXISTS `marketing_consent` TINYINT(1) NOT NULL DEFAULT 0 AFTER `privacy_version`,
    ADD COLUMN IF NOT EXISTS `marketing_consent_at` DATETIME NULL AFTER `marketing_consent`;
