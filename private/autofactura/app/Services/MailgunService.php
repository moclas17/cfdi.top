<?php
/**
 * Servicio: MailgunService
 * Envío simple de correos sin Composer.
 */

class MailgunService
{
    public static function sendEmailVerification(
        string $toEmail,
        string $businessName,
        string $verificationUrl
    ): array {
        $config = self::mailConfig($toEmail);
        if (!$config['success']) {
            return $config;
        }

        $subject = 'Verifica tu cuenta de AutoFactura';
        $text = self::buildEmailVerificationTextTemplate($businessName, $verificationUrl);
        $html = self::buildEmailVerificationHtmlTemplate(
            $config['logo_url'],
            $businessName,
            $verificationUrl
        );

        return self::sendMessage($config['url'], $config['api_key'], [
            'from' => $config['from'],
            'to' => $toEmail,
            'subject' => $subject,
            'text' => $text,
            'html' => $html,
        ]);
    }

    public static function sendInvoiceDocuments(
        string $toEmail,
        string $businessName,
        string $conceptName,
        float $amount,
        string $uuid,
        ?string $xmlUrl = null,
        ?string $pdfUrl = null,
        ?string $xmlPath = null,
        ?string $pdfPath = null,
        ?string $businessLogoUrl = null
    ): array {
        $config = self::mailConfig($toEmail);
        if (!$config['success']) {
            return $config;
        }

        $subject = 'Tu factura ya esta lista';
        $formattedAmount = '$' . number_format($amount, 2, '.', ',') . ' MXN';
        $text = self::buildInvoiceDocumentsTextTemplate($businessName, $conceptName, $formattedAmount, $uuid, $xmlUrl, $pdfUrl);
        $html = self::buildInvoiceDocumentsHtmlTemplate(
            $config['logo_url'],
            $businessName,
            $conceptName,
            $formattedAmount,
            $uuid,
            $xmlUrl,
            $pdfUrl,
            $businessLogoUrl
        );

        $postFields = [
            'from' => $config['from'],
            'to' => $toEmail,
            'subject' => $subject,
            'text' => $text,
            'html' => $html,
        ];

        $attachments = [];
        if (!empty($xmlPath) && is_file($xmlPath)) {
            $attachments[] = curl_file_create($xmlPath, 'application/xml', basename($xmlPath));
        }
        if (!empty($pdfPath) && is_file($pdfPath)) {
            $attachments[] = curl_file_create($pdfPath, 'application/pdf', basename($pdfPath));
        }
        foreach ($attachments as $index => $attachment) {
            $postFields['attachment[' . $index . ']'] = $attachment;
        }

        return self::sendMessage($config['url'], $config['api_key'], $postFields);
    }

    public static function sendInvoiceLink(
        string $toEmail,
        string $businessName,
        string $link,
        float $amount,
        string $conceptName,
        ?string $expiresAt = null,
        ?string $businessLogoUrl = null
    ): array {
        $config = self::mailConfig($toEmail);
        if (!$config['success']) {
            return $config;
        }

        $subject = 'Tu enlace de factura está listo';
        $expiresText = $expiresAt ? (new DateTime($expiresAt))->format('d/m/Y H:i') : 'No definida';
        $formattedAmount = '$' . number_format($amount, 2, '.', ',') . ' MXN';
        $text = self::buildTextTemplate($businessName, $conceptName, $formattedAmount, $expiresText, $link);
        $html = self::buildHtmlTemplate($config['logo_url'], $businessName, $conceptName, $formattedAmount, $expiresText, $link, $businessLogoUrl);

        return self::sendMessage($config['url'], $config['api_key'], [
            'from' => $config['from'],
            'to' => $toEmail,
            'subject' => $subject,
            'text' => $text,
            'html' => $html,
        ]);
    }

