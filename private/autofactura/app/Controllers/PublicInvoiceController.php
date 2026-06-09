<?php
/**
 * Controller: PublicInvoiceController
 * Maneja el flujo público de autofactura (link /f/{token})
 */

class PublicInvoiceController
{
    /**
     * Mostrar formulario público de datos fiscales
     */
    public function show(string $token): void
    {
        $request = AutofacturaRequest::findByToken($token);

        // Validar existencia
        if (!$request) {
            http_response_code(404);
            view('public.not-found');
            return;
        }

        // Validar expiración
        if (AutofacturaRequest::isExpired($request)) {
            AutofacturaRequest::updateStatus($request['id'], 'expirada');
            view('public.expired', ['request' => $request]);
            return;
        }

        // Validar estatus
        if ($request['status'] === 'facturada') {
            $customer = AutofacturaCustomer::getByRequest($request['id']);
            $request = $this->hydrateInvoiceAssets($request, $token);
            $business = Business::find($request['business_id']);
            $settings = BusinessSetting::getByBusiness($request['business_id']);
            view('public.already-invoiced', [
                'request' => $request,
                'customer' => $customer,
                'business' => $business,
                'settings' => $settings,
                'canSendWhatsapp' => $this->canSendInvoiceWhatsapp($request, $customer),
            ]);
            return;
        }

        if (!in_array($request['status'], ['pendiente', 'enviada', 'error'], true)) {
            view('public.unavailable', ['request' => $request]);
            return;
        }

        $old = [];
        $errors = [];
        if (($request['status'] ?? '') === 'error') {
            $customer = AutofacturaCustomer::getByRequest((int) $request['id']);
            if ($customer) {
                $old = [
                    'rfc' => (string) ($customer['rfc'] ?? ''),
                    'razon_social' => (string) ($customer['razon_social'] ?? ''),
                    'codigo_postal' => (string) ($customer['codigo_postal'] ?? ''),
                    'regimen_fiscal' => (string) ($customer['regimen_fiscal'] ?? ''),
                    'uso_cfdi' => (string) ($customer['uso_cfdi'] ?? ''),
                    'email' => (string) ($customer['email'] ?? ''),
                    'phone' => (string) ($customer['phone'] ?? ''),
                ];
            }

            $latestErrorLog = AutofacturaLog::getLatestByRequestAndAction((int) $request['id'], 'invoice_generation_error');
            $latestError = trim((string) ($latestErrorLog['details'] ?? ($_SESSION['_public_invoice_errors'][$token] ?? '')));
            if ($latestError !== '') {
                $errors[] = $this->getPublicInvoiceErrorMessage();
            } else {
                $errors[] = 'La factura anterior no se pudo generar. Comunícate con el administrador.';
            }
        }

        // Obtener datos del negocio
        $business = Business::find($request['business_id']);
        $concept = InvoiceConcept::find($request['concept_id']);
        $settings = BusinessSetting::getByBusiness($request['business_id']);

        view('public.invoice-form', [
            'request'  => $request,
            'business' => $business,
            'concept'  => $concept,
            'settings' => $settings,
            'token'    => $token,
            'old' => $old,
            'errors' => $errors,
            'efosCheck' => null,
        ]);
    }

