-- =============================================
-- AutoFactura - Schema de Base de Datos
-- PHP 8.2 + MySQL/MariaDB
-- =============================================

CREATE DATABASE IF NOT EXISTS `autofactura`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `autofactura`;

-- =============================================
-- Tabla: businesses (Negocios registrados)
-- =============================================
CREATE TABLE IF NOT EXISTS `businesses` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `stamp_credits` INT UNSIGNED NOT NULL DEFAULT 0,
  `email_verification_token` VARCHAR(64) DEFAULT NULL,
  `email_verification_sent_at` DATETIME DEFAULT NULL,
  `email_verified_at` DATETIME DEFAULT NULL,
  `role` ENUM('user', 'superuser') NOT NULL DEFAULT 'user',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Tabla: business_settings (Configuración del negocio)
-- =============================================
CREATE TABLE IF NOT EXISTS `business_settings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `business_id` INT UNSIGNED NOT NULL,
  `invoicing_mode` ENUM('direct', 'ef_api') NOT NULL DEFAULT 'direct',

  -- Modo direct
  `rfc_emisor` VARCHAR(13) DEFAULT NULL,
  `nombre_emisor` VARCHAR(255) DEFAULT NULL,
  `regimen_fiscal` VARCHAR(10) DEFAULT NULL,
  `codigo_postal` VARCHAR(5) DEFAULT NULL,

  -- Modo ef_api
  `api_url` VARCHAR(500) DEFAULT NULL,
  `api_user` VARCHAR(255) DEFAULT NULL,
  `api_password` VARCHAR(255) DEFAULT NULL,
  `api_key` VARCHAR(255) DEFAULT NULL,

  -- Logo del negocio
  `commercial_name` VARCHAR(255) DEFAULT NULL,
  `logo` VARCHAR(255) DEFAULT NULL,
  `template_color` VARCHAR(7) DEFAULT '#359BE3',
  `font_color` VARCHAR(7) DEFAULT '#111111',
  `link_expiration_days` TINYINT UNSIGNED NOT NULL DEFAULT 3,
  `csd_key_path` VARCHAR(255) DEFAULT NULL,
  `csd_cer_path` VARCHAR(255) DEFAULT NULL,
  `csd_password` TEXT DEFAULT NULL,
  `csd_uploaded_at` DATETIME DEFAULT NULL,
  `csd_rfc` VARCHAR(13) DEFAULT NULL,
  `csd_valid_from` DATETIME DEFAULT NULL,
  `csd_valid_to` DATETIME DEFAULT NULL,

  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT `fk_settings_business` FOREIGN KEY (`business_id`)
    REFERENCES `businesses`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Tabla: invoice_concepts (Conceptos de facturación)
-- =============================================
CREATE TABLE IF NOT EXISTS `invoice_concepts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `business_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `sat_product_key` VARCHAR(10) NOT NULL,
  `sat_unit_key` VARCHAR(10) NOT NULL,
  `unit_name` VARCHAR(50) NOT NULL,
  `tax_object` VARCHAR(5) NOT NULL DEFAULT '02',
  `tax_type` VARCHAR(10) NOT NULL DEFAULT 'IVA',
  `tax_rate` DECIMAL(5,4) NOT NULL DEFAULT 0.1600,
  `default_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT `fk_concepts_business` FOREIGN KEY (`business_id`)
    REFERENCES `businesses`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Tabla: autofactura_requests (Solicitudes de autofactura)