    public static function sendLowStampAlert(
        string $toEmail,
        string $businessName,
        int $currentCredits,
        int $threshold = 1000
    ): array {
        $config = self::mailConfig($toEmail);
        if (!$config['success']) {
            return $config;
        }

        $subject = 'Tu saldo de timbres está por debajo del mínimo recomendado';
        $text = "Hola {$businessName},\n\n"
            . "Tu inventario de timbres bajó a {$currentCredits} timbres.\n"
            . "El umbral recomendado es {$threshold} timbres.\n\n"
            . "Te sugerimos recargar saldo cuanto antes para no detener la venta de paquetes a tus negocios clientes.\n\n"
            . "Este recordatorio fue generado automáticamente por AutoFactura.";

        $eBusiness = htmlspecialchars($businessName, ENT_QUOTES, 'UTF-8');
        $eCredits = htmlspecialchars((string) $currentCredits, ENT_QUOTES, 'UTF-8');
        $eThreshold = htmlspecialchars((string) $threshold, ENT_QUOTES, 'UTF-8');
        $logoBlock = '';
        if ($config['logo_url'] !== '') {
            $eLogo = htmlspecialchars($config['logo_url'], ENT_QUOTES, 'UTF-8');
            $logoBlock = '<div style="text-align:center;margin-bottom:20px;">'
                . '<img src="' . $eLogo . '" alt="AutoFactura" style="max-width:160px;height:auto;">'
                . '</div>';
        }

        $html = '<!doctype html><html lang="es"><body style="margin:0;padding:24px;background:#f6f8fc;font-family:Arial,sans-serif;color:#1f2937;">'
            . '<div style="max-width:620px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;padding:28px;">'
            . $logoBlock
            . '<h2 style="margin:0 0 14px 0;font-size:24px;color:#111827;">Saldo bajo de timbres</h2>'
            . '<p style="margin:0 0 12px 0;font-size:15px;line-height:1.6;">Hola ' . $eBusiness . ', tu inventario de timbres bajó a <strong>' . $eCredits . '</strong>.</p>'
            . '<p style="margin:0 0 12px 0;font-size:15px;line-height:1.6;">El umbral recomendado es de <strong>' . $eThreshold . ' timbres</strong>.</p>'
            . '<p style="margin:0;font-size:15px;line-height:1.6;">Te sugerimos hacer una recarga pronto para no frenar la venta de paquetes a tus negocios clientes.</p>'
            . '</div></body></html>';

        return self::sendMessage($config['url'], $config['api_key'], [
            'from' => $config['from'],
            'to' => $toEmail,
            'subject' => $subject,
            'text' => $text,
            'html' => $html,
        ]);
    }

    private static function mailConfig(string $toEmail): array
    {
        $enabled = filter_var((string) env('MAILGUN_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN);
        $apiKey = trim((string) env('MAILGUN_API_KEY', ''));
        $domain = trim((string) env('MAILGUN_DOMAIN', ''));
        $baseUrl = self::normalizeBaseUrl((string) env('MAILGUN_BASE_URL', 'https://api.mailgun.net/v3'));

        if (!$enabled) {
            return ['success' => false, 'message' => 'Mailgun desactivado (MAILGUN_ENABLED=false).'];
        }

        if ($apiKey === '' || $domain === '') {
            return ['success' => false, 'message' => 'Faltan credenciales Mailgun en .env (MAILGUN_API_KEY/MAILGUN_DOMAIN).'];
        }

        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Correo de destino inválido.'];
        }

        if (!function_exists('curl_init')) {
            return ['success' => false, 'message' => 'cURL no está disponible en el servidor.'];
        }

        $fromEmail = trim((string) env('MAILGUN_FROM_EMAIL', 'noreply@' . $domain));
        $fromName = trim((string) env('MAILGUN_FROM_NAME', 'AutoFactura'));

        return [
            'success' => true,
            'api_key' => $apiKey,
            'url' => $baseUrl . '/' . rawurlencode($domain) . '/messages',
            'from' => sprintf('%s <%s>', $fromName, $fromEmail),
            'logo_url' => self::resolveLogoUrl(),
        ];
    }

    private static function sendMessage(string $url, string $apiKey, array $postFields): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERPWD => 'api:' . $apiKey,
            CURLOPT_POSTFIELDS => $postFields,
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return ['success' => false, 'message' => 'Error al conectar con Mailgun: ' . ($curlError ?: 'sin respuesta')];
        }

