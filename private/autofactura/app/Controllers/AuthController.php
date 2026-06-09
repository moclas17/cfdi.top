<?php
/**
 * Controller: AuthController (Autenticación)
 */

class AuthController
{
    private const VERIFICATION_RESEND_COOLDOWN = 300;

    /**
     * Mostrar formulario de login
     */
    public function showLogin(): void
    {
        // Si ya está autenticado, redirigir al dashboard
        if (is_authenticated()) {
            Router::redirect('/dashboard');
        }

        view('auth.login');
    }

    /**
     * Mostrar formulario de registro
     */
    public function showRegister(): void
    {
        if (is_authenticated()) {
            Router::redirect('/dashboard');
        }

        view('auth.register');
    }

    /**
     * Procesar login
     */
    public function login(): void
    {
        AuthMiddleware::verifyCsrf();

        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';

        // Validar campos
        if (empty($email) || empty($password)) {
            flash('error', 'Email y contraseña son obligatorios.');
            Router::redirect('/login');
        }

        // Buscar negocio
        $business = Business::findByEmail($email);

        if (!$business || !Business::verifyPassword($password, $business['password'])) {
            flash('error', 'Credenciales incorrectas.');
            AutofacturaLog::log('login_failed', null, null, "Intento fallido para: {$email}");
            Router::redirect('/login');
        }

        // Verificar que esté activo
        if (!$business['is_active']) {
            flash('error', 'Tu cuenta está desactivada. Contacta al administrador.');
            Router::redirect('/login');
        }

        if (!Business::isEmailVerified($business)) {
            $resent = $this->sendVerificationEmailIfAllowed($business);
            AutofacturaLog::log('login_blocked_unverified_email', null, (int) $business['id'], 'Intento de acceso sin verificar.');
            flash('info', $resent
                ? 'Tu cuenta aún no está verificada. Te reenviamos el correo de verificación.'
                : 'Tu cuenta aún no está verificada. Revisa tu correo para activarla.');
            Router::redirect('/login');
        }

        // Iniciar sesión
        $_SESSION['business_id'] = $business['id'];
        $_SESSION['business_name'] = $business['name'];
        $_SESSION['business_email'] = $business['email'];
        $_SESSION['business_role'] = $business['role'] ?? 'user';

        // Regenerar ID de sesión por seguridad
        session_regenerate_id(true);

        AutofacturaLog::log('login_success', null, $business['id']);
        flash('success', '¡Bienvenido, ' . e($business['name']) . '!');
        Router::redirect('/dashboard');
    }

    /**
     * Registrar nuevo usuario (SaaS)
     */
    public function register(): void
    {
        AuthMiddleware::verifyCsrf();

        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        if ($name === '' || $email === '' || $password === '') {
            flash('error', 'Nombre, correo y contraseña son obligatorios.');
            Router::redirect('/register');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'El correo electrónico no es válido.');
            Router::redirect('/register');
        }

        if (strlen($password) < 6) {
            flash('error', 'La contraseña debe tener al menos 6 caracteres.');
            Router::redirect('/register');
        }

        if ($password !== $passwordConfirm) {
            flash('error', 'La confirmación de contraseña no coincide.');
            Router::redirect('/register');
        }

        $existing = Business::findByEmail($email);
        if ($existing) {
            flash('error', 'Ya existe una cuenta con ese correo.');
            Router::redirect('/register');
        }

        $newId = Business::register([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => $password,
            'role' => 'user',
            'is_active' => 1,
        ]);

        $business = Business::find($newId);
        $mailSent = $business ? $this->sendVerificationEmail($business, false) : false;

        AutofacturaLog::log('register_success', null, $newId, "Registro de cuenta: {$email}");
        if ($mailSent) {
            flash('success', 'Cuenta creada. Revisa tu correo para verificarla antes de iniciar sesión.');
        } else {
            flash('info', 'Cuenta creada, pero no pudimos enviar el correo de verificación en este momento. Intenta iniciar sesión más tarde para reenviarlo.');
        }
        Router::redirect('/login');
    }

    public function verifyEmail(string $token): void
    {
        $token = trim($token);
        if ($token === '') {
            flash('error', 'El enlace de verificación no es válido.');
            Router::redirect('/login');
        }

        $business = Business::findByVerificationToken($token);
        if (!$business) {
            flash('error', 'El enlace de verificación ya no es válido o ya fue utilizado.');
            Router::redirect('/login');
        }

        if (Business::isEmailVerified($business)) {
            flash('success', 'Tu cuenta ya estaba verificada. Ya puedes iniciar sesión.');
            Router::redirect('/login');
        }

        Business::markEmailVerified((int) $business['id']);
        AutofacturaLog::log('email_verified', null, (int) $business['id'], 'Correo verificado correctamente.');
        flash('success', 'Tu cuenta fue verificada correctamente. Ahora ya puedes iniciar sesión.');
        Router::redirect('/login');
    }

    /**
     * Cerrar sesión
     */
    public function logout(): void
    {
        $businessId = auth_business_id();

        if ($businessId) {
            AutofacturaLog::log('logout', null, $businessId);
        }

        // Destruir sesión
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
        Router::redirect('/login');
    }

    private function sendVerificationEmail(array $business, bool $refreshToken = true): bool
    {
        $businessId = (int) ($business['id'] ?? 0);
        if ($businessId <= 0 || empty($business['email'])) {
            return false;
        }

        $token = $refreshToken
            ? Business::refreshEmailVerificationToken($businessId)
            : (string) ($business['email_verification_token'] ?? '');

        if ($token === '') {
            return false;
        }

        $verificationUrl = url('verify-account/' . $token);
        $result = MailgunService::sendEmailVerification(
            (string) $business['email'],
            (string) ($business['name'] ?? 'AutoFactura'),
            $verificationUrl
        );

        AutofacturaLog::log(
            !empty($result['success']) ? 'verification_email_sent' : 'verification_email_error',
            null,
            $businessId,
            $result['message'] ?? 'Sin respuesta'
        );

        return !empty($result['success']);
    }

    private function sendVerificationEmailIfAllowed(array $business): bool
    {
        if (!Business::canResendVerificationEmail($business, self::VERIFICATION_RESEND_COOLDOWN)) {
            return false;
        }

        return $this->sendVerificationEmail($business, true);
    }
}
