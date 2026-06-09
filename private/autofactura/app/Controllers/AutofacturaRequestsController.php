<?php
/**
 * Controller: AutofacturaRequestsController
 */

class AutofacturaRequestsController
{
    public function __construct()
    {
        AuthMiddleware::check();
    }

    /**
     * Listar solicitudes
     */
    public function index(): void
    {
        $businessId = auth_business_id();
        $requests = AutofacturaRequest::getByBusiness($businessId);

        view('dashboard.autofactura-requests', [
            'requests' => $requests,
        ]);
    }

    /**
     * Crear solicitud
     */
    public function store(): void
    {
        AuthMiddleware::verifyCsrf();

        $businessId = auth_business_id();
        $data = [
            'business_id' => $businessId,
            'concept_id'  => (int) ($_POST['concept_id'] ?? 0),
            'custom_concept_text' => null,
            'phone'       => trim($_POST['phone'] ?? '') ?: null,
            'email'       => trim($_POST['email'] ?? '') ?: null,
            'amount'      => (float) ($_POST['amount'] ?? 0),
        ];

        // Validar
        if ($data['concept_id'] <= 0) {
            flash('error', 'Debes seleccionar un concepto.');
            Router::redirect('/autofactura-requests');
        }

        // Verificar concepto
        $concept = InvoiceConcept::find($data['concept_id']);
        if (!$concept || $concept['business_id'] != $businessId) {
            flash('error', 'Concepto no válido.');
            Router::redirect('/autofactura-requests');
        }

        // Si no envían importe, usar el importe sugerido del concepto
        if ($data['amount'] <= 0) {
            $data['amount'] = (float) ($concept['default_amount'] ?? 0);
        }

        if ($data['amount'] <= 0) {
            flash('error', 'El importe debe ser mayor a 0.');
            Router::redirect('/autofactura-requests');
        }

        // Regla: solo un canal de contacto por factura (email o teléfono).
        if (!empty($data['email']) && !empty($data['phone'])) {
            flash('error', 'Captura solo un dato de contacto: correo o teléfono, no ambos.');
            Router::redirect('/autofactura-requests');
        }

        if (AutofacturaRequest::supportsCustomConceptText() && isset($_POST['use_custom_concept_text'])) {
            $customConceptText = trim((string) ($_POST['custom_concept_text'] ?? ''));
            if ($customConceptText !== '') {
                $data['custom_concept_text'] = $customConceptText;
            }
        }

        $settings = BusinessSetting::getByBusiness($businessId);
        $expirationDays = (int) ($settings['link_expiration_days'] ?? 3);
        if ($expirationDays < 1 || $expirationDays > 30) {
            $expirationDays = 3;
        }
        $data['expires_at'] = (new DateTime())->modify("+{$expirationDays} days")->format('Y-m-d H:i:s');
        $supportsWhatsappSent = AutofacturaRequest::supportsWhatsappSentFlag();
        if ($supportsWhatsappSent) {
            $data['whatsapp_sent'] = 0;
        }

        $id = AutofacturaRequest::createWithToken($data);
        $request = AutofacturaRequest::find($id);
        $publicLink = url('f/' . $request['token']);
        $business = Business::find($businessId);
        $businessLogoUrl = !empty($settings['logo']) ? url('storage/uploads/logos/' . $settings['logo']) : null;
        $commercialName = trim((string) ($settings['commercial_name'] ?? ''));
        $businessDisplayName = $commercialName !== '' ? $commercialName : (string) ($business['name'] ?? 'AutoFactura');

        AutofacturaLog::log('request_created', $id, $businessId, "Importe: {$data['amount']}");

        $sentChannels = [];
        $failedChannels = [];

        // Enviar por correo vía Mailgun si hay email de cliente.
        if (!empty($data['email'])) {
            $mailResult = MailgunService::sendInvoiceLink(
                (string) $data['email'],
                $businessDisplayName,
                $publicLink,
                (float) $data['amount'],
                (string) ($data['custom_concept_text'] ?? $concept['name'] ?? 'Factura'),
                (string) ($data['expires_at'] ?? ''),
                $businessLogoUrl
            );

            if (!empty($mailResult['success'])) {
                AutofacturaLog::log('request_email_sent', $id, $businessId, 'Correo enviado a: ' . $data['email']);
                $sentChannels[] = 'correo';
            } else {
                AutofacturaLog::log('request_email_error', $id, $businessId, $mailResult['message'] ?? 'Error desconocido');
                $failedChannels[] = 'correo (' . ($mailResult['message'] ?? 'error desconocido') . ')';
            }
        }

        // Enviar por WhatsApp vía api.mensajes.xyz si hay teléfono.
        if (!empty($data['phone'])) {
            $alreadySent = $supportsWhatsappSent && (int) ($request['whatsapp_sent'] ?? 0) === 1;

            if ($alreadySent) {
                AutofacturaLog::log('request_whatsapp_skipped', $id, $businessId, 'WhatsApp omitido: ya enviado previamente.');
                $failedChannels[] = 'WhatsApp (ya fue enviado previamente)';
            } else {
                $whatsappResult = MensajesXyzService::sendInvoiceLinkTemplate(
                    (string) $data['phone'],
                    'Cliente',
                    $businessDisplayName,
                    (string) ($data['custom_concept_text'] ?? $concept['name'] ?? 'Factura'),
                    '$' . number_format((float) $data['amount'], 2) . ' MXN',
                    date('d/m/Y H:i', strtotime((string) $data['expires_at'])),
                    $publicLink,
                    'REQ-' . $id
                );

                if (!empty($whatsappResult['success'])) {
                    if ($supportsWhatsappSent) {
                        AutofacturaRequest::markWhatsappSent($id);
                    }
                    AutofacturaLog::log('request_whatsapp_sent', $id, $businessId, 'WhatsApp enviado a: ' . $data['phone']);
                    $sentChannels[] = 'WhatsApp';
                } else {
                    AutofacturaLog::log('request_whatsapp_error', $id, $businessId, $whatsappResult['message'] ?? 'Error desconocido');
                    $failedChannels[] = 'WhatsApp (' . ($whatsappResult['message'] ?? 'error desconocido') . ')';
                }
            }
        }

        if (!empty($sentChannels) && empty($failedChannels)) {
            flash('success', 'Factura creada y enlace enviado por ' . implode(' y ', $sentChannels) . '.');
        } elseif (!empty($sentChannels) && !empty($failedChannels)) {
            flash('info', 'Factura creada. Enviado por ' . implode(' y ', $sentChannels) . '. Falló: ' . implode(' | ', $failedChannels) . '.');
        } elseif (!empty($failedChannels)) {
            flash('info', 'Factura creada, pero falló el envío: ' . implode(' | ', $failedChannels) . '.');
        } else {
            flash('success', 'Factura creada. Comparte el enlace con tu cliente.');
        }

        // Ir directo a vista previa del link generado.
        header('Location: ' . $publicLink . '?preview=1');
        exit;
    }