        $data = json_decode((string) $response, true);
        $isSuccess = $httpCode >= 200 && $httpCode < 300;

        if (!$isSuccess) {
            $message = is_array($data) ? ($data['message'] ?? 'Error Mailgun') : 'Error Mailgun';
            return ['success' => false, 'message' => 'Mailgun respondió HTTP ' . $httpCode . ': ' . $message];
        }

        return [
            'success' => true,
            'message' => is_array($data) ? ($data['message'] ?? 'Correo enviado correctamente.') : 'Correo enviado correctamente.',
        ];
    }

    private static function resolveLogoUrl(): string
    {
        $custom = trim((string) env('MAIL_TEMPLATE_LOGO_URL', ''));
        if ($custom !== '') {
            return $custom;
        }

        $appUrl = rtrim((string) env('APP_URL', ''), '/');
        if ($appUrl === '') {
            return '';
        }

        return $appUrl . '/img/autofactura.png';
    }

    private static function buildTextTemplate(
        string $businessName,
        string $conceptName,
        string $formattedAmount,
        string $expiresText,
        string $link
    ): string {
        return "Hola,\n\n"
            . "Tu enlace para capturar datos de factura ya está disponible.\n\n"
            . "Negocio: {$businessName}\n"
            . "Concepto: {$conceptName}\n"
            . "Importe: {$formattedAmount}\n"
            . "Vence: {$expiresText}\n"
            . "Enlace: {$link}\n\n"
            . "Este correo fue generado automáticamente por AutoFactura.\n"
            . "Si no reconoces este mensaje, ignóralo.";
    }

    private static function buildEmailVerificationTextTemplate(
        string $businessName,
        string $verificationUrl
    ): string {
        return "Hola {$businessName},\n\n"
            . "Gracias por crear tu cuenta en AutoFactura.\n"
            . "Para activar tu acceso y confirmar que eres una persona real, verifica tu correo aquí:\n\n"
            . $verificationUrl . "\n\n"
            . "Hasta que completes esta verificación no podrás iniciar sesión.\n\n"
            . "Si no reconoces este registro, puedes ignorar este mensaje.";
    }

    private static function buildHtmlTemplate(
        string $logoUrl,
        string $businessName,
        string $conceptName,
        string $formattedAmount,
        string $expiresText,
        string $link,
        ?string $businessLogoUrl = null
    ): string {
        $eBusiness = htmlspecialchars($businessName, ENT_QUOTES, 'UTF-8');
        $eConcept = htmlspecialchars($conceptName, ENT_QUOTES, 'UTF-8');
        $eAmount = htmlspecialchars($formattedAmount, ENT_QUOTES, 'UTF-8');
        $eExpires = htmlspecialchars($expiresText, ENT_QUOTES, 'UTF-8');
        $eLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
        $eYear = date('Y');

        $businessLogoBlock = '';
        if (!empty($businessLogoUrl)) {
            $eBusinessLogo = htmlspecialchars($businessLogoUrl, ENT_QUOTES, 'UTF-8');
            $businessLogoBlock = '<tr>'
                . '<td style="padding:22px 24px 0 24px; text-align:center;">'
                . '<img src="' . $eBusinessLogo . '" alt="Logo del negocio" style="max-width:180px; max-height:72px; width:auto; height:auto; border:1px solid #e8edf4; border-radius:8px; background:#ffffff; padding:8px;">'
                . '</td>'
                . '</tr>';
        }

        $autofacturaFooterLogo = '';
        if ($logoUrl !== '') {
            $eLogo = htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8');
            $autofacturaFooterLogo = '<div style="margin-top:10px;">'
                . '<img src="' . $eLogo . '" alt="AutoFactura" style="max-width:120px; width:100%; height:auto; opacity:0.85;">'
                . '</div>';
        }

        return '<!doctype html>'
            . '<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>'
            . '<body style="margin:0; padding:0; background:#f2f5fb; font-family:Arial,Helvetica,sans-serif; color:#243043;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f2f5fb; padding:24px 0;">'
            . '<tr><td align="center">'
            . '<table role="presentation" width="640" cellspacing="0" cellpadding="0" style="max-width:640px; width:100%; background:#ffffff; border:1px solid #e5ebf2; border-radius:12px;">'
            . $businessLogoBlock
            . '<tr><td style="padding:8px 24px 0 24px; text-align:center;">'
            . '<h1 style="margin:0; font-size:24px; line-height:1.2; color:#1f2a3a;">Tu enlace de factura está listo</h1>'
            . '</td></tr>'
            . '<tr><td style="padding:12px 24px 0 24px; text-align:center; font-size:15px; color:#5b6677;">'
            . 'Usa este enlace para capturar tus datos fiscales y generar tu factura.'
            . '</td></tr>'
            . '<tr><td style="padding:20px 24px 0 24px;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #e7edf5; border-radius:10px; background:#f9fbff;">'
            . '<tr><td style="padding:16px 18px;">'
            . '<div style="font-size:13px; color:#6a7688; margin-bottom:8px;">Resumen de la factura</div>'
            . '<div style="font-size:14px; margin-bottom:6px;"><strong>Negocio:</strong> ' . $eBusiness . '</div>'
            . '<div style="font-size:14px; margin-bottom:6px;"><strong>Concepto:</strong> ' . $eConcept . '</div>'
            . '<div style="font-size:14px; margin-bottom:6px;"><strong>Importe:</strong> ' . $eAmount . '</div>'
            . '<div style="font-size:14px;"><strong>Vigencia del enlace:</strong> ' . $eExpires . '</div>'
            . '</td></tr></table>'
            . '</td></tr>'
            . '<tr><td style="padding:22px 24px 8px 24px; text-align:center;">'
            . '<a href="' . $eLink . '" style="display:inline-block; background:#3c92f6; color:#ffffff; text-decoration:none; font-weight:700; font-size:15px; padding:12px 20px; border-radius:8px;">Abrir enlace de factura</a>'
            . '</td></tr>'
            . '<tr><td style="padding:8px 24px 0 24px; text-align:center;">'
            . '<div style="font-size:12px; color:#7f8a9a; word-break:break-all;">' . $eLink . '</div>'
            . '</td></tr>'
            . '<tr><td style="padding:22px 24px 22px 24px; text-align:center; font-size:12px; color:#8b95a3;">'
            . 'Este correo fue generado automáticamente por AutoFactura.<br>'
            . 'Si no reconoces este mensaje, puedes ignorarlo.<br><br>'
            . '&copy; ' . $eYear . ' AutoFactura'
            . $autofacturaFooterLogo
            . '</td></tr>'
            . '</table>'
            . '</td></tr>'
            . '</table>'
            . '</body></html>';
    }

    private static function buildEmailVerificationHtmlTemplate(
        string $logoUrl,
        string $businessName,
        string $verificationUrl
    ): string {
        $eBusiness = htmlspecialchars($businessName, ENT_QUOTES, 'UTF-8');
        $eVerificationUrl = htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8');
        $eYear = date('Y');

        $autofacturaFooterLogo = '';
        if ($logoUrl !== '') {
            $eLogo = htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8');
            $autofacturaFooterLogo = '<div style="margin-top:10px;">'
                . '<img src="' . $eLogo . '" alt="AutoFactura" style="max-width:120px; width:100%; height:auto; opacity:0.85;">'
                . '</div>';
        }

        return '<!doctype html>'
            . '<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>'
            . '<body style="margin:0; padding:0; background:#f2f5fb; font-family:Arial,Helvetica,sans-serif; color:#243043;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f2f5fb; padding:24px 0;">'
            . '<tr><td align="center">'
            . '<table role="presentation" width="640" cellspacing="0" cellpadding="0" style="max-width:640px; width:100%; background:#ffffff; border:1px solid #e5ebf2; border-radius:12px;">'
            . '<tr><td style="padding:28px 24px 0 24px; text-align:center;">'
            . '<h1 style="margin:0; font-size:24px; line-height:1.2; color:#1f2a3a;">Verifica tu cuenta</h1>'
            . '</td></tr>'
            . '<tr><td style="padding:14px 24px 0 24px; text-align:center; font-size:15px; color:#5b6677;">'
            . 'Hola <strong>' . $eBusiness . '</strong>, confirma tu correo para activar tu acceso a AutoFactura.'
            . '</td></tr>'
            . '<tr><td style="padding:22px 24px 0 24px;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #e7edf5; border-radius:10px; background:#f9fbff;">'
            . '<tr><td style="padding:16px 18px; font-size:14px; color:#4b5565;">'
            . 'Esta verificación ayuda a bloquear registros falsos, bots y cuentas de spam. '
            . 'Mientras no confirmes tu correo, no podrás ingresar al sistema.'
            . '</td></tr></table>'
            . '</td></tr>'
            . '<tr><td style="padding:22px 24px 8px 24px; text-align:center;">'
            . '<a href="' . $eVerificationUrl . '" style="display:inline-block; background:#3c92f6; color:#ffffff; text-decoration:none; font-weight:700; font-size:15px; padding:12px 20px; border-radius:8px;">Verificar mi cuenta</a>'
            . '</td></tr>'
            . '<tr><td style="padding:8px 24px 0 24px; text-align:center;">'
            . '<div style="font-size:12px; color:#7f8a9a; word-break:break-all;">' . $eVerificationUrl . '</div>'
            . '</td></tr>'
            . '<tr><td style="padding:22px 24px 22px 24px; text-align:center; font-size:12px; color:#8b95a3;">'
            . 'Este correo fue generado automáticamente por AutoFactura.<br>'
            . 'Si no reconoces este registro, puedes ignorarlo.<br><br>'
            . '&copy; ' . $eYear . ' AutoFactura'
            . $autofacturaFooterLogo
            . '</td></tr>'
            . '</table>'
            . '</td></tr></table>'
            . '</body></html>';
    }

    private static function buildInvoiceDocumentsTextTemplate(
        string $businessName,
        string $conceptName,
        string $formattedAmount,
        string $uuid,
        ?string $xmlUrl,
        ?string $pdfUrl
    ): string {
        $lines = [
            'Hola,',
            '',
            'Tu factura ya fue generada correctamente.',
            '',
            'Negocio: ' . $businessName,
            'Concepto: ' . $conceptName,
            'Importe: ' . $formattedAmount,
            'UUID: ' . $uuid,
        ];

        if (!empty($pdfUrl)) {
            $lines[] = 'PDF: ' . $pdfUrl;
        }
        if (!empty($xmlUrl)) {
            $lines[] = 'XML: ' . $xmlUrl;
        }

        $lines[] = '';
        $lines[] = 'Adjuntamos tu XML y PDF cuando estuvieron disponibles.';
        $lines[] = 'Este correo fue generado automáticamente por AutoFactura.';

        return implode("\n", $lines);
    }

    private static function buildInvoiceDocumentsHtmlTemplate(
        string $logoUrl,
        string $businessName,
        string $conceptName,
        string $formattedAmount,
        string $uuid,
        ?string $xmlUrl,
        ?string $pdfUrl,
        ?string $businessLogoUrl = null
    ): string {
        $eBusiness = htmlspecialchars($businessName, ENT_QUOTES, 'UTF-8');
        $eConcept = htmlspecialchars($conceptName, ENT_QUOTES, 'UTF-8');
        $eAmount = htmlspecialchars($formattedAmount, ENT_QUOTES, 'UTF-8');
        $eUuid = htmlspecialchars($uuid, ENT_QUOTES, 'UTF-8');
        $eYear = date('Y');

        $businessLogoBlock = '';
        if (!empty($businessLogoUrl)) {
            $eBusinessLogo = htmlspecialchars($businessLogoUrl, ENT_QUOTES, 'UTF-8');
            $businessLogoBlock = '<tr><td style="padding:22px 24px 0 24px; text-align:center;">'
                . '<img src="' . $eBusinessLogo . '" alt="Logo del negocio" style="max-width:180px; max-height:72px; width:auto; height:auto; border:1px solid #e8edf4; border-radius:8px; background:#ffffff; padding:8px;">'
                . '</td></tr>';
        }

        $buttons = '';
        if (!empty($pdfUrl)) {
            $ePdfUrl = htmlspecialchars($pdfUrl, ENT_QUOTES, 'UTF-8');
            $buttons .= '<a href="' . $ePdfUrl . '" style="display:inline-block; margin:0 6px 8px 6px; background:#3c92f6; color:#ffffff; text-decoration:none; font-weight:700; font-size:15px; padding:12px 20px; border-radius:8px;">Descargar PDF</a>';
        }
        if (!empty($xmlUrl)) {
            $eXmlUrl = htmlspecialchars($xmlUrl, ENT_QUOTES, 'UTF-8');
            $buttons .= '<a href="' . $eXmlUrl . '" style="display:inline-block; margin:0 6px 8px 6px; background:#ffffff; color:#3c92f6; text-decoration:none; font-weight:700; font-size:15px; padding:12px 20px; border-radius:8px; border:1px solid #3c92f6;">Descargar XML</a>';
        }

        $autofacturaFooterLogo = '';
        if ($logoUrl !== '') {
            $eLogo = htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8');
            $autofacturaFooterLogo = '<div style="margin-top:10px;"><img src="' . $eLogo . '" alt="AutoFactura" style="max-width:120px; width:100%; height:auto; opacity:0.85;"></div>';
        }

        return '<!doctype html>'
            . '<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>'
            . '<body style="margin:0; padding:0; background:#f2f5fb; font-family:Arial,Helvetica,sans-serif; color:#243043;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f2f5fb; padding:24px 0;"><tr><td align="center">'
            . '<table role="presentation" width="640" cellspacing="0" cellpadding="0" style="max-width:640px; width:100%; background:#ffffff; border:1px solid #e5ebf2; border-radius:12px;">'
            . $businessLogoBlock
            . '<tr><td style="padding:8px 24px 0 24px; text-align:center;"><h1 style="margin:0; font-size:24px; line-height:1.2; color:#1f2a3a;">Tu factura ya esta lista</h1></td></tr>'
            . '<tr><td style="padding:12px 24px 0 24px; text-align:center; font-size:15px; color:#5b6677;">Adjuntamos el XML y PDF de tu factura, y tambien te dejamos los enlaces de descarga.</td></tr>'
            . '<tr><td style="padding:20px 24px 0 24px;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #e7edf5; border-radius:10px; background:#f9fbff;"><tr><td style="padding:16px 18px;">'
            . '<div style="font-size:13px; color:#6a7688; margin-bottom:8px;">Resumen de la factura</div>'
            . '<div style="font-size:14px; margin-bottom:6px;"><strong>Negocio:</strong> ' . $eBusiness . '</div>'
            . '<div style="font-size:14px; margin-bottom:6px;"><strong>Concepto:</strong> ' . $eConcept . '</div>'
            . '<div style="font-size:14px; margin-bottom:6px;"><strong>Importe:</strong> ' . $eAmount . '</div>'
            . '<div style="font-size:14px;"><strong>UUID:</strong> ' . $eUuid . '</div>'
            . '</td></tr></table></td></tr>'
            . '<tr><td style="padding:22px 24px 8px 24px; text-align:center;">' . $buttons . '</td></tr>'
            . '<tr><td style="padding:22px 24px 22px 24px; text-align:center; font-size:12px; color:#8b95a3;">'
            . 'Este correo fue generado automáticamente por AutoFactura.<br>'
            . 'Si no reconoces este mensaje, puedes ignorarlo.<br><br>'
            . '&copy; ' . $eYear . ' AutoFactura'
            . $autofacturaFooterLogo
            . '</td></tr></table></td></tr></table></body></html>';
    }

    private static function normalizeBaseUrl(string $url): string
    {
        $base = rtrim(trim($url), '/');
        if ($base === '') {
            return 'https://api.mailgun.net/v3';
        }

        if (!preg_match('#/v3$#i', $base)) {
            $base .= '/v3';
        }

        return $base;
    }
}
