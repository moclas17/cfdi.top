<?php
/**
 * Controller: InvoiceConceptsController
 */

class InvoiceConceptsController
{
    public function __construct()
    {
        AuthMiddleware::check();
    }

    /**
     * Listar conceptos
     */
    public function index(): void
    {
        $businessId = auth_business_id();
        $concepts = InvoiceConcept::where('business_id', $businessId, 'name', 'ASC');

        view('dashboard.invoice-concepts', [
            'concepts' => $concepts,
            'supportsDefaultAmount' => InvoiceConcept::supportsDefaultAmount(),
        ]);
    }

    /**
     * Crear concepto
     */
    public function store(): void
    {
        AuthMiddleware::verifyCsrf();

        $businessId = auth_business_id();
        $data = [
            'business_id'     => $businessId,
            'name'            => trim($_POST['name'] ?? ''),
            'description'     => trim($_POST['description'] ?? ''),
            'sat_product_key' => trim($_POST['sat_product_key'] ?? ''),
            'sat_unit_key'    => trim($_POST['sat_unit_key'] ?? ''),
            'unit_name'       => trim($_POST['unit_name'] ?? ''),
            'tax_object'      => trim($_POST['tax_object'] ?? '02'),
            'tax_type'        => trim($_POST['tax_type'] ?? 'IVA'),
            'tax_rate'        => (float) ($_POST['tax_rate'] ?? 0.16),
            'is_default'      => isset($_POST['is_default']) ? 1 : 0,
            'is_active'       => 1,
        ];

        if (InvoiceConcept::supportsDefaultAmount()) {
            $data['default_amount'] = (float) ($_POST['default_amount'] ?? 0);
        }

        // Validar campos obligatorios
        if (empty($data['name']) || empty($data['sat_product_key']) || empty($data['sat_unit_key'])) {
            flash('error', 'Nombre, clave de producto SAT y clave de unidad SAT son obligatorios.');
            Router::redirect('/invoice-concepts');
        }

        InvoiceConcept::createForBusiness($data);
        AutofacturaLog::log('concept_created', null, $businessId, "Concepto: {$data['name']}");
        flash('success', 'Concepto creado correctamente.');
        Router::redirect('/invoice-concepts');
    }

    /**
     * Actualizar concepto
     */
    public function update(): void
    {
        AuthMiddleware::verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $businessId = auth_business_id();

        // Verificar que el concepto pertenece al negocio
        $concept = InvoiceConcept::find($id);
        if (!$concept || $concept['business_id'] != $businessId) {
            flash('error', 'Concepto no encontrado.');
            Router::redirect('/invoice-concepts');
        }

        $data = [
            'name'            => trim($_POST['name'] ?? ''),
            'description'     => trim($_POST['description'] ?? ''),
            'sat_product_key' => trim($_POST['sat_product_key'] ?? ''),
            'sat_unit_key'    => trim($_POST['sat_unit_key'] ?? ''),
            'unit_name'       => trim($_POST['unit_name'] ?? ''),
            'tax_object'      => trim($_POST['tax_object'] ?? '02'),
            'tax_type'        => trim($_POST['tax_type'] ?? 'IVA'),
            'tax_rate'        => (float) ($_POST['tax_rate'] ?? 0.16),
            'is_default'      => isset($_POST['is_default']) ? 1 : 0,
            'is_active'       => isset($_POST['is_active']) ? 1 : 0,
        ];

        if (InvoiceConcept::supportsDefaultAmount()) {
            $data['default_amount'] = (float) ($_POST['default_amount'] ?? 0);
        }

        InvoiceConcept::updateForBusiness($id, $data);
        AutofacturaLog::log('concept_updated', null, $businessId, "Concepto ID: {$id}");
        flash('success', 'Concepto actualizado correctamente.');
        Router::redirect('/invoice-concepts');
    }

    /**
     * Eliminar concepto
     */
    public function delete(): void
    {
        AuthMiddleware::verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $businessId = auth_business_id();

        $concept = InvoiceConcept::find($id);
        if (!$concept || $concept['business_id'] != $businessId) {
            flash('error', 'Concepto no encontrado.');
            Router::redirect('/invoice-concepts');
        }

        $usage = Database::fetchOne(
            "SELECT COUNT(*) AS total FROM autofactura_requests WHERE business_id = :bid AND concept_id = :cid",
            ['bid' => $businessId, 'cid' => $id]
        );

        if ((int) ($usage['total'] ?? 0) > 0) {
            flash('error', 'No se puede eliminar el concepto porque ya está siendo usado por solicitudes de autofactura.');
            Router::redirect('/invoice-concepts');
        }

        try {
            InvoiceConcept::delete($id);
            AutofacturaLog::log('concept_deleted', null, $businessId, "Concepto: {$concept['name']}");
            flash('success', 'Concepto eliminado correctamente.');
        } catch (DatabaseQueryException $e) {
            if ($e->isIntegrityViolation()) {
                flash('error', 'No se puede eliminar el concepto porque tiene registros relacionados.');
            } else {
                flash('error', 'No se pudo eliminar el concepto. Inténtalo de nuevo.');
            }
            AutofacturaLog::log('concept_delete_error', null, $businessId, $e->getMessage());
        } catch (Throwable $e) {
            flash('error', 'Ocurrió un error inesperado al eliminar el concepto.');
            AutofacturaLog::log('concept_delete_error', null, $businessId, $e->getMessage());
        }

        Router::redirect('/invoice-concepts');
    }

    /**
     * Alternar estado activo/inactivo
     */
    public function toggleActive(): void
    {
        AuthMiddleware::verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $businessId = auth_business_id();

        $concept = InvoiceConcept::find($id);
        if (!$concept || (int) $concept['business_id'] !== $businessId) {
            flash('error', 'Concepto no encontrado.');
            Router::redirect('/invoice-concepts');
        }

        $newStatus = (int) (($concept['is_active'] ?? 0) ? 0 : 1);

        InvoiceConcept::update($id, ['is_active' => $newStatus]);

        AutofacturaLog::log(
            'concept_active_toggled',
            null,
            $businessId,
            sprintf('Concepto ID: %d, nuevo estado: %s', $id, $newStatus ? 'activo' : 'inactivo')
        );

        flash('success', $newStatus ? 'Concepto activado correctamente.' : 'Concepto desactivado correctamente.');
        Router::redirect('/invoice-concepts');
    }

    /**
     * Alternar concepto por defecto
     */
    public function toggleDefault(): void
    {
        AuthMiddleware::verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $businessId = auth_business_id();

        $concept = InvoiceConcept::find($id);
        if (!$concept || (int) $concept['business_id'] !== $businessId) {
            flash('error', 'Concepto no encontrado.');
            Router::redirect('/invoice-concepts');
        }

        $newDefault = (int) (($concept['is_default'] ?? 0) ? 0 : 1);

        InvoiceConcept::updateForBusiness($id, ['is_default' => $newDefault]);

        AutofacturaLog::log(
            'concept_default_toggled',
            null,
            $businessId,
            sprintf('Concepto ID: %d, nuevo default: %s', $id, $newDefault ? 'si' : 'no')
        );

        flash('success', $newDefault ? 'Concepto marcado como predeterminado.' : 'Concepto ya no es el predeterminado.');
        Router::redirect('/invoice-concepts');
    }
}
