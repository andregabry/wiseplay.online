<?php

declare(strict_types=1);

namespace App\controllers;

use App\core\Auth;
use App\core\Response;
use App\core\Validator;
use App\core\View;
use App\models\AuditLog;
use App\models\BillingPlan;
use App\models\EmailVerificationCode;
use App\models\Profile;
use App\models\Setting;
use App\models\Subscription;
use App\models\User;
use App\services\EmailService;
use App\services\PaymentGatewayManager;

final class AuthController extends BaseController
{
    private const REG_EMAIL_KEY = 'registration_email';
    private const REG_NAME_KEY = 'registration_name';
    private const REG_VERIFIED_AT_KEY = 'registration_verified_at';

    public function loginForm(): void
    {
        if (Auth::check()) {
            Response::redirect('/profiles');
        }

        View::render('public/login', [
            'title' => 'Login | WisePlay',
            'currentPath' => current_path(),
        ], 'public');
    }

    public function login(): void
    {
        $this->requireCsrf();

        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $userModel = new User();

        try {
            $user = $this->attemptLogin($email, $password);
        } catch (\Throwable) {
            flash('error', 'Banco nao configurado. Verifique .env e import SQL.');
            Response::redirect('/login');
        }
        if ($user === null) {
            $existing = null;
            if (Validator::email($email)) {
                $existing = $userModel->findByEmail($email);
            }
            if ($existing && (((string) ($existing['email_verified_at'] ?? '')) === '' || ((string) ($existing['password_set_at'] ?? '')) === '')) {
                flash('error', 'Conta ainda nao verificada. Conclua o cadastro em "Criar conta".');
            } else {
                flash('error', 'Credenciais invalidas.');
            }
            Response::redirect('/login');
        }

        Auth::login($user);
        $userModel->updateLastLogin((int) $user['id']);
        (new Profile())->ensureDefaultAdultProfile((int) $user['id']);
        Response::redirect('/onboarding');
    }

    public function registerEmailForm(): void
    {
        if (Auth::check()) {
            Response::redirect('/profiles');
        }

        View::render('public/register_email', [
            'title' => 'Criar Conta | WisePlay',
            'currentPath' => current_path(),
            'prefillEmail' => (string) ($_SESSION[self::REG_EMAIL_KEY] ?? ''),
            'prefillName' => (string) ($_SESSION[self::REG_NAME_KEY] ?? ''),
        ], 'public');
    }

    public function registerSendCode(): void
    {
        $this->requireCsrf();
        if (Auth::check()) {
            Response::redirect('/profiles');
        }

        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $name = trim((string) ($_POST['name'] ?? ''));

        if (!Validator::email($email)) {
            flash('error', 'Informe um email valido para continuar.');
            Response::redirect('/register');
        }
        if ($name !== '' && !Validator::maxLength($name, 120)) {
            flash('error', 'Nome muito longo.');
            Response::redirect('/register');
        }

        $userModel = new User();
        $existing = $userModel->findByEmail($email);
        if ($existing && ((string) ($existing['email_verified_at'] ?? '')) !== '' && ((string) ($existing['password_set_at'] ?? '')) !== '') {
            flash('error', 'Este email ja possui cadastro. Use o login.');
            Response::redirect('/login');
        }

        $result = $this->dispatchVerificationCode($email, $name, false, isset($existing['id']) ? (int) $existing['id'] : null);
        if (($result['ok'] ?? false) !== true) {
            flash('error', (string) ($result['message'] ?? 'Nao foi possivel enviar o codigo.'));
            Response::redirect('/register');
        }

        $_SESSION[self::REG_EMAIL_KEY] = $email;
        $_SESSION[self::REG_NAME_KEY] = $name;
        unset($_SESSION[self::REG_VERIFIED_AT_KEY]);

        $this->auditRegistrationEvent('registration.code.sent', $email, [
            'resend' => false,
            'expires_minutes' => $this->verificationCodeTtlMinutes(),
        ]);

        flash('success', 'Enviamos um codigo de 6 digitos para seu email.');
        Response::redirect('/register/verify');
    }

