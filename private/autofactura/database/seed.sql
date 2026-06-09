-- =============================================
-- AutoFactura - Datos Iniciales (Seed)
-- =============================================

USE `autofactura`;

-- =============================================
-- Negocio de demostración
-- Password: admin123 (bcrypt hash)
-- =============================================
INSERT INTO `businesses` (`id`, `name`, `email`, `password`, `phone`, `role`, `is_active`)
VALUES (
  1,
  'Negocio Demo S.A. de C.V.',
  'admin@demo.com',
  '$2y$10$mtckogotuQOUaKMWFKlLw.p9BZYLgyWQ41NBwi2aIGtn23e3pEQLy',
  '5551234567',
  'superuser',
  1
);

-- Usuario normal de demostración
-- Password: user12345 (bcrypt hash)
INSERT INTO `businesses` (`id`, `name`, `email`, `password`, `phone`, `role`, `is_active`)
VALUES (
  2,
  'Usuario Demo',
  'user@demo.com',
  '$2y$10$MVsU.GbaErv4YuUdGULlmeAiWcviFLn5ZNsFD2YmwZFEcJkHe/nwu',
  '5550000000',
  'user',
  1
);

-- =============================================
-- Configuración del negocio (modo direct)
-- =============================================
INSERT INTO `business_settings` (`business_id`, `invoicing_mode`, `rfc_emisor`, `nombre_emisor`, `regimen_fiscal`, `codigo_postal`, `commercial_name`, `link_expiration_days`)
VALUES (
  1,
  'direct',
  'XAXX010101000',
  'Negocio Demo S.A. de C.V.',
  '601',
  '06600',
  'AutoFactura Demo',
  3
);

-- =============================================
-- Conceptos de facturación de ejemplo
-- =============================================
INSERT INTO `invoice_concepts` (`business_id`, `name`, `description`, `sat_product_key`, `sat_unit_key`, `unit_name`, `tax_object`, `tax_type`, `tax_rate`, `default_amount`, `is_default`, `is_active`)
VALUES
  (1, 'Servicio General', 'Servicio profesional general', '80101500', 'E48', 'Servicio', '02', 'IVA', 0.1600, 500.00, 1, 1),
  (1, 'Venta de Producto', 'Venta de mercancía en general', '43201800', 'H87', 'Pieza', '02', 'IVA', 0.1600, 100.00, 0, 1),
  (1, 'Consultoría', 'Servicios de consultoría empresarial', '80111600', 'E48', 'Servicio', '02', 'IVA', 0.1600, 1500.00, 0, 1);

-- =============================================
-- Solicitud de autofactura de ejemplo
-- =============================================
INSERT INTO `autofactura_requests` (`business_id`, `concept_id`, `token`, `phone`, `email`, `whatsapp_sent`, `amount`, `status`, `expires_at`)
VALUES (
  1,
  1,
  'demo_token_abc123def456ghi789jkl012mno345pqr678stu901vwx234yz567',
  '5559876543',
  'cliente@ejemplo.com',
  0,
  1500.00,
  'pendiente',
  DATE_ADD(NOW(), INTERVAL 72 HOUR)
);

-- =============================================
-- Consulta de actualización para BD existentes
-- =============================================
-- Úsala después del ALTER de schema.sql para inicializar registros previos:
-- UPDATE `business_settings`
-- SET `link_expiration_days` = 3
-- WHERE `link_expiration_days` IS NULL OR `link_expiration_days` = 0;
-- UPDATE `business_settings`
-- SET `commercial_name` = NULL
-- WHERE `commercial_name` = '';
-- UPDATE `invoice_concepts`
-- SET `default_amount` = 0
-- WHERE `default_amount` IS NULL;
-- UPDATE `autofactura_requests`
-- SET `whatsapp_sent` = 0
-- WHERE `whatsapp_sent` IS NULL;
-- UPDATE `autofactura_requests`
-- SET `invoice_uuid` = NULL, `invoice_xml_url` = NULL, `invoice_pdf_url` = NULL, `invoiced_at` = NULL
-- WHERE `invoice_uuid` IS NULL;
