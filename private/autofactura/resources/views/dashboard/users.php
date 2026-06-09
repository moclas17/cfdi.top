<?php
$title = 'Usuarios - AutoFactura';
$pageTitle = is_superuser() ? 'Gestión de Usuarios' : 'Mi Cuenta';
$activeMenu = 'users';
ob_start();
?>

<div class="row g-4">
    <div class="col-lg-<?= is_superuser() ? '4' : '8' ?>">
        <div class="card-af">
            <div class="card-body">
                <h5 class="mb-3"><i class="bi bi-person-circle me-2"></i>Mi Perfil</h5>

                <form method="POST" action="<?= url('users/profile') ?>">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label-af">Nombre</label>
                        <input type="text" name="name" class="form-control form-control-af" value="<?= e($currentUser['name'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label-af">Correo</label>
                        <input type="email" class="form-control form-control-af" value="<?= e($currentUser['email'] ?? '') ?>" disabled>
                    </div>

                    <div class="mb-3">
                        <label class="form-label-af">Teléfono</label>
                        <input type="text" name="phone" class="form-control form-control-af" value="<?= e($currentUser['phone'] ?? '') ?>" maxlength="20">
                    </div>

                    <hr>
                    <p class="text-muted small mb-2">Cambiar contraseña</p>

                    <div class="mb-3">
                        <label class="form-label-af">Nueva contraseña</label>
                        <input type="password" name="password" class="form-control form-control-af" minlength="6">
                    </div>

                    <div class="mb-3">
                        <label class="form-label-af">Confirmar contraseña</label>
                        <input type="password" name="password_confirm" class="form-control form-control-af" minlength="6">
                    </div>

                    <button type="submit" class="btn btn-af w-100">
                        <i class="bi bi-save me-1"></i> Guardar perfil
                    </button>
                </form>
            </div>
        </div>
    </div>

    <?php if (is_superuser()): ?>
        <div class="col-lg-8">
            <div class="card-af">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0"><i class="bi bi-people-fill me-2"></i>Usuarios del sistema</h5>
                        <span class="text-muted small">Total: <?= count($users) ?></span>
                    </div>

                    <?php if (empty($users)): ?>
                        <div class="text-center py-4 text-muted">No hay usuarios registrados.</div>
                    <?php else: ?>
                        <div class="accordion" id="usersAccordion">
                            <?php foreach ($users as $u): ?>
                                <?php $uid = (int) $u['id']; ?>
                                <div class="accordion-item mb-2 border rounded">
                                    <h2 class="accordion-header" id="heading<?= $uid ?>">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $uid ?>" aria-expanded="false" aria-controls="collapse<?= $uid ?>">
                                            <div class="d-flex w-100 align-items-center justify-content-between pe-3">
                                                <span>
                                                    <strong>#<?= $uid ?></strong> <?= e($u['name']) ?> (<?= e($u['email']) ?>)
                                                    <span class="ms-2 badge text-bg-light border">
                                                        <i class="bi bi-ticket-perforated me-1"></i><?= number_format((int) ($u['stamp_credits'] ?? 0)) ?> timbres
                                                    </span>
                                                </span>
                                                <span>
                                                    <span class="badge <?= $u['role'] === 'superuser' ? 'text-bg-primary' : 'text-bg-secondary' ?>"><?= e($u['role']) ?></span>
                                                    <span class="badge <?= (int) $u['is_active'] === 1 ? 'text-bg-success' : 'text-bg-danger' ?>"><?= (int) $u['is_active'] === 1 ? 'Activo' : 'Inactivo' ?></span>
                                                </span>
                                            </div>
                                        </button>
                                    </h2>
                                    <div id="collapse<?= $uid ?>" class="accordion-collapse collapse" aria-labelledby="heading<?= $uid ?>" data-bs-parent="#usersAccordion">
                                        <div class="accordion-body">
                                            <form method="POST" action="<?= url('users/update') ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= $uid ?>">

                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label-af">Nombre</label>
                                                        <input type="text" name="name" class="form-control form-control-af" value="<?= e($u['name']) ?>" required>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label-af">Correo</label>
                                                        <input type="email" name="email" class="form-control form-control-af" value="<?= e($u['email']) ?>" required>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label-af">Teléfono</label>
                                                        <input type="text" name="phone" class="form-control form-control-af" value="<?= e($u['phone'] ?? '') ?>" maxlength="20">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label-af">Timbres disponibles</label>
                                                        <input type="text" class="form-control form-control-af" value="<?= number_format((int) ($u['stamp_credits'] ?? 0)) ?>" disabled>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label-af">Rol</label>
                                                        <select name="role" class="form-select form-select-af" required>
                                                            <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>user</option>
                                                            <option value="superuser" <?= $u['role'] === 'superuser' ? 'selected' : '' ?>>superuser</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label-af">Nueva contraseña (opcional)</label>
                                                        <input type="password" name="password" class="form-control form-control-af" minlength="6">
                                                    </div>
                                                </div>

                                                <div class="form-check mt-3 mb-3">
                                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive<?= $uid ?>" <?= (int) $u['is_active'] === 1 ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="isActive<?= $uid ?>">
                                                        Cuenta activa
                                                    </label>
                                                </div>

                                                <div class="d-flex flex-wrap gap-2">
                                                    <button type="submit" class="btn btn-af">
                                                        <i class="bi bi-check2-circle me-1"></i> Guardar usuario
                                                    </button>
                                                </div>
                                            </form>

                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require VIEWS_PATH . '/layouts/app.php';
?>