    public function registerVerifyForm(): void
    {
        if (Auth::check()) {
            Response::redirect('/profiles');
        }

        $email = (string) ($_SESSION[self::REG_EMAIL_KEY] ?? '');
        if ($email === '') {
            flash('error', 'Informe seu email para iniciar o cadastro.');
            Response::redirect('/register');
        }

        $latest = (new EmailVerificationCode())->latestByEmail($email);
        $cooldownRemaining = 0;
        if ($latest && isset($latest['created_at'])) {
            $cooldownEndsAt = strtotime((string) $latest['created_at']) + $this->verificationResendCooldownSeconds();
            $cooldownRemaining = max(0, $cooldownEndsAt - time());
        }

        View::render('public/register_verify', [
            'title' => 'Verificar Email | WisePlay',
            'currentPath' => current_path(),
            'email' => $email,
            'name' => (string) ($_SESSION[self::REG_NAME_KEY] ?? ''),
            'cooldownRemaining' => $cooldownRemaining,
        ], 'public');
    }

    public function registerVerifyCode(): void
    {
        $this->requireCsrf();
        if (Auth::check()) {
            Response::redirect('/profiles');
        }

        $email = (string) ($_SESSION[self::REG_EMAIL_KEY] ?? '');
        if ($email === '') {
            flash('error', 'Sessao de cadastro expirada. Informe seu email novamente.');
            Response::redirect('/register');
        }

        $inputCode = preg_replace('/\D+/', '', (string) ($_POST['code'] ?? ''));
        if (!is_string($inputCode) || strlen($inputCode) !== 6) {
            flash('error', 'Informe o codigo com 6 digitos.');
            Response::redirect('/register/verify');
        }

        $codes = new EmailVerificationCode();
        $active = $codes->latestActiveByEmail($email);
        if (!$active) {
            flash('error', 'Nenhum codigo ativo encontrado. Solicite um novo codigo.');
            Response::redirect('/register/verify');
        }

        $expiresAt = strtotime((string) ($active['expires_at'] ?? ''));
        if ($expiresAt !== false && $expiresAt < time()) {
            $codes->consume((int) $active['id']);
            flash('error', 'Codigo expirado. Clique em reenviar codigo.');
            Response::redirect('/register/verify');
        }

        $attempts = (int) ($active['attempts_count'] ?? 0);
        $maxAttempts = max(1, (int) ($active['max_attempts'] ?? $this->verificationCodeMaxAttempts()));
        if ($attempts >= $maxAttempts) {
            $codes->consume((int) $active['id']);
            flash('error', 'Limite de tentativas excedido. Solicite um novo codigo.');
            Response::redirect('/register/verify');
        }

        $inputHash = $this->hashVerificationCode($inputCode);
        $storedHash = (string) ($active['code_hash'] ?? '');
        if (!hash_equals($storedHash, $inputHash)) {
            $codes->incrementAttempts((int) $active['id']);
            $updated = $codes->latestByEmail($email);
            $newAttempts = (int) ($updated['attempts_count'] ?? ($attempts + 1));
            if ($newAttempts >= $maxAttempts) {
                $codes->consume((int) $active['id']);
                flash('error', 'Codigo invalido. Limite de tentativas atingido, gere um novo codigo.');
            } else {
                flash('error', 'Codigo invalido. Tentativas restantes: ' . max(0, $maxAttempts - $newAttempts) . '.');
            }
            Response::redirect('/register/verify');
        }

        $codes->consume((int) $active['id']);
        $_SESSION[self::REG_VERIFIED_AT_KEY] = date('Y-m-d H:i:s');

        $this->auditRegistrationEvent('registration.code.verified', $email, [
            'attempts_used' => (int) ($active['attempts_count'] ?? 0),
        ]);

        flash('success', 'Email verificado. Agora defina sua senha.');
        Response::redirect('/register/password');
    }