    /**
     * Ver detalle de solicitud
     */
    public function show(string $id): void
    {
        $businessId = auth_business_id();
        $request = AutofacturaRequest::find((int) $id);

        if (!$request || $request['business_id'] != $businessId) {
            flash('error', 'Solicitud no encontrada.');
            Router::redirect('/autofactura-requests');
        }

        $customer = AutofacturaCustomer::getByRequest($request['id']);
        $concept = InvoiceConcept::find($request['concept_id']);
        $logs = AutofacturaLog::getByRequest($request['id']);

        view('dashboard.autofactura-request-detail', [
            'request'  => $request,
            'customer' => $customer,
            'concept'  => $concept,
            'logs'     => $logs,
        ]);
    }

    public function resendEmail(): void
    {
        AuthMiddleware::verifyCsrf();

        $businessId = auth_business_id();
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $request = AutofacturaRequest::find($requestId);

        if (!$request || (int) ($request['business_id'] ?? 0) !== $businessId) {
            flash('error', 'Solicitud no encontrada.');
            Router::redirect('/autofactura-requests');
        }

        $email = trim((string) ($request['email'] ?? ''));
        if ($email === '') {
            flash('error', 'Esta solicitud no tiene correo electrónico para reenviar.');
            Router::redirect('/autofactura-requests/' . $requestId);
        }

        $concept = InvoiceConcept::find((int) ($request['concept_id'] ?? 0));
        $settings = BusinessSetting::getByBusiness($businessId) ?? [];
        $business = Business::find($businessId);
        $businessLogoUrl = !empty($settings['logo']) ? url('storage/uploads/logos/' . $settings['logo']) : null;
        $commercialName = trim((string) ($settings['commercial_name'] ?? ''));
        $businessDisplayName = $commercialName !== '' ? $commercialName : (string) ($business['name'] ?? 'AutoFactura');
        $conceptName = (string) (($request['custom_concept_text'] ?? '') !== '' ? $request['custom_concept_text'] : ($concept['name'] ?? 'Factura'));
        $publicLink = url('f/' . $request['token']);

        $mailResult = MailgunService::sendInvoiceLink(
            $email,
            $businessDisplayName,
            $publicLink,
            (float) ($request['amount'] ?? 0),
            $conceptName,
            (string) ($request['expires_at'] ?? ''),
            $businessLogoUrl
        );

        if (!empty($mailResult['success'])) {
            AutofacturaLog::log('request_email_resent', $requestId, $businessId, 'Correo reenviado a: ' . $email);
            app_log('Solicitud #' . $requestId . ': correo reenviado a ' . $email, 'info');
            flash('success', 'Correo reenviado correctamente a ' . $email . '. Si no llega en unos minutos, revisa spam o correo no deseado.');
        } else {
            $message = (string) ($mailResult['message'] ?? 'Error desconocido al reenviar correo.');
            AutofacturaLog::log('request_email_resend_error', $requestId, $businessId, $message);
            app_log('Solicitud #' . $requestId . ': error al reenviar correo a ' . $email . '. ' . $message, 'error');
            flash('error', 'No se pudo reenviar el correo: ' . $message);
        }

        Router::redirect('/autofactura-requests/' . $requestId);
    }

