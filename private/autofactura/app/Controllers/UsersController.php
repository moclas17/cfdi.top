<?php
/**
 * Controller: UsersController
 * Administración de usuarios SaaS.
 */

class UsersController
{
    public function __construct()
    {
        AuthMiddleware::check();
    }

    /**
     * Pantalla de usuarios y perfil.
     */
    public function index(): void
    {
        $currentUser = Business::find((int) auth_business_id());
        $users = is_superuser() ? Business::allForAdmin() : [];

        view('dashboard.users', [
            'currentUser' => $currentUser,
            'users' => $users,
        ]);
    }

    /**
     * Editar perfil del usuario autenticado.
     */
    public function updateProfile(): void
    {
        AuthMiddleware::verifyCsrf();

        $id = (int) auth_business_id();
        $user = Business::find($id);

        if (!$user) {
            flash('error', 'Usuario no encontrado.');
            Router::redirect('/users');
        }

        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        if ($name === '') {
            flash('error', 'El nombre es obligatorio.');
            Router::redirect('/users');
        }

        $data = [
            'name' => $name,
            'phone' => $phone !== '' ? $phone : null,
        ];

        if ($password !== '' || $passwordConfirm !== '') {
            if (strlen($password) < 6) {
                flash('error', 'La nueva contraseña debe tener al menos 6 caracteres.');
                Router::redirect('/users');
            }

            if ($password !== $passwordConfirm) {
                flash('error', 'La confirmación de contraseña no coincide.');
                Router::redirect('/users');
            }

            $data['password'] = Business::hashPassword($password);
        }

        Business::update($id, $data);

        $_SESSION['business_name'] = $data['name'];

        AutofacturaLog::log('profile_updated', null, $id);
        flash('success', 'Perfil actualizado correctamente.');
        Router::redirect('/users');
    }

    /**
     * Edición de cualquier usuario (solo superusuario).
     */
    public function updateUser(): void
    {
        AuthMiddleware::requireSuperuser();
        AuthMiddleware::verifyCsrf();

        $targetId = (int) ($_POST['id'] ?? 0);
        $target = Business::find($targetId);

        if (!$target) {
            flash('error', 'Usuario no encontrado.');
            Router::redirect('/users');
        }

        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');
        $role = trim($_POST['role'] ?? 'user');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '' || $email === '') {
            flash('error', 'Nombre y correo son obligatorios.');
            Router::redirect('/users');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'El correo del usuario no es válido.');
            Router::redirect('/users');
        }

        if (!in_array($role, ['user', 'superuser'], true)) {
            $role = 'user';
        }

        $emailOwner = Business::findByEmail($email);
        if ($emailOwner && (int) $emailOwner['id'] !== $targetId) {
            flash('error', 'Ese correo ya está en uso por otro usuario.');
            Router::redirect('/users');
        }

        $data = [
            'name' => $name,
            'email' => $email,
            'phone' => $phone !== '' ? $phone : null,
            'role' => $role,
            'is_active' => $isActive,
        ];

        $password = $_POST['password'] ?? '';
        if ($password !== '') {
            if (strlen($password) < 6) {
                flash('error', 'La contraseña asignada debe tener al menos 6 caracteres.');
                Router::redirect('/users');
            }
            $data['password'] = Business::hashPassword($password);
        }

        // Evitar bloquear al único superusuario activo
        if ((int) $target['id'] === (int) auth_business_id() && ($data['role'] !== 'superuser' || !$data['is_active'])) {
            $activeSuperusers = Database::fetchOne(
                "SELECT COUNT(*) AS total FROM businesses WHERE role = 'superuser' AND is_active = 1 AND id != :id",
                ['id' => auth_business_id()]
            );

            if ((int) ($activeSuperusers['total'] ?? 0) === 0) {
                flash('error', 'Debe existir al menos un superusuario activo en el sistema.');
                Router::redirect('/users');
            }
        }

        Business::update($targetId, $data);

        // Refrescar sesión si el superusuario editó su propia cuenta
        if ($targetId === (int) auth_business_id()) {
            $_SESSION['business_name'] = $data['name'];
            $_SESSION['business_email'] = $data['email'];
            $_SESSION['business_role'] = $data['role'];
        }

        AutofacturaLog::log('user_updated', null, (int) auth_business_id(), "Usuario editado ID: {$targetId}");
        flash('success', 'Usuario actualizado correctamente.');
        Router::redirect('/users');
    }

}