    /**
     * Procesar formulario público
     */
    public function submit(string $token): void
    {
        $request = AutofacturaRequest::findByToken($token);

        if (!$request) {
            http_response_code(404);
            view('public.not-found');
            return;
        }

        if (($request['status'] ?? null) === 'facturada') {
            $customer = AutofacturaCustomer::getByRequest((int) $request['id']);
            $request = $this->hydrateInvoiceAssets($request, $token);
            $business = Business::find($request['business_id']);
            $settings = BusinessSetting::getByBusiness($request['business_id']);
            view('public.already-invoiced', [
                'request' => $request,
                'customer' => $customer,
                'business' => $business,
                'settings' => $settings,
                'canSendWhatsapp' => $this->canSendInvoiceWhatsapp($request, $customer),
            ]);
            return;
        }

        if (!in_array($request['status'], ['pendiente', 'enviada', 'error'], true)) {
            view('public.unavailable', ['request' => $request]);
            return;
        }

        // Verificar CSRF
        $csrfToken = $_POST['_csrf_token'] ?? '';
        if (!verify_csrf($csrfToken)) {
            http_response_code(403);
            die('Token CSRF inválido.');
        }

        $runtimeSettings = BusinessSetting::getRuntimeByBusiness($request['business_id']) ?? [];

        // Recoger datos del cliente
        $customerData = [
            'request_id'     => $request['id'],
            'rfc'            => strtoupper(trim($_POST['rfc'] ?? '')),
            'razon_social'   => strtoupper(trim($_POST['razon_social'] ?? '')),
            'codigo_postal'  => trim($_POST['codigo_postal'] ?? ''),
            'regimen_fiscal' => trim($_POST['regimen_fiscal'] ?? ''),
            'uso_cfdi'       => trim($_POST['uso_cfdi'] ?? ''),
            'email'          => trim($_POST['email'] ?? ''),
            'phone'          => trim($_POST['phone'] ?? '') ?: null,
        ];

        if ($customerData['rfc'] === 'XAXX010101000') {
            $customerData['codigo_postal'] = (string) ($runtimeSettings['codigo_postal'] ?? '');
            $customerData['regimen_fiscal'] = '616';
            $customerData['uso_cfdi'] = 'S01';
        }

        // Validaciones básicas
        $errors = [];
        if (empty($customerData['rfc'])) $errors[] = 'RFC es obligatorio.';
        if (empty($customerData['razon_social'])) $errors[] = 'Razón social es obligatoria.';
        if (empty($customerData['codigo_postal'])) $errors[] = 'Código postal es obligatorio.';
        if (empty($customerData['regimen_fiscal'])) $errors[] = 'Régimen fiscal es obligatorio.';
        if (empty($customerData['uso_cfdi'])) $errors[] = 'Uso de CFDI es obligatorio.';
        if (empty($customerData['email'])) $errors[] = 'Correo electrónico es obligatorio.';

        if (!empty($errors)) {
            $this->renderInvoiceForm($request, $token, [
                'errors' => $errors,
                'old' => $customerData,
                'efosCheck' => null,
            ]);
            return;
        }

        $business = Business::find($request['business_id']);
        $concept = InvoiceConcept::find($request['concept_id']);
        $settings = $runtimeSettings;

        $efosResult = EfosValidationService::validateRfc($customerData['rfc']);

        $customerData['efos_status'] = (string) ($efosResult['status'] ?? 'no_verificado');
        $customerData['efos_checked_at'] = date('Y-m-d H:i:s');

        if (!empty($efosResult['found']) && empty($efosResult['allow_continue'])) {
            $this->renderInvoiceForm($request, $token, [
                'errors' => [(string) ($efosResult['message'] ?? 'No es posible generar la factura para este RFC.')],
                'old' => $customerData,
                'efosCheck' => $efosResult,
            ]);
            return;
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
            'receptor_rfc' => (string) $customerData['rfc'],
            'receptor_nombre' => (string) $customerData['razon_social'],
            'receptor_cp' => (string) $customerData['codigo_postal'],
            'receptor_regimen_fiscal' => (string) $customerData['regimen_fiscal'],
            'receptor_uso_cfdi' => (string) $customerData['uso_cfdi'],
            'receptor_email' => (string) $customerData['email'],
            'sat_product_key' => (string) ($concept['sat_product_key'] ?? '01010101'),
            'sat_unit_key' => (string) ($concept['sat_unit_key'] ?? 'E48'),
            'unit_name' => (string) ($concept['unit_name'] ?? 'Servicio'),
            'tax_object' => (string) ($concept['tax_object'] ?? '02'),
            'tax_rate' => (float) ($concept['tax_rate'] ?? 0.16),
        ];

        $apiConfig = [];
        if (($settings['invoicing_mode'] ?? 'direct') === 'ef_api') {
            $apiConfig = [
                'api_url' => (string) ($settings['api_url'] ?? ''),
                'api_user' => (string) ($settings['api_user'] ?? ''),
                'api_password' => (string) ($settings['api_password'] ?? ''),
                'api_key' => (string) ($settings['api_key'] ?? ''),
            ];
        }

        $csdCredentials = BusinessSetting::getCsdCredentials((int) $request['business_id']);

        try {
            Database::beginTransaction();

            AutofacturaCustomer::upsertByRequest((int) $request['id'], $customerData);
            AutofacturaRequest::updateStatus($request['id'], 'capturada');

            AutofacturaLog::log(
                'customer_data_captured',
                $request['id'],
                $request['business_id'],
                "RFC: {$customerData['rfc']}"
            );

            $invoiceResult = EfectosFiscalesService::createInvoice($invoiceData, $apiConfig, $csdCredentials);
            if (empty($invoiceResult['success'])) {
                throw new RuntimeException((string) ($invoiceResult['message'] ?? 'No se pudo generar la factura.'));
            }

            $requiredInvoiceColumns = ['invoice_uuid', 'invoice_xml_url', 'invoice_pdf_url', 'invoiced_at'];
            $missingInvoiceColumns = [];
            foreach ($requiredInvoiceColumns as $column) {
                if (!AutofacturaRequest::hasColumn($column)) {
                    $missingInvoiceColumns[] = $column;
                }
            }
            if (!empty($missingInvoiceColumns)) {
                throw new RuntimeException(
                    'Faltan columnas para persistir la factura en autofactura_requests: ' . implode(', ', $missingInvoiceColumns)
                );
            }

            $requestUpdate = [
                'status' => 'facturada',
                'invoice_uuid' => (string) ($invoiceResult['uuid'] ?? null),
                'invoice_xml_url' => (string) ($invoiceResult['xml_url'] ?? null),
                'invoice_pdf_url' => (string) ($invoiceResult['pdf_url'] ?? null),
                'invoiced_at' => date('Y-m-d H:i:s'),
            ];

            $persisted = AutofacturaRequest::update($request['id'], $requestUpdate);
            if (!$persisted) {
                throw new RuntimeException('No se pudo persistir UUID/XML/PDF de la factura en la base de datos.');
            }

            $remoteCreditsBeforeStamp = EfectosFiscalesService::extractCreditInteger(
                $invoiceResult['remote_credits_before_stamp'] ?? null
            );
            if ($remoteCreditsBeforeStamp !== null) {
                $syncedCredits = max(0, $remoteCreditsBeforeStamp - 1);
                Business::setStampCredits((int) $request['business_id'], $syncedCredits);
            }

            AutofacturaLog::log(
                'invoice_generated',
                $request['id'],
                $request['business_id'],
                'UUID: ' . ($invoiceResult['uuid'] ?? 'N/A')
            );

            AutofacturaLog::log(
                'stamp_credit_consumed',
                $request['id'],
                $request['business_id'],
                'Se consumió 1 timbre. Saldo sincronizado restante: ' . Business::getStampCredits((int) $request['business_id'])
            );

            Database::commit();

            $updatedRequest = AutofacturaRequest::find((int) $request['id']) ?: $request;
            if (
                empty($updatedRequest['invoice_uuid'])
                || empty($updatedRequest['invoice_xml_url'])
                || empty($updatedRequest['invoice_pdf_url'])
            ) {
                throw new RuntimeException('La factura se generó, pero no se guardaron UUID/XML/PDF en la base de datos.');
            }
            $_SESSION['_latest_invoices'][$token] = [
                'invoice_uuid' => $invoiceResult['uuid'] ?? null,
                'invoice_xml_url' => $invoiceResult['xml_url'] ?? null,
                'invoice_pdf_url' => $invoiceResult['pdf_url'] ?? null,
            ];
            $updatedRequest = $this->hydrateInvoiceAssets($updatedRequest, $token);
            $commercialName = trim((string) ($settings['commercial_name'] ?? ''));
            $businessDisplayName = $commercialName !== '' ? $commercialName : (string) ($business['name'] ?? 'AutoFactura');
            $businessLogoUrl = !empty($settings['logo']) ? url('storage/uploads/logos/' . $settings['logo']) : null;

            if (!empty($customerData['email'])) {
                $uuid = (string) ($updatedRequest['invoice_uuid'] ?? '');
                $xmlRelativeUrl = (string) ($updatedRequest['invoice_xml_url'] ?? '');
                $pdfRelativeUrl = (string) ($updatedRequest['invoice_pdf_url'] ?? '');
                $xmlAbsolutePath = $xmlRelativeUrl !== '' ? EfectosFiscalesService::storagePathFromUrl($xmlRelativeUrl) : null;
                $pdfAbsolutePath = $pdfRelativeUrl !== '' ? EfectosFiscalesService::storagePathFromUrl($pdfRelativeUrl) : null;

                $mailResult = MailgunService::sendInvoiceDocuments(
                    (string) $customerData['email'],
                    $businessDisplayName,
                    (string) (($request['custom_concept_text'] ?? '') !== '' ? $request['custom_concept_text'] : ($concept['name'] ?? 'Factura')),
                    (float) ($request['amount'] ?? 0),
                    $uuid,
                    $xmlRelativeUrl !== '' ? url(ltrim($xmlRelativeUrl, '/')) : null,
                    $pdfRelativeUrl !== '' ? url(ltrim($pdfRelativeUrl, '/')) : null,
                    $xmlAbsolutePath,
                    $pdfAbsolutePath,
                    $businessLogoUrl
                );

                if (!empty($mailResult['success'])) {
                    AutofacturaLog::log('invoice_email_sent', $request['id'], $request['business_id'], 'Factura enviada a: ' . $customerData['email']);
                } else {
                    AutofacturaLog::log('invoice_email_error', $request['id'], $request['business_id'], $mailResult['message'] ?? 'Error desconocido');
                }
            }

            view('public.success', [
                'request'  => $updatedRequest,
                'customer' => $customerData,
                'invoice'  => $invoiceResult,
                'canSendWhatsapp' => $this->canSendInvoiceWhatsapp($updatedRequest, $customerData),
            ]);
            return;
        } catch (Throwable $e) {
            if (Database::getInstance()->inTransaction()) {
                Database::rollBack();
            }

            AutofacturaRequest::updateStatus($request['id'], 'error');
            AutofacturaLog::log(
                'invoice_generation_error',
                $request['id'],
                $request['business_id'],
                $e->getMessage()
            );

            $_SESSION['_public_invoice_errors'][$token] = $this->getPublicInvoiceErrorMessage();

            $this->renderInvoiceForm($request, $token, [
                'errors' => [$this->getPublicInvoiceErrorMessage()],
                'old' => $customerData,
                'efosCheck' => null,
            ]);
            return;
        }
    }

