<?php
$title = 'Configuración - AutoFactura';
$pageTitle = 'Configuración del Negocio';
$activeMenu = 'settings';
$hasCsdConfigured = !empty($settings['csd_key_path']) && !empty($settings['csd_cer_path']) && !empty($settings['csd_password']);
$hasEfAccountConfigured = !empty($settings['api_user']) && !empty($settings['api_password']);
$requestedTab = trim((string) ($_GET['tab'] ?? ''));
$allowedTabs = ['csd', 'business', 'visuals'];
$activeTab = in_array($requestedTab, $allowedTabs, true) ? $requestedTab : 'csd';
ob_start();
?>

<div class="card-af mb-4">
    <div class="card-body">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
            <div>
                <h5 class="mb-1"><i class="bi bi-gear me-2"></i>Configuración del Negocio</h5>
                <p class="text-muted mb-0">Organiza aquí la configuración fiscal, los datos generales del negocio y la parte visual de tus links.</p>
            </div>
        </div>

        <form method="POST" action="<?= url('business-settings') ?>" enctype="multipart/form-data" id="settingsForm">
            <?= csrf_field() ?>

            <ul class="nav nav-pills gap-2 mb-4" id="businessSettingsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'csd' ? 'active' : '' ?>" id="tab-csd-button" data-bs-toggle="pill" data-bs-target="#tab-csd" type="button" role="tab" aria-controls="tab-csd" aria-selected="<?= $activeTab === 'csd' ? 'true' : 'false' ?>">
                        <i class="bi bi-shield-lock me-1"></i> CSD
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'business' ? 'active' : '' ?>" id="tab-business-button" data-bs-toggle="pill" data-bs-target="#tab-business" type="button" role="tab" aria-controls="tab-business" aria-selected="<?= $activeTab === 'business' ? 'true' : 'false' ?>">
                        <i class="bi bi-building me-1"></i> Datos del negocio
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'visuals' ? 'active' : '' ?>" id="tab-visuals-button" data-bs-toggle="pill" data-bs-target="#tab-visuals" type="button" role="tab" aria-controls="tab-visuals" aria-selected="<?= $activeTab === 'visuals' ? 'true' : 'false' ?>">
                        <i class="bi bi-palette me-1"></i> Visuales
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="businessSettingsTabsContent">
                <div class="tab-pane fade <?= $activeTab === 'csd' ? 'show active' : '' ?>" id="tab-csd" role="tabpanel" aria-labelledby="tab-csd-button" tabindex="0">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body">
                            <h6 class="text-muted mb-3"><i class="bi bi-shield-lock me-1"></i> Sellos CSD del emisor</h6>
                            <div class="alert alert-warning py-3" role="alert">
                                Primero sube tu archivo <strong>.key</strong>, tu archivo <strong>.cer</strong> y la contraseña del CSD. Cuando el certificado sea válido, AutoFactura guarda los datos fiscales del emisor y prepara automáticamente tu cuenta de timbrado.
                            </div>

                            <div class="mb-3">
                                <?php if ($hasCsdConfigured): ?>
                                    <span class="badge text-bg-success">CSD configurado</span>
                                    <?php if (!empty($settings['csd_uploaded_at'])): ?>
                                        <small class="text-muted ms-2">Actualizado: <?= e(format_date($settings['csd_uploaded_at'], 'd/m/Y H:i')) ?></small>
                                    <?php endif; ?>
                                    <div class="row g-3 mt-2">
                                        <div class="col-md-3">
                                            <div class="small text-muted">RFC del certificado</div>
                                            <div class="fw-semibold"><?= e($settings['csd_rfc'] ?? 'No disponible') ?></div>
                                        </div>
                                        <div class="col-md-5">
                                            <div class="small text-muted">Razón social del certificado</div>
                                            <div class="fw-semibold"><?= e($settings['nombre_emisor'] ?? 'No disponible') ?></div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="small text-muted">Vigencia desde</div>
                                            <div class="fw-semibold">
                                                <?= !empty($settings['csd_valid_from']) ? e(format_date($settings['csd_valid_from'], 'd/m/Y H:i')) : 'No disponible' ?>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="small text-muted">Vigencia hasta</div>
                                            <div class="fw-semibold">
                                                <?= !empty($settings['csd_valid_to']) ? e(format_date($settings['csd_valid_to'], 'd/m/Y H:i')) : 'No disponible' ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary">CSD no configurado</span>
                                <?php endif; ?>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label-af" for="csd_key">Archivo .key</label>
                                    <input type="file" class="form-control form-control-af" id="csd_key" name="csd_key" accept=".key">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label-af" for="csd_cer">Archivo .cer</label>
                                    <input type="file" class="form-control form-control-af" id="csd_cer" name="csd_cer" accept=".cer">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label-af" for="csd_password">Contraseña CSD</label>
                                    <input type="password" class="form-control form-control-af" id="csd_password" name="csd_password"
                                           placeholder="<?= !empty($settings['csd_password']) ? 'Guardada. Captura una nueva junto con ambos archivos para reemplazar.' : '••••••••' ?>">
                                </div>
                                <div class="col-12">
                                    <small class="text-muted">Para registrar o reemplazar los sellos debes subir los tres elementos al mismo tiempo. Tamaño máximo por archivo: 1 MB.</small>
                                </div>
                                <?php if ($hasCsdConfigured): ?>
                                    <div class="col-12 d-flex flex-wrap gap-2">
                                        <button type="submit" name="remove_csd" value="1" class="btn btn-outline-danger"
                                                onclick="return confirm('¿Eliminar los sellos CSD almacenados? Esta acción impedirá sellar CFDI hasta cargar nuevos archivos.');">
                                            <i class="bi bi-shield-x me-1"></i> Eliminar CSD
                                        </button>

                                        <?php if ($hasEfAccountConfigured): ?>
                                            <button type="submit" class="btn btn-af-outline"
                                                    formaction="<?= url('business-settings/test-connection') ?>"
                                                    formmethod="POST">
                                                <i class="bi bi-plug me-1"></i> Probar conexión wsGetCredit
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($hasCsdConfigured): ?>
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-body">
                                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
                                    <div>
                                        <h6 class="text-muted mb-1"><i class="bi bi-file-earmark-text me-1"></i> Datos fiscales del emisor</h6>
                                        <p class="text-muted mb-0">Estos datos se llenan automáticamente desde el CSD y quedan listos para timbrar.</p>
                                    </div>
                                    <?php if ($hasEfAccountConfigured): ?>
                                        <span class="badge text-bg-success">Timbrado listo</span>
                                    <?php endif; ?>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label-af" for="rfc_emisor">RFC Emisor</label>
                                        <input type="text" class="form-control form-control-af" id="rfc_emisor" name="rfc_emisor"
                                               value="<?= e($settings['rfc_emisor'] ?? '') ?>" maxlength="13"
                                               placeholder="Se llena automáticamente desde el CSD" style="text-transform: uppercase;" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label-af" for="nombre_emisor">Nombre / Razón Social</label>
                                        <input type="text" class="form-control form-control-af" id="nombre_emisor" name="nombre_emisor"
                                               value="<?= e($settings['nombre_emisor'] ?? '') ?>" placeholder="Se llena automáticamente desde el CSD" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label-af" for="regimen_fiscal">Régimen Fiscal</label>
                                        <select class="form-select form-select-af" id="regimen_fiscal" name="regimen_fiscal">
                                            <option value="">Seleccionar...</option>
                                            <option value="601" <?= ($settings['regimen_fiscal'] ?? '') === '601' ? 'selected' : '' ?>>601 - General de Ley Personas Morales</option>
                                            <option value="603" <?= ($settings['regimen_fiscal'] ?? '') === '603' ? 'selected' : '' ?>>603 - Personas Morales con Fines no Lucrativos</option>
                                            <option value="605" <?= ($settings['regimen_fiscal'] ?? '') === '605' ? 'selected' : '' ?>>605 - Sueldos y Salarios</option>
                                            <option value="606" <?= ($settings['regimen_fiscal'] ?? '') === '606' ? 'selected' : '' ?>>606 - Arrendamiento</option>
                                            <option value="612" <?= ($settings['regimen_fiscal'] ?? '') === '612' ? 'selected' : '' ?>>612 - Personas Físicas con Actividades Empresariales</option>
                                            <option value="616" <?= ($settings['regimen_fiscal'] ?? '') === '616' ? 'selected' : '' ?>>616 - Sin obligaciones fiscales</option>
                                            <option value="621" <?= ($settings['regimen_fiscal'] ?? '') === '621' ? 'selected' : '' ?>>621 - Incorporación Fiscal</option>
                                            <option value="625" <?= ($settings['regimen_fiscal'] ?? '') === '625' ? 'selected' : '' ?>>625 - Régimen de las Actividades Empresariales con ingresos a través de Plataformas Tecnológicas</option>
                                            <option value="626" <?= ($settings['regimen_fiscal'] ?? '') === '626' ? 'selected' : '' ?>>626 - Régimen Simplificado de Confianza</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label-af" for="codigo_postal">Código Postal</label>
                                        <input type="text" class="form-control form-control-af" id="codigo_postal" name="codigo_postal"
                                               value="<?= e($settings['codigo_postal'] ?? '') ?>" maxlength="5" placeholder="06600">
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="tab-pane fade <?= $activeTab === 'business' ? 'show active' : '' ?>" id="tab-business" role="tabpanel" aria-labelledby="tab-business-button" tabindex="0">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body">
                            <h6 class="text-muted mb-3"><i class="bi bi-building me-1"></i> Datos del negocio</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label-af">Correo del negocio</label>
                                    <input type="email" class="form-control form-control-af" value="<?= e($business['email'] ?? '') ?>" readonly>
                                    <small class="text-muted">Este correo se usa para alta automática y notificaciones administrativas.</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-af" for="commercial_name">Nombre comercial</label>
                                    <input type="text" class="form-control form-control-af" id="commercial_name" name="commercial_name"
                                           value="<?= e($settings['commercial_name'] ?? '') ?>" maxlength="255"
                                           placeholder="Ej. AutoFactura by Efectos Fiscales">
                                    <small class="text-muted">Este nombre se usará en correos y comunicación con clientes.</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-af" for="link_expiration_days">Caducidad de links de autofactura (días)</label>
                                    <input type="number" class="form-control form-control-af" id="link_expiration_days" name="link_expiration_days"
                                           min="1" max="30" step="1" value="<?= (int) ($settings['link_expiration_days'] ?? 3) ?>">
                                    <small class="text-muted">Valor recomendado: 3 días.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade <?= $activeTab === 'visuals' ? 'show active' : '' ?>" id="tab-visuals" role="tabpanel" aria-labelledby="tab-visuals-button" tabindex="0">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body">
                            <h6 class="text-muted mb-3"><i class="bi bi-palette me-1"></i> Visuales</h6>

                            <div class="row align-items-center g-4">
                                <div class="col-lg-4">
                                    <?php if (!empty($settings['logo'])): ?>
                                        <div class="position-relative d-inline-block" id="logoPreviewContainer">
                                            <img src="<?= url('storage/uploads/logos/' . e($settings['logo'])) ?>"
                                                 alt="Logo actual"
                                                 id="logoPreview"
                                                 style="max-height: 100px; max-width: 200px; border-radius: 12px; border: 2px solid var(--af-border); object-fit: contain; background: #fff; padding: 8px;">
                                        </div>
                                    <?php else: ?>
                                        <div id="logoPreviewContainer" style="width: 100px; height: 100px; border-radius: 12px; border: 2px dashed var(--af-border); display: flex; align-items: center; justify-content: center; color: var(--af-text-muted);">
                                            <i class="bi bi-image" style="font-size: 2rem;"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-lg-8">
                                    <div class="mb-3">
                                        <label class="form-label-af">Subir logo</label>
                                        <input type="file" class="form-control form-control-af" name="logo" id="logoInput"
                                               accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml"
                                               onchange="previewLogo(this)">
                                        <small class="text-muted">JPG, PNG, GIF, WebP o SVG. Máx 2MB.</small>
                                    </div>
                                    <?php if (!empty($settings['logo'])): ?>
                                        <button type="submit" name="remove_logo" value="1" class="btn btn-sm btn-outline-danger mb-3"
                                                onclick="return confirm('¿Eliminar el logo actual?');">
                                            <i class="bi bi-trash me-1"></i> Eliminar logo
                                        </button>
                                    <?php endif; ?>
                                    <div class="row g-3 align-items-end">
                                        <div class="col-md-6">
                                            <label class="form-label-af" for="template_color">Color de la plantilla</label>
                                            <div class="d-flex gap-2 align-items-center">
                                                <input type="color" class="form-control form-control-color" id="template_color" name="template_color"
                                                       value="<?= e($settings['template_color'] ?? '#359BE3') ?>" style="width: 56px; height: 42px;">
                                                <input type="text" class="form-control form-control-af" id="template_color_text"
                                                       value="<?= e($settings['template_color'] ?? '#359BE3') ?>" maxlength="7"
                                                       pattern="^#[0-9A-Fa-f]{6}$" oninput="syncColorInput('template_color', this.value)">
                                            </div>
                                            <small class="text-muted">Se usa en barras, acentos y elementos destacados del PDF.</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label-af" for="font_color">Color de la fuente</label>
                                            <div class="d-flex gap-2 align-items-center">
                                                <input type="color" class="form-control form-control-color" id="font_color" name="font_color"
                                                       value="<?= e($settings['font_color'] ?? '#111111') ?>" style="width: 56px; height: 42px;">
                                                <input type="text" class="form-control form-control-af" id="font_color_text"
                                                       value="<?= e($settings['font_color'] ?? '#111111') ?>" maxlength="7"
                                                       pattern="^#[0-9A-Fa-f]{6}$" oninput="syncColorInput('font_color', this.value)">
                                            </div>
                                            <small class="text-muted">Se usa como color base del texto del PDF.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-af">
                <i class="bi bi-check-lg me-1"></i> Guardar Configuración
            </button>
        </form>
    </div>
</div>

<script>
function previewLogo(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];

        if (file.size > 2 * 1024 * 1024) {
            alert('El archivo no debe superar los 2MB.');
            input.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            const container = document.getElementById('logoPreviewContainer');
            container.innerHTML = '<img src="' + e.target.result + '" alt="Vista previa" style="max-height: 100px; max-width: 200px; border-radius: 12px; border: 2px solid var(--af-border); object-fit: contain; background: #fff; padding: 8px;">';
        };
        reader.readAsDataURL(file);
    }
}

function syncColorInput(inputId, value) {
    const normalized = String(value || '').trim();
    if (/^#[0-9a-fA-F]{6}$/.test(normalized)) {
        document.getElementById(inputId).value = normalized;
    }
}

document.getElementById('template_color')?.addEventListener('input', function() {
    document.getElementById('template_color_text').value = this.value.toUpperCase();
});

document.getElementById('font_color')?.addEventListener('input', function() {
    document.getElementById('font_color_text').value = this.value.toUpperCase();
});
</script>

<?php
$content = ob_get_clean();
require VIEWS_PATH . '/layouts/app.php';
?>
