<?php
/**
 * Controller: DashboardController
 */

class DashboardController
{
    public function __construct()
    {
        AuthMiddleware::check();
    }

    /**
     * Mostrar dashboard principal
     */
    public function index(): void
    {
        $businessId = auth_business_id();
        $settings = BusinessSetting::getByBusiness($businessId);

        // Estadísticas rápidas
        $stats = [
            'total_requests'     => AutofacturaRequest::count('business_id', $businessId),
            'pending_requests'   => count(Database::fetchAll(
                "SELECT id FROM autofactura_requests WHERE business_id = :bid AND status = 'pendiente'",
                ['bid' => $businessId]
            )),
            'invoiced_requests'  => count(Database::fetchAll(
                "SELECT id FROM autofactura_requests WHERE business_id = :bid AND status = 'facturada'",
                ['bid' => $businessId]
            )),
            'total_concepts'     => InvoiceConcept::count('business_id', $businessId),
            'stamp_credits'      => Business::getStampCredits($businessId),
        ];

        // Últimas solicitudes
        $recentRequests = Database::fetchAll(
            "SELECT r.*, c.name as concept_name
             FROM autofactura_requests r
             LEFT JOIN invoice_concepts c ON r.concept_id = c.id
             WHERE r.business_id = :bid
             ORDER BY r.created_at DESC LIMIT 5",
            ['bid' => $businessId]
        );

        $onboarding = $this->buildOnboardingChecklist($businessId, $settings, $stats);

        view('dashboard.index', [
            'stats'          => $stats,
            'recentRequests' => $recentRequests,
            'onboarding'     => $onboarding,
        ]);
    }

    private function buildOnboardingChecklist(int $businessId, ?array $settings, array $stats): array
    {
        $hasBusinessData = !empty($settings) && trim((string) ($settings['commercial_name'] ?? '')) !== '';
        $activeConcept = InvoiceConcept::getFirstActive($businessId);
        $hasConcepts = !empty($activeConcept);
        $timbradoReady = !empty($settings)
            && !empty($settings['csd_key_path'])
            && !empty($settings['csd_cer_path'])
            && !empty($settings['csd_password'])
            && !empty($settings['rfc_emisor'])
            && !empty($settings['nombre_emisor'])
            && !empty($settings['api_url'])
            && !empty($settings['api_user'])
            && !empty($settings['api_password']);
        $hasCredits = (int) ($stats['stamp_credits'] ?? 0) > 0;
        $hasFirstInvoice = (int) ($stats['invoiced_requests'] ?? 0) > 0;

        $steps = [
            [
                'key' => 'business',
                'title' => 'Negocio',
                'description' => 'Completa el nombre comercial y la configuración base de tu negocio.',
                'is_complete' => $hasBusinessData,
                'action_url' => url('business-settings?tab=business'),
                'action_label' => $hasBusinessData ? 'Ver configuración' : 'Completar negocio',
                'icon' => 'bi-building',
            ],
            [
                'key' => 'concepts',
                'title' => 'Conceptos',
                'description' => 'Crea al menos un concepto activo para poder facturar.',
                'is_complete' => $hasConcepts,
                'action_url' => url('invoice-concepts'),
                'action_label' => $hasConcepts ? 'Ver conceptos' : 'Configurar conceptos',
                'icon' => 'bi-card-checklist',
            ],
            [
                'key' => 'timbrado',
                'title' => 'Timbrado',
                'description' => 'Sube tu CSD y deja lista tu cuenta de timbrado.',
                'is_complete' => $timbradoReady,
                'action_url' => url('business-settings?tab=csd'),
                'action_label' => $timbradoReady ? 'Ver timbrado' : 'Configurar timbrado',
                'icon' => 'bi-shield-lock',
            ],
            [
                'key' => 'credits',
                'title' => 'Timbres',
                'description' => 'Necesitas saldo disponible para poder emitir facturas.',
                'is_complete' => $hasCredits,
                'action_url' => url('stamp-purchases'),
                'action_label' => $hasCredits ? 'Ver timbres' : 'Comprar timbres',
                'icon' => 'bi-ticket-perforated',
            ],
            [
                'key' => 'first_invoice',
                'title' => 'Primera factura',
                'description' => 'Haz tu primera factura para validar el flujo completo.',
                'is_complete' => $hasFirstInvoice,
                'action_url' => url('autofactura-requests'),
                'action_label' => $hasFirstInvoice ? 'Ver facturas' : 'Generar primera factura',
                'icon' => 'bi-receipt',
            ],
        ];

        $completedSteps = count(array_filter($steps, static fn(array $step): bool => !empty($step['is_complete'])));
        $totalSteps = count($steps);
        $progressPercent = $totalSteps > 0 ? (int) floor(($completedSteps / $totalSteps) * 100) : 0;
        $coreReady = $hasBusinessData && $hasConcepts && $timbradoReady && $hasCredits;

        return [
            'steps' => $steps,
            'completed_steps' => $completedSteps,
            'total_steps' => $totalSteps,
            'progress_percent' => $progressPercent,
            'core_ready' => $coreReady,
            'all_done' => $completedSteps === $totalSteps,
            'title' => $coreReady
                ? ($hasFirstInvoice ? 'Tu cuenta ya está operando' : 'Tu cuenta ya está lista para facturar')
                : 'Completa tu configuración',
            'description' => $coreReady
                ? ($hasFirstInvoice
                    ? 'Todo el flujo principal ya está configurado correctamente.'
                    : 'Ya puedes emitir facturas. Solo falta completar tu primera para cerrar el proceso.')
                : 'Sigue estos pasos para dejar tu cuenta lista y empezar a facturar sin fricción.',
        ];
    }
}