-- =============================================
CREATE TABLE IF NOT EXISTS `autofactura_requests` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `business_id` INT UNSIGNED NOT NULL,
  `concept_id` INT UNSIGNED NOT NULL,
  `token` VARCHAR(64) NOT NULL UNIQUE,
  `phone` VARCHAR(20) DEFAULT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `whatsapp_sent` TINYINT(1) NOT NULL DEFAULT 0,
  `amount` DECIMAL(12,2) NOT NULL,
  `invoice_uuid` VARCHAR(50) DEFAULT NULL,
  `invoice_xml_url` VARCHAR(500) DEFAULT NULL,
  `invoice_pdf_url` VARCHAR(500) DEFAULT NULL,
  `invoiced_at` DATETIME DEFAULT NULL,
  `status` ENUM('pendiente','enviada','capturada','facturada','expirada','cancelada','error')
    NOT NULL DEFAULT 'pendiente',
  `expires_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT `fk_requests_business` FOREIGN KEY (`business_id`)
    REFERENCES `businesses`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_requests_concept` FOREIGN KEY (`concept_id`)
    REFERENCES `invoice_concepts`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,

  INDEX `idx_token` (`token`),
  INDEX `idx_status` (`status`),
  UNIQUE KEY `uk_invoice_uuid` (`invoice_uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Tabla: autofactura_customers (Datos fiscales del cliente)
-- =============================================
CREATE TABLE IF NOT EXISTS `autofactura_customers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `request_id` INT UNSIGNED NOT NULL,
  `rfc` VARCHAR(13) NOT NULL,
  `razon_social` VARCHAR(255) NOT NULL,
  `codigo_postal` VARCHAR(5) NOT NULL,
  `regimen_fiscal` VARCHAR(10) NOT NULL,
  `uso_cfdi` VARCHAR(10) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `efos_status` VARCHAR(50) DEFAULT NULL,
  `efos_checked_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT `fk_customers_request` FOREIGN KEY (`request_id`)
    REFERENCES `autofactura_requests`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Tabla: autofactura_logs (Bitácora de eventos)
-- =============================================
CREATE TABLE IF NOT EXISTS `autofactura_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `request_id` INT UNSIGNED DEFAULT NULL,
  `business_id` INT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `details` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(500) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT `fk_logs_request` FOREIGN KEY (`request_id`)
    REFERENCES `autofactura_requests`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_logs_business` FOREIGN KEY (`business_id`)
    REFERENCES `businesses`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,

  INDEX `idx_action` (`action`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Tabla: stamp_purchases (Historial de compras de timbres)
-- =============================================
CREATE TABLE IF NOT EXISTS `stamp_purchases` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `business_id` INT UNSIGNED NOT NULL,
  `package_name` VARCHAR(150) NOT NULL,
  `credits` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `invoice_request_id` INT UNSIGNED DEFAULT NULL,
  `invoice_link_sent_at` DATETIME DEFAULT NULL,
  `payment_request_id` VARCHAR(80) DEFAULT NULL,
  `payment_request_url` VARCHAR(500) DEFAULT NULL,
  `clip_status` VARCHAR(80) DEFAULT NULL,
  `payment_method` VARCHAR(50) DEFAULT NULL,
  `payment_reference` VARCHAR(150) DEFAULT NULL,
  `status` ENUM('pending','paid','cancelled','failed') NOT NULL DEFAULT 'pending',
  `notes` TEXT DEFAULT NULL,
  `paid_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT `fk_stamp_purchases_business` FOREIGN KEY (`business_id`)
    REFERENCES `businesses`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,

  INDEX `idx_stamp_purchases_business` (`business_id`),
  INDEX `idx_stamp_purchases_status` (`status`),
  INDEX `idx_stamp_purchases_paid_at` (`paid_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Consulta de actualización para BD existentes
-- =============================================
-- Si tu BD ya fue creada previamente, ejecuta:
-- ALTER TABLE `business_settings`
--   ADD COLUMN `csd_key_path` VARCHAR(255) DEFAULT NULL AFTER `link_expiration_days`,
--   ADD COLUMN `csd_cer_path` VARCHAR(255) DEFAULT NULL AFTER `csd_key_path`,
--   ADD COLUMN `csd_password` TEXT DEFAULT NULL AFTER `csd_cer_path`,
--   ADD COLUMN `csd_uploaded_at` DATETIME DEFAULT NULL AFTER `csd_password`,
--   ADD COLUMN `csd_rfc` VARCHAR(13) DEFAULT NULL AFTER `csd_uploaded_at`,
--   ADD COLUMN `csd_valid_from` DATETIME DEFAULT NULL AFTER `csd_rfc`,
--   ADD COLUMN `csd_valid_to` DATETIME DEFAULT NULL AFTER `csd_valid_from`;
-- ADD COLUMN `link_expiration_days` TINYINT UNSIGNED NOT NULL DEFAULT 3 AFTER `logo`;
-- ALTER TABLE `business_settings`
-- ADD COLUMN `commercial_name` VARCHAR(255) DEFAULT NULL AFTER `api_key`;
-- ALTER TABLE `business_settings`
--   ADD COLUMN `template_color` VARCHAR(7) DEFAULT '#359BE3' AFTER `logo`,
--   ADD COLUMN `font_color` VARCHAR(7) DEFAULT '#111111' AFTER `template_color`;
-- ALTER TABLE `invoice_concepts`
-- ADD COLUMN `default_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `tax_rate`;
-- ALTER TABLE `businesses`
-- ADD COLUMN `stamp_credits` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `phone`;
-- ALTER TABLE `businesses`
--   ADD COLUMN `email_verification_token` VARCHAR(64) DEFAULT NULL AFTER `stamp_credits`,
--   ADD COLUMN `email_verification_sent_at` DATETIME DEFAULT NULL AFTER `email_verification_token`,
--   ADD COLUMN `email_verified_at` DATETIME DEFAULT NULL AFTER `email_verification_sent_at`;
-- ALTER TABLE `autofactura_requests`
-- ADD COLUMN `whatsapp_sent` TINYINT(1) NOT NULL DEFAULT 0 AFTER `email`;
-- ALTER TABLE `autofactura_requests`
-- ADD COLUMN `invoice_uuid` VARCHAR(50) DEFAULT NULL AFTER `amount`,
-- ADD COLUMN `invoice_xml_url` VARCHAR(500) DEFAULT NULL AFTER `invoice_uuid`,
-- ADD COLUMN `invoice_pdf_url` VARCHAR(500) DEFAULT NULL AFTER `invoice_xml_url`,
-- ADD COLUMN `invoiced_at` DATETIME DEFAULT NULL AFTER `invoice_pdf_url`;
-- CREATE TABLE `stamp_purchases` (
--   `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
--   `business_id` INT UNSIGNED NOT NULL,
--   `package_name` VARCHAR(150) NOT NULL,
--   `credits` INT UNSIGNED NOT NULL,
--   `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
--   `invoice_request_id` INT UNSIGNED DEFAULT NULL,
--   `invoice_link_sent_at` DATETIME DEFAULT NULL,
--   `payment_request_id` VARCHAR(80) DEFAULT NULL,
--   `payment_request_url` VARCHAR(500) DEFAULT NULL,
--   `clip_status` VARCHAR(80) DEFAULT NULL,
--   `payment_method` VARCHAR(50) DEFAULT NULL,
--   `payment_reference` VARCHAR(150) DEFAULT NULL,
--   `status` ENUM('pending','paid','cancelled','failed') NOT NULL DEFAULT 'pending',
--   `notes` TEXT DEFAULT NULL,
--   `paid_at` DATETIME DEFAULT NULL,
--   `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
--   `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
--   CONSTRAINT `fk_stamp_purchases_business` FOREIGN KEY (`business_id`)
--     REFERENCES `businesses`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
--   INDEX `idx_stamp_purchases_business` (`business_id`),
--   INDEX `idx_stamp_purchases_status` (`status`),
--   INDEX `idx_stamp_purchases_paid_at` (`paid_at`)
-- );