    public function registerResendCode(): void
    {
        $this->requireCsrf();
        if (Auth::check()) {
            Response::redirect('/profiles');
        }

        $email = (string) ($_SESSION[self::REG_EMAIL_KEY] ?? '');
        if ($email === '') {
            flash('error', 'Sessao de cadastro expirada. Informe seu email novamente.');
            Response::redirect('/register');
        }

        $name = (string) ($_SESSION[self::REG_NAME_KEY] ?? '');
        $user = (new User())->findByEmail($email);
        $result = $this->dispatchVerificationCode($email, $name, true, isset($user['id']) ? (int) $user['id'] : null);
        if (($result['ok'] ?? false) !== true) {
            flash('error', (string) ($result['message'] ?? 'Nao foi possivel reenviar o codigo.'));
            Response::redirect('/register/verify');
        }

        unset($_SESSION[self::REG_VERIFIED_AT_KEY]);
        $this->auditRegistrationEvent('registration.code.resent', $email, [
            'expires_minutes' => $this->verificationCodeTtlMinutes(),
        ]);

        flash('success', 'Novo codigo enviado. Verifique sua caixa de entrada.');
        Response::redirect('/register/verify');
    }

    public function registerPasswordForm(): void
    {
        if (Auth::check()) {
            Response::redirect('/profiles');
        }

        $email = (string) ($_SESSION[self::REG_EMAIL_KEY] ?? '');
        if ($email === '') {
            flash('error', 'Sessao de cadastro expirada. Informe seu email novamente.');
            Response::redirect('/register');
        }

        if (!$this->isRegistrationCodeVerified()) {
            flash('error', 'Valide o codigo de email antes de criar sua senha.');
            Response::redirect('/register/verify');
        }

        View::render('public/register_password', [
            'title' => 'Criar Senha | WisePlay',
            'currentPath' => current_path(),
            'email' => $email,
            'name' => (string) ($_SESSION[self::REG_NAME_KEY] ?? ''),
            'passwordMinLength' => $this->passwordMinLength(),
        ], 'public');
    }

