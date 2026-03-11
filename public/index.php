<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\controllers\AdminBillingController;
use App\controllers\AdminDashboardController;
use App\controllers\AdminMediaController;
use App\controllers\AdminMeController;
use App\controllers\AdminProfilesController;
use App\controllers\AdminSettingsController;
use App\controllers\AdminTitlesController;
use App\controllers\AdminUsersController;
use App\controllers\AuthController;
use App\controllers\PublicController;
use App\core\Router;

$router = new Router();

$router->get('/', [PublicController::class, 'home']);
$router->get('/register', [AuthController::class, 'registerEmailForm']);
$router->post('/register/send-code', [AuthController::class, 'registerSendCode']);
$router->get('/register/verify', [AuthController::class, 'registerVerifyForm']);
$router->post('/register/verify', [AuthController::class, 'registerVerifyCode']);
$router->post('/register/resend-code', [AuthController::class, 'registerResendCode']);
$router->get('/register/password', [AuthController::class, 'registerPasswordForm']);
$router->post('/register/password', [AuthController::class, 'registerSavePassword']);
$router->get('/login', [AuthController::class, 'loginForm']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);

$router->get('/onboarding', [PublicController::class, 'onboardingEntry']);
$router->get('/onboarding/plan', [PublicController::class, 'onboardingPlan']);
$router->post('/onboarding/plan/select', [PublicController::class, 'onboardingSelectPlan']);
$router->get('/onboarding/payment', [PublicController::class, 'onboardingPayment']);
$router->post('/onboarding/payment/submit', [PublicController::class, 'onboardingSubmitPayment']);

$router->get('/profiles', [PublicController::class, 'profiles']);
$router->post('/profiles/select', [PublicController::class, 'selectProfile']);
$router->post('/profiles/create', [PublicController::class, 'createProfile']);
$router->post('/profiles/update', [PublicController::class, 'updateProfile']);
$router->post('/profiles/delete', [PublicController::class, 'deleteProfile']);
$router->get('/browse', [PublicController::class, 'browse']);
$router->get('/account', [PublicController::class, 'account']);
$router->post('/account/plan/change', [PublicController::class, 'changePlan']);
$router->post('/account/payment-methods/add', [PublicController::class, 'addPaymentMethod']);
$router->post('/account/payment-methods/default', [PublicController::class, 'setDefaultPaymentMethod']);
$router->post('/account/payment-methods/delete', [PublicController::class, 'deletePaymentMethod']);
$router->post('/account/password/update', [PublicController::class, 'updatePassword']);
$router->post('/account/delete', [PublicController::class, 'deleteAccount']);
$router->post('/notifications/read-all', [PublicController::class, 'markNotificationsRead']);
$router->get('/title/{id}', [PublicController::class, 'title']);
$router->get('/title/{id}/preview', [PublicController::class, 'titlePreview']);
$router->get('/watch/{id}', [PublicController::class, 'watch']);
$router->post('/watch/progress', [PublicController::class, 'updateWatchProgress']);

$router->get('/admin/login', [AuthController::class, 'adminLoginForm']);
$router->post('/admin/login', [AuthController::class, 'adminLogin']);
$router->post('/admin/logout', [AuthController::class, 'logout']);

$router->get('/admin', [AdminDashboardController::class, 'index']);
$router->get('/admin/users', [AdminUsersController::class, 'index']);
$router->post('/admin/users/create', [AdminUsersController::class, 'create']);
$router->post('/admin/users/update', [AdminUsersController::class, 'update']);
$router->post('/admin/users/toggle', [AdminUsersController::class, 'toggleStatus']);
$router->post('/admin/users/delete', [AdminUsersController::class, 'delete']);
$router->post('/admin/users/bulk-delete', [AdminUsersController::class, 'bulkDelete']);

$router->get('/admin/profiles', [AdminProfilesController::class, 'index']);
$router->post('/admin/profiles/create', [AdminProfilesController::class, 'create']);
$router->post('/admin/profiles/update', [AdminProfilesController::class, 'update']);
$router->post('/admin/profiles/delete', [AdminProfilesController::class, 'delete']);

$router->get('/admin/titles', [AdminTitlesController::class, 'index']);
$router->post('/admin/titles/create', [AdminTitlesController::class, 'create']);
$router->post('/admin/titles/update', [AdminTitlesController::class, 'update']);
$router->post('/admin/titles/delete', [AdminTitlesController::class, 'delete']);

$router->get('/admin/media', [AdminMediaController::class, 'index']);
$router->post('/admin/media/upload-session/start', [AdminMediaController::class, 'startUploadSession']);
$router->post('/admin/media/upload-session/chunk-log', [AdminMediaController::class, 'logUploadChunk']);
$router->post('/admin/media/create', [AdminMediaController::class, 'create']);
$router->post('/admin/media/update', [AdminMediaController::class, 'update']);
$router->post('/admin/media/replace-source', [AdminMediaController::class, 'replaceSource']);
$router->post('/admin/media/delete', [AdminMediaController::class, 'delete']);

$router->get('/admin/billing', [AdminBillingController::class, 'index']);
$router->post('/admin/billing/plans/create', [AdminBillingController::class, 'createPlan']);
$router->post('/admin/billing/plans/update', [AdminBillingController::class, 'updatePlan']);
$router->post('/admin/billing/plans/delete', [AdminBillingController::class, 'deletePlan']);
$router->post('/admin/billing/subscriptions/assign', [AdminBillingController::class, 'assignSubscription']);

$router->get('/admin/settings', [AdminSettingsController::class, 'index']);
$router->post('/admin/settings/create', [AdminSettingsController::class, 'create']);
$router->post('/admin/settings/update', [AdminSettingsController::class, 'update']);
$router->post('/admin/settings/update-category', [AdminSettingsController::class, 'updateCategory']);
$router->post('/admin/settings/delete', [AdminSettingsController::class, 'delete']);
$router->post('/admin/settings/onedrive/save', [AdminSettingsController::class, 'saveOneDrive']);
$router->post('/admin/settings/smtp/save', [AdminSettingsController::class, 'saveSmtp']);
$router->post('/admin/settings/smtp/test', [AdminSettingsController::class, 'testSmtpConnection']);
$router->post('/admin/settings/configs/create', [AdminSettingsController::class, 'createProfileConfig']);
$router->post('/admin/settings/configs/update', [AdminSettingsController::class, 'updateProfileConfig']);
$router->post('/admin/settings/configs/default', [AdminSettingsController::class, 'setDefaultProfileConfig']);
$router->post('/admin/settings/configs/delete', [AdminSettingsController::class, 'deleteProfileConfig']);
$router->post('/admin/settings/configs/test', [AdminSettingsController::class, 'testProfileConfig']);
$router->get('/admin/settings/onedrive/connect', [AdminSettingsController::class, 'connectOneDrive']);
$router->get('/admin/settings/onedrive/callback', [AdminSettingsController::class, 'oneDriveCallback']);
$router->post('/admin/settings/onedrive/test', [AdminSettingsController::class, 'testOneDriveConnection']);
$router->post('/admin/settings/onedrive/quota', [AdminSettingsController::class, 'refreshOneDriveQuota']);
$router->get('/admin/me', [AdminMeController::class, 'index']);
$router->post('/admin/me/update', [AdminMeController::class, 'update']);

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method === 'HEAD') {
    $method = 'GET';
}
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$router->dispatch($method, $path);