    public function regeneratePdf(): void
    {
        AuthMiddleware::verifyCsrf();

        $businessId = auth_business_id();
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $request = AutofacturaRequest::find($requestId);

        if (!$request || (int) ($request['business_id'] ?? 0) !== $businessId) {
            flash('error', 'Solicitud no encontrada.');
            Router::redirect('/autofactura-requests');
        }

        if (($request['status'] ?? '') !== 'facturada' || empty($request['invoice_uuid'])) {
            flash('error', 'La solicitud todavía no tiene una factura lista para regenerar.');
            Router::redirect('/autofactura-requests/' . $requestId);
        }

        $customer = AutofacturaCustomer::getByRequest($requestId);
        $concept = InvoiceConcept::find((int) ($request['concept_id'] ?? 0));
        $settings = BusinessSetting::getRuntimeByBusiness($businessId) ?? [];
        $business = Business::find($businessId);

        if (!$customer || !$concept) {
            flash('error', 'Faltan datos de cliente o concepto para regenerar el PDF.');
            Router::redirect('/autofactura-requests/' . $requestId);
        }

        $invoiceData = [
            'request_id' => $request['id'],
            'amount' => (float) ($request['amount'] ?? 0),
            'concepto' => (string) (($request['custom_concept_text'] ?? '') !== '' ? $request['custom_concept_text'] : ($concept['name'] ?? 'Factura')),
            'business_logo_path' => !empty($settings['logo']) ? STORAGE_PATH . '/uploads/logos/' . $settings['logo'] : null,
            'template_color' => (string) ($settings['template_color'] ?? '#359BE3'),
            'font_color' => (string) ($settings['font_color'] ?? '#111111'),
            'emisor_rfc' => (string) ($settings['rfc_emisor'] ?? ''),
            'emisor_nombre' => (string) ($settings['nombre_emisor'] ?? ($business['name'] ?? 'AutoFactura')),
            'emisor_regimen_fiscal' => (string) ($settings['regimen_fiscal'] ?? '601'),
            'emisor_cp' => (string) ($settings['codigo_postal'] ?? ''),
            'receptor_rfc' => (string) ($customer['rfc'] ?? ''),
            'receptor_nombre' => (string) ($customer['razon_social'] ?? ''),
            'receptor_cp' => (string) ($customer['codigo_postal'] ?? ''),
            'receptor_regimen_fiscal' => (string) ($customer['regimen_fiscal'] ?? ''),
            'receptor_uso_cfdi' => (string) ($customer['uso_cfdi'] ?? ''),
            'receptor_email' => (string) ($customer['email'] ?? ''),
            'sat_product_key' => (string) ($concept['sat_product_key'] ?? '01010101'),
            'sat_unit_key' => (string) ($concept['sat_unit_key'] ?? 'E48'),
            'unit_name' => (string) ($concept['unit_name'] ?? 'Servicio'),
            'tax_object' => (string) ($concept['tax_object'] ?? '02'),
            'tax_rate' => (float) ($concept['tax_rate'] ?? 0.16),
            'payment_method' => 'PUE',
            'payment_form' => '01',
            'currency' => 'MXN',
            'invoice_type' => 'I',
        ];

        try {
            $result = EfectosFiscalesService::regenerateInvoicePdf(
                (string) $request['invoice_uuid'],
                $invoiceData,
                !empty($request['invoiced_at']) ? date('Y-m-d\TH:i:s', strtotime((string) $request['invoiced_at'])) : null
            );
        } catch (Throwable $e) {
            AutofacturaLog::log('invoice_pdf_regeneration_error', $requestId, $businessId, $e->getMessage());
            flash('error', 'No se pudo regenerar el PDF: ' . $e->getMessage());
            Router::redirect('/autofactura-requests/' . $requestId);
        }

        if (!empty($result['success'])) {
            if (!empty($result['pdf_url'])) {
                AutofacturaRequest::update($requestId, ['invoice_pdf_url' => (string) $result['pdf_url']]);
            }
            AutofacturaLog::log('invoice_pdf_regenerated', $requestId, $businessId, 'PDF regenerado manualmente.');
            flash('success', 'PDF regenerado correctamente.');
        } else {
            AutofacturaLog::log('invoice_pdf_regeneration_error', $requestId, $businessId, $result['message'] ?? 'Error desconocido');
            flash('error', $result['message'] ?? 'No se pudo regenerar el PDF.');
        }

        Router::redirect('/autofactura-requests/' . $requestId);
    }

    public function delete(): void
    {
        AuthMiddleware::verifyCsrf();

        $businessId = auth_business_id();
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $request = AutofacturaRequest::find($requestId);

        if (!$request || (int) ($request['business_id'] ?? 0) !== $businessId) {
            flash('error', 'Solicitud no encontrada.');
            Router::redirect('/autofactura-requests');
        }

        if (($request['status'] ?? '') === 'facturada') {
            flash('error', 'Las facturas facturadas no se pueden eliminar.');
            Router::redirect('/autofactura-requests');
        }

        try {
            AutofacturaRequest::delete($requestId);
            AutofacturaLog::log('request_deleted', $requestId, $businessId, 'Solicitud eliminada manualmente.');
            flash('success', 'Solicitud eliminada correctamente.');
        } catch (Throwable $e) {
            AutofacturaLog::log('request_delete_error', $requestId, $businessId, $e->getMessage());
            flash('error', 'No se pudo eliminar la solicitud.');
        }

        Router::redirect('/autofactura-requests');
    }
}