    public function registerSavePassword(): void
    {
        $this->requireCsrf();
        if (Auth::check()) {
            Response::redirect('/profiles');
        }

        $email = (string) ($_SESSION[self::REG_EMAIL_KEY] ?? '');
        if ($email === '') {
            flash('error', 'Sessao de cadastro expirada. Informe seu email novamente.');
            Response::redirect('/register');
        }
        if (!$this->isRegistrationCodeVerified()) {
            flash('error', 'Valide o codigo de email antes de criar sua senha.');
            Response::redirect('/register/verify');
        }

        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
        $minLength = $this->passwordMinLength();
        if (!Validator::minLength($password, $minLength)) {
            flash('error', 'Senha deve conter ao menos ' . $minLength . ' caracteres.');
            Response::redirect('/register/password');
        }
        if (!hash_equals($password, $passwordConfirm)) {
            flash('error', 'Confirmacao de senha nao confere.');
            Response::redirect('/register/password');
        }

        $userModel = new User();
        $planModel = new BillingPlan();
        $subModel = new Subscription();
        $profileModel = new Profile();
        $name = trim((string) ($_SESSION[self::REG_NAME_KEY] ?? ''));
        if ($name === '') {
            $name = 'Novo Usuario';
        }

        $existing = $userModel->findByEmail($email);
        if ($existing && ((string) ($existing['email_verified_at'] ?? '')) !== '' && ((string) ($existing['password_set_at'] ?? '')) !== '') {
            $this->clearRegistrationSession();
            flash('error', 'Este email ja possui cadastro. Use o login.');
            Response::redirect('/login');
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $verifiedAt = date('Y-m-d H:i:s');

        try {
            if ($existing) {
                $userId = (int) $existing['id'];
                $userModel->update($userId, [
                    'name' => $name !== '' ? $name : (string) ($existing['name'] ?? 'Novo Usuario'),
                    'email' => $email,
                    'account_avatar' => (string) ($existing['account_avatar'] ?? '/assets/img/avatar-library/avatar-01.svg'),
                    'role' => (string) ($existing['role'] ?? 'VIEWER'),
                    'status' => 'ACTIVE',
                    'password_hash' => $passwordHash,
                    'password_set_at' => $verifiedAt,
                    'email_verified_at' => $verifiedAt,
                    'onboarding_completed_at' => null,
                ]);
            } else {
                $userId = $userModel->create([
                    'name' => $name,
                    'email' => $email,
                    'password_hash' => $passwordHash,
                    'account_avatar' => '/assets/img/avatar-library/avatar-01.svg',
                    'role' => 'VIEWER',
                    'status' => 'ACTIVE',
                    'email_verified_at' => $verifiedAt,
                    'password_set_at' => $verifiedAt,
                    'onboarding_completed_at' => null,
                ]);
            }

            $plan = $planModel->findDefaultForRegistration();
            if (!$plan) {
                flash('error', 'Nenhum plano ativo disponivel para concluir cadastro.');
                Response::redirect('/register/password');
            }

            $requiresPayment = ((int) ($plan['requires_payment_method'] ?? 0) === 1);
            $gatewayProvider = strtolower((new PaymentGatewayManager())->gateway()->providerCode());
            $subModel->assignOrUpdate(
                (int) $userId,
                (int) $plan['id'],
                $requiresPayment ? 'PENDING' : 'ACTIVE',
                null,
                true,
                $requiresPayment,
                $requiresPayment ? 'PENDING' : 'NOT_REQUIRED',
                $requiresPayment ? $gatewayProvider : null,
                null
            );
            $profileModel->ensureDefaultAdultProfile((int) $userId);
        } catch (\Throwable) {
            flash('error', 'Nao foi possivel concluir o cadastro no momento.');
            Response::redirect('/register/password');
        }

        $this->auditRegistrationEvent('registration.password.set', $email, [
            'user_id' => (int) $userId,
            'plan_assigned' => (string) ($plan['slug'] ?? ''),
        ]);

        $this->clearRegistrationSession();
        flash('success', 'Cadastro concluido com sucesso. Agora faca login.');
        Response::redirect('/login');
    }

    public function adminLoginForm(): void
    {
        if (Auth::check() && Auth::isAdmin()) {
            Response::redirect('/admin');
        }

        View::render('admin/login', [
            'title' => 'Admin Login | WisePlay',
            'currentPath' => current_path(),
        ], 'admin_auth');
    }

    public function adminLogin(): void
    {
        $this->requireCsrf();

        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        try {
            $user = $this->attemptLogin($email, $password);
        } catch (\Throwable) {
            flash('error', 'Banco nao configurado. Verifique .env e import SQL.');
            Response::redirect('/admin/login');
        }
        if ($user === null) {
            flash('error', 'Credenciais invalidas.');
            Response::redirect('/admin/login');
        }

        Auth::login($user);

        if (!in_array($user['role'], (new User())->adminRoles(), true)) {
            flash('error', 'Usuario sem permissao administrativa.');
            Response::redirect('/admin/login');
        }

        (new User())->updateLastLogin((int) $user['id']);
        (new Profile())->ensureDefaultAdultProfile((int) $user['id']);
        Response::redirect('/admin');
    }

    public function logout(): void
    {
        $this->requireCsrf();
        Auth::logout();
        Response::redirect('/login');
    }

    private function attemptLogin(string $email, string $password): ?array
    {
        if (!Validator::email($email) || $password === '') {
            return null;
        }

        $user = (new User())->findByEmail($email);

        if (!$user || $user['status'] !== 'ACTIVE' || !password_verify($password, (string) $user['password_hash'])) {
            return null;
        }

        if (((string) ($user['email_verified_at'] ?? '')) === '' || ((string) ($user['password_set_at'] ?? '')) === '') {
            return null;
        }

        return $user;
    }

    private function dispatchVerificationCode(string $email, string $name, bool $isResend, ?int $userId): array
    {
        $codes = new EmailVerificationCode();
        $latest = $codes->latestByEmail($email);
        $cooldown = $this->verificationResendCooldownSeconds();
        if ($latest && isset($latest['created_at'])) {
            $createdAtTs = strtotime((string) $latest['created_at']);
            if ($createdAtTs !== false) {
                $nextAllowedAt = $createdAtTs + $cooldown;
                if ($nextAllowedAt > time()) {
                    $retryAfter = $nextAllowedAt - time();
                    return [
                        'ok' => false,
                        'message' => 'Aguarde ' . $retryAfter . 's para reenviar o codigo.',
                    ];
                }
            }
        }

        $ttlMinutes = $this->verificationCodeTtlMinutes();
        $maxAttempts = $this->verificationCodeMaxAttempts();
        $resendCount = $latest ? ((int) ($latest['resend_count'] ?? 0) + 1) : 0;

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $codeHash = $this->hashVerificationCode($code);
        $expiresAt = date('Y-m-d H:i:s', time() + ($ttlMinutes * 60));

        $codes->invalidateActiveByEmail($email);
        $recordId = $codes->createCode([
            'user_id' => $userId,
            'email' => $email,
            'code_hash' => $codeHash,
            'expires_at' => $expiresAt,
            'max_attempts' => $maxAttempts,
            'resend_count' => $resendCount,
        ]);

        $mail = new EmailService();
        $send = $mail->sendVerificationCode($email, $name, $code, $ttlMinutes);
        if (($send['ok'] ?? false) !== true) {
            $codes->consume($recordId);
            return [
                'ok' => false,
                'message' => (string) ($send['message'] ?? 'Falha ao enviar email de verificacao.'),
            ];
        }

        return ['ok' => true];
    }

    private function hashVerificationCode(string $code): string
    {
        $appKey = (string) config('app.key', 'wiseplay_default_key');
        return hash_hmac('sha256', $code, $appKey);
    }

    private function verificationCodeTtlMinutes(): int
    {
        return $this->settingInt('auth.verification_code_ttl_minutes', 10, 5, 30);
    }

    private function verificationCodeMaxAttempts(): int
    {
        return $this->settingInt('auth.verification_code_max_attempts', 5, 3, 10);
    }

    private function verificationResendCooldownSeconds(): int
    {
        return $this->settingInt('auth.verification_resend_cooldown_seconds', 60, 30, 300);
    }

    private function verificationSessionTtlMinutes(): int
    {
        return $this->settingInt('auth.verification_session_ttl_minutes', 30, 10, 180);
    }

    private function passwordMinLength(): int
    {
        return $this->settingInt('auth.password_min_length', 8, 6, 64);
    }

    private function settingInt(string $key, int $default, int $min, int $max): int
    {
        $value = (string) (new Setting())->value($key, (string) $default);
        if (!is_numeric($value)) {
            return $default;
        }

        $intValue = (int) $value;
        if ($intValue < $min) {
            return $min;
        }
        if ($intValue > $max) {
            return $max;
        }
        return $intValue;
    }

    private function isRegistrationCodeVerified(): bool
    {
        $verifiedAt = (string) ($_SESSION[self::REG_VERIFIED_AT_KEY] ?? '');
        if ($verifiedAt === '') {
            return false;
        }

        $verifiedTs = strtotime($verifiedAt);
        if ($verifiedTs === false) {
            return false;
        }

        if ((time() - $verifiedTs) > ($this->verificationSessionTtlMinutes() * 60)) {
            unset($_SESSION[self::REG_VERIFIED_AT_KEY]);
            return false;
        }

        return true;
    }

    private function clearRegistrationSession(): void
    {
        unset(
            $_SESSION[self::REG_EMAIL_KEY],
            $_SESSION[self::REG_NAME_KEY],
            $_SESSION[self::REG_VERIFIED_AT_KEY]
        );
    }

    private function auditRegistrationEvent(string $action, string $email, array $metadata = []): void
    {
        try {
            (new AuditLog())->log(
                null,
                $action,
                'registration',
                $email,
                $metadata
            );
        } catch (\Throwable) {
            // audit should never break registration flow
        }
    }
}