    public function efosCheck(string $token): void
    {
        $request = AutofacturaRequest::findByToken($token);
        if (!$request) {
            http_response_code(404);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => false, 'message' => 'Solicitud no encontrada.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $rfc = strtoupper(trim((string) ($_GET['rfc'] ?? '')));
        if ($rfc === '' || strlen($rfc) < 12) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => true, 'result' => null], JSON_UNESCAPED_UNICODE);
            return;
        }

        $result = EfosValidationService::validateRfc($rfc);

        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => true, 'result' => $result], JSON_UNESCAPED_UNICODE);
    }

    public function sendInvoiceEmail(string $token): void
    {
        $request = AutofacturaRequest::findByToken($token);
        if (!$request) {
            http_response_code(404);
            view('public.not-found');
            return;
        }

        $csrfToken = $_POST['_csrf_token'] ?? '';
        if (!verify_csrf($csrfToken)) {
            http_response_code(403);
            die('Token CSRF inválido.');
        }

        if (($request['status'] ?? '') !== 'facturada') {
            flash('error', 'La factura todavía no está disponible para envío.');
            Router::redirect('/f/' . $token);
        }

        $request = $this->hydrateInvoiceAssets($request, $token);
        $customer = AutofacturaCustomer::getByRequest((int) $request['id']);
        $business = Business::find($request['business_id']);
        $settings = BusinessSetting::getByBusiness($request['business_id']);

        if (empty($customer['email'])) {
            flash('error', 'No hay un correo electrónico disponible para enviar la factura.');
            Router::redirect('/f/' . $token);
        }

        if (empty($request['invoice_uuid']) || empty($request['invoice_xml_url']) || empty($request['invoice_pdf_url'])) {
            flash('error', 'La factura no tiene archivos completos para enviarse por correo.');
            Router::redirect('/f/' . $token);
        }

        $commercialName = trim((string) ($settings['commercial_name'] ?? ''));
        $businessDisplayName = $commercialName !== '' ? $commercialName : (string) ($business['name'] ?? 'AutoFactura');
        $businessLogoUrl = !empty($settings['logo']) ? url('storage/uploads/logos/' . $settings['logo']) : null;
        $uuid = (string) $request['invoice_uuid'];

        $mailResult = MailgunService::sendInvoiceDocuments(
            (string) $customer['email'],
            $businessDisplayName,
            (string) ($this->resolveConceptName($request) ?? 'Factura'),
            (float) ($request['amount'] ?? 0),
            $uuid,
            url(ltrim((string) $request['invoice_xml_url'], '/')),
            url(ltrim((string) $request['invoice_pdf_url'], '/')),
            EfectosFiscalesService::storagePathFromUrl((string) $request['invoice_xml_url']),
            EfectosFiscalesService::storagePathFromUrl((string) $request['invoice_pdf_url']),
            $businessLogoUrl
        );

        if (!empty($mailResult['success'])) {
            AutofacturaLog::log('invoice_email_resent', $request['id'], $request['business_id'], 'Factura reenviada a: ' . $customer['email']);
            flash('success', 'La factura fue enviada nuevamente por correo.');
        } else {
            AutofacturaLog::log('invoice_email_error', $request['id'], $request['business_id'], $mailResult['message'] ?? 'Error desconocido');
            flash('error', 'No se pudo enviar el correo: ' . ($mailResult['message'] ?? 'error desconocido'));
        }

        Router::redirect('/f/' . $token);
    }

    public function sendInvoiceWhatsapp(string $token): void
    {
        $request = AutofacturaRequest::findByToken($token);
        if (!$request) {
            http_response_code(404);
            view('public.not-found');
            return;
        }

        $csrfToken = $_POST['_csrf_token'] ?? '';
        if (!verify_csrf($csrfToken)) {
            http_response_code(403);
            die('Token CSRF inválido.');
        }

        if (($request['status'] ?? '') !== 'facturada') {
            flash('error', 'La factura todavía no está disponible para envío.');
            Router::redirect('/f/' . $token);
        }

        $request = $this->hydrateInvoiceAssets($request, $token);
        $customer = AutofacturaCustomer::getByRequest((int) $request['id']);
        if (!$this->canSendInvoiceWhatsapp($request, $customer)) {
            flash('error', 'La notificación por WhatsApp no está disponible para esta factura.');
            Router::redirect('/f/' . $token);
        }

        $business = Business::find($request['business_id']);
        $settings = BusinessSetting::getByBusiness($request['business_id']);
        $commercialName = trim((string) ($settings['commercial_name'] ?? ''));
        $businessDisplayName = $commercialName !== '' ? $commercialName : (string) ($business['name'] ?? 'AutoFactura');
        $phone = $this->normalizeTenDigitPhone((string) ($customer['phone'] ?? ''));
        $pdfUrl = !empty($request['invoice_pdf_url']) ? url(ltrim((string) $request['invoice_pdf_url'], '/')) : null;
        $xmlUrl = !empty($request['invoice_xml_url']) ? url(ltrim((string) $request['invoice_xml_url'], '/')) : null;
        $preferredLink = $pdfUrl ?: ($xmlUrl ?: '');

        $whatsappResult = MensajesXyzService::sendInvoiceLinkTemplate(
            $phone,
            (string) ($customer['razon_social'] ?? 'Cliente'),
            $businessDisplayName,
            (string) ($this->resolveConceptName($request) ?? 'Factura'),
            '$' . number_format((float) ($request['amount'] ?? 0), 2) . ' MXN',
            'Disponible ahora',
            $preferredLink,
            'INV-' . $request['id']
        );

        if (!empty($whatsappResult['success'])) {
            AutofacturaLog::log('invoice_whatsapp_sent', $request['id'], $request['business_id'], 'Factura enviada por WhatsApp a: ' . $phone);
            flash('success', 'La factura fue enviada por WhatsApp.');
        } else {
            AutofacturaLog::log('invoice_whatsapp_error', $request['id'], $request['business_id'], $whatsappResult['message'] ?? 'Error desconocido');
            flash('error', 'No se pudo enviar por WhatsApp: ' . ($whatsappResult['message'] ?? 'error desconocido'));
        }

        Router::redirect('/f/' . $token);
    }

    private function resolveConceptName(array $request): ?string
    {
        $custom = trim((string) ($request['custom_concept_text'] ?? ''));
        if ($custom !== '') {
            return $custom;
        }

        $conceptId = (int) ($request['concept_id'] ?? 0);
        if ($conceptId <= 0) {
            return null;
        }

        $concept = InvoiceConcept::find($conceptId);
        return $concept['name'] ?? null;
    }

    private function canSendInvoiceWhatsapp(array $request, ?array $customer): bool
    {
        if (empty($request['invoice_xml_url']) && empty($request['invoice_pdf_url'])) {
            return false;
        }

        $phone = $this->normalizeTenDigitPhone((string) ($customer['phone'] ?? ''));
        if ($phone === '') {
            return false;
        }

        return !AutofacturaLog::hasActionForRequest((int) $request['id'], 'invoice_whatsapp_sent');
    }

    private function normalizeTenDigitPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        return strlen($digits) === 10 ? $digits : '';
    }

    private function hydrateInvoiceAssets(array $request, string $token): array
    {
        $sessionInvoice = $_SESSION['_latest_invoices'][$token] ?? null;
        if (is_array($sessionInvoice)) {
            foreach (['invoice_uuid', 'invoice_xml_url', 'invoice_pdf_url'] as $field) {
                if (empty($request[$field]) && !empty($sessionInvoice[$field])) {
                    $request[$field] = $sessionInvoice[$field];
                }
            }
        }

        $uuid = (string) ($request['invoice_uuid'] ?? '');
        if ($uuid !== '') {
            if (empty($request['invoice_xml_url'])) {
                $assets = EfectosFiscalesService::findInvoiceAssetUrls($uuid);
                if (!empty($assets['xml_url'])) {
                    $request['invoice_xml_url'] = $assets['xml_url'];
                }
            }

            if (empty($request['invoice_pdf_url'])) {
                $assets = $assets ?? EfectosFiscalesService::findInvoiceAssetUrls($uuid);
                if (!empty($assets['pdf_url'])) {
                    $request['invoice_pdf_url'] = $assets['pdf_url'];
                }
            }
        }

        return $request;
    }

    private function renderInvoiceForm(array $request, string $token, array $data = []): void
    {
        $business = Business::find($request['business_id']);
        $concept = InvoiceConcept::find($request['concept_id']);
        $settings = BusinessSetting::getByBusiness($request['business_id']);

        view('public.invoice-form', array_merge([
            'request'  => $request,
            'business' => $business,
            'concept'  => $concept,
            'settings' => $settings,
            'token'    => $token,
            'errors'   => [],
            'old'      => [],
            'efosCheck' => null,
        ], $data));
    }

    private function getPublicInvoiceErrorMessage(): string
    {
        return 'No se pudo generar la factura en este momento. Comunícate con el administrador.';
    }
}
