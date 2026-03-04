<?php

declare(strict_types=1);

namespace App\controllers;

use App\core\Auth;
use App\core\Response;
use App\core\Validator;
use App\core\View;
use App\models\AuditLog;
use App\models\BillingPlan;
use App\models\Media;
use App\models\PaymentEvent;
use App\models\PaymentTransaction;
use App\models\Profile;
use App\models\Setting;
use App\models\Subscription;
use App\models\Title;
use App\models\WatchHistory;
use App\services\MediaSourceResolver;
use App\services\OnboardingService;
use App\services\AvatarLibraryService;
use App\services\PaymentGatewayManager;

final class PublicController extends BaseController
{
    public function home(): void
    {
        if (Auth::check()) {
            Response::redirect('/onboarding');
        }

        $titleModel = new Title();
        $planModel = new BillingPlan();

        $rows = $titleModel->rowsForBrowse(null);
        $wallItems = [];
        $seen = [];

        foreach ($rows as $items) {
            foreach ($items as $item) {
                $itemId = (int) ($item['id'] ?? 0);
                if ($itemId <= 0 || isset($seen[$itemId])) {
                    continue;
                }

                $image = trim((string) ($item['cover_image'] ?? ''));
                if ($image === '') {
                    $image = trim((string) ($item['backdrop_image'] ?? ''));
                }
                if ($image === '') {
                    continue;
                }

                $seen[$itemId] = true;
                $wallItems[] = [
                    'id' => $itemId,
                    'title' => (string) ($item['title_name'] ?? 'WisePlay'),
                    'image' => $image,
                ];

                if (count($wallItems) >= 36) {
                    break 2;
                }
            }
        }

        $plans = $planModel->listPublicPlans();
        $startingPlan = null;
        foreach ($plans as $plan) {
            if ((int) ($plan['active'] ?? 0) !== 1) {
                continue;
            }
            $startingPlan = $plan;
            break;
        }

        View::render('public/home', [
            'title' => 'WisePlay',
            'currentPath' => current_path(),
            'wallItems' => $wallItems,
            'startingPlan' => $startingPlan,
        ], 'public');
    }

    public function onboardingEntry(): void
    {
        Auth::requireLogin(false);
        $userId = (int) (Auth::user()['id'] ?? 0);
        $step = (new OnboardingService())->nextStep($userId);
        $this->redirectByOnboardingStep($step);
    }

    public function onboardingPlan(): void
    {
        Auth::requireLogin(false);
        $userId = (int) (Auth::user()['id'] ?? 0);
        $onboarding = new OnboardingService();
        $context = $onboarding->contextForUser($userId);

        if ((string) ($context['step'] ?? '') !== OnboardingService::STEP_PLAN) {
            $this->redirectByOnboardingStep((string) ($context['step'] ?? OnboardingService::STEP_PLAN));
        }

        View::render('public/onboarding_plan', [
            'title' => 'Onboarding | Escolha seu plano',
            'currentPath' => current_path(),
            'plans' => $onboarding->plansForSelection(),
            'gatewayEnabled' => (bool) ($context['gateway_enabled'] ?? false),
            'gatewayProvider' => (string) ($context['gateway_provider'] ?? 'MOCK'),
        ], 'public');
    }

    public function onboardingSelectPlan(): void
    {
        Auth::requireLogin(false);
        $this->requireCsrf();

        $userId = (int) (Auth::user()['id'] ?? 0);
        $planId = (int) ($_POST['plan_id'] ?? 0);

        if ($planId <= 0) {
            flash('error', 'Selecione um plano valido.');
            Response::redirect('/onboarding/plan');
        }

        $onboarding = new OnboardingService();
        $context = $onboarding->contextForUser($userId);
        $currentStep = (string) ($context['step'] ?? OnboardingService::STEP_PLAN);
        if (!in_array($currentStep, [OnboardingService::STEP_PLAN, OnboardingService::STEP_PAYMENT], true)) {
            $this->redirectByOnboardingStep($currentStep);
        }

        $plan = (new BillingPlan())->findById($planId);
        if (!$plan) {
            flash('error', 'Plano nao encontrado.');
            Response::redirect('/onboarding/plan');
        }

        if (!$onboarding->canSelectPlan($plan)) {
            flash('error', 'Este plano esta indisponivel no momento.');
            Response::redirect('/onboarding/plan');
        }

        $requiresPayment = ((int) ($plan['requires_payment_method'] ?? 0) === 1);
        $gatewayManager = new PaymentGatewayManager();
        $providerCode = strtolower($gatewayManager->gateway()->providerCode());

        $status = $requiresPayment ? 'PENDING' : 'ACTIVE';
        $paymentRequired = $requiresPayment;
        $paymentStatus = $requiresPayment ? 'PENDING' : 'NOT_REQUIRED';

        try {
            $subscriptionId = (new Subscription())->assignOrUpdate(
                $userId,
                $planId,
                $status,
                null,
                true,
                $paymentRequired,
                $paymentStatus,
                $requiresPayment ? $providerCode : null,
                null
            );
            if (!$requiresPayment) {
                (new Profile())->ensureDefaultAdultProfile($userId);
            }
        } catch (\Throwable) {
            flash('error', 'Nao foi possivel selecionar o plano agora.');
            Response::redirect('/onboarding/plan');
        }

        $this->auditPublic(
            'onboarding.plan.selected',
            'subscription',
            (string) $subscriptionId,
            [
                'plan_id' => $planId,
                'plan_slug' => (string) ($plan['slug'] ?? ''),
                'requires_payment' => $requiresPayment ? 1 : 0,
            ]
        );

        if ($requiresPayment) {
            flash('success', 'Plano selecionado. Finalize o pagamento para ativar o acesso.');
            Response::redirect('/onboarding/payment');
        }

        flash('success', 'Plano selecionado com sucesso.');
        Response::redirect('/profiles?manage=1');
    }

    public function onboardingPayment(): void
    {
        Auth::requireLogin(false);
        $userId = (int) (Auth::user()['id'] ?? 0);

        $onboarding = new OnboardingService();
        $context = $onboarding->contextForUser($userId);
        if ((string) ($context['step'] ?? '') !== OnboardingService::STEP_PAYMENT) {
            $this->redirectByOnboardingStep((string) ($context['step'] ?? OnboardingService::STEP_PLAN));
        }

        $subscription = $context['subscription'] ?? null;
        if (!$subscription || (int) ($subscription['requires_payment_method'] ?? 0) !== 1) {
            flash('error', 'Nao ha pagamento pendente para esta conta.');
            Response::redirect('/onboarding');
        }

        View::render('public/onboarding_payment', [
            'title' => 'Onboarding | Pagamento',
            'currentPath' => current_path(),
            'subscription' => $subscription,
            'gatewayEnabled' => (bool) ($context['gateway_enabled'] ?? false),
            'gatewayProvider' => (string) ($context['gateway_provider'] ?? 'MOCK'),
        ], 'public');
    }

    public function onboardingSubmitPayment(): void
    {
        Auth::requireLogin(false);
        $this->requireCsrf();

        $userId = (int) (Auth::user()['id'] ?? 0);
        $onboarding = new OnboardingService();
        $context = $onboarding->contextForUser($userId);
        if ((string) ($context['step'] ?? '') !== OnboardingService::STEP_PAYMENT) {
            $this->redirectByOnboardingStep((string) ($context['step'] ?? OnboardingService::STEP_PLAN));
        }

        $subscription = $context['subscription'] ?? null;
        if (!$subscription) {
            flash('error', 'Assinatura nao encontrada para pagamento.');
            Response::redirect('/onboarding/plan');
        }

        $gatewayManager = new PaymentGatewayManager();
        if (!$gatewayManager->isGatewayEnabled()) {
            flash('error', 'Pagamento indisponivel no momento. Tente novamente mais tarde.');
            Response::redirect('/onboarding/payment');
        }

        $holderName = trim((string) ($_POST['card_holder'] ?? ''));
        $cardNumberRaw = preg_replace('/\D+/', '', (string) ($_POST['card_number'] ?? ''));
        $expMonth = (int) ($_POST['exp_month'] ?? 0);
        $expYearRaw = trim((string) ($_POST['exp_year'] ?? ''));
        $cvvRaw = preg_replace('/\D+/', '', (string) ($_POST['cvv'] ?? ''));

        $errors = [];
        if (!Validator::required($holderName) || !Validator::minLength($holderName, 2) || !Validator::maxLength($holderName, 120)) {
            $errors[] = 'Nome no cartao invalido.';
        }
        if (!is_string($cardNumberRaw) || strlen($cardNumberRaw) < 13 || strlen($cardNumberRaw) > 19) {
            $errors[] = 'Numero de cartao invalido.';
        }
        if ($expMonth < 1 || $expMonth > 12) {
            $errors[] = 'Mes de expiracao invalido.';
        }

        $expYear = (int) $expYearRaw;
        if (strlen($expYearRaw) === 2) {
            $expYear += 2000;
        }
        $currentYear = (int) date('Y');
        if ($expYear < $currentYear || $expYear > ($currentYear + 20)) {
            $errors[] = 'Ano de expiracao invalido.';
        }

        if (!is_string($cvvRaw) || strlen($cvvRaw) < 3 || strlen($cvvRaw) > 4) {
            $errors[] = 'CVV invalido.';
        }

        if ($errors !== []) {
            flash('error', implode(' ', $errors));
            Response::redirect('/onboarding/payment');
        }

        $provider = $gatewayManager->gateway();
        $planPrice = (float) ($subscription['price'] ?? 0);
        $planCurrency = (string) ($subscription['currency'] ?? 'BRL');
        $last4 = substr((string) $cardNumberRaw, -4);

        $transactionModel = new PaymentTransaction();
        $eventModel = new PaymentEvent();
        $transactionId = $transactionModel->create([
            'user_id' => $userId,
            'subscription_id' => (int) ($subscription['id'] ?? 0),
            'plan_id' => (int) ($subscription['plan_id'] ?? 0),
            'provider' => $provider->providerCode(),
            'status' => 'PENDING',
            'amount' => $planPrice,
            'currency' => $planCurrency,
            'metadata_json' => json_encode([
                'holder_name' => $holderName,
                'card_last4' => $last4,
                'exp_month' => $expMonth,
                'exp_year' => $expYear,
                'flow' => 'onboarding_first_payment',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $eventModel->create($transactionId, 'payment.charge.requested', $provider->providerCode(), json_encode([
            'amount' => $planPrice,
            'currency' => $planCurrency,
            'subscription_id' => (int) ($subscription['id'] ?? 0),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $charge = $provider->createCharge([
            'amount' => $planPrice,
            'currency' => $planCurrency,
            'description' => 'WisePlay onboarding subscription',
            'metadata' => [
                'subscription_id' => (int) ($subscription['id'] ?? 0),
                'user_id' => $userId,
                'plan_slug' => (string) ($subscription['plan_slug'] ?? ''),
            ],
            'card' => [
                'holder_name' => $holderName,
                'last4' => $last4,
                'exp_month' => $expMonth,
                'exp_year' => $expYear,
            ],
        ]);

        $chargeStatus = strtoupper((string) ($charge['status'] ?? 'FAILED'));
        $providerTxnId = (string) ($charge['provider_txn_id'] ?? '');
        $chargeMetadata = json_encode($charge['metadata'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (($charge['ok'] ?? false) !== true || $chargeStatus === 'FAILED') {
            $transactionModel->updateStatus($transactionId, 'FAILED', $providerTxnId !== '' ? $providerTxnId : null, $chargeMetadata ?: null);
            (new Subscription())->markPaymentState(
                (int) ($subscription['id'] ?? 0),
                'PAYMENT_FAILED',
                'FAILED',
                $provider->providerCode(),
                $providerTxnId !== '' ? $providerTxnId : null
            );

            $eventModel->create($transactionId, 'payment.charge.failed', $provider->providerCode(), json_encode([
                'message' => (string) ($charge['message'] ?? 'Falha ao processar pagamento.'),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            $this->auditPublic('onboarding.payment.failed', 'payment_transaction', (string) $transactionId, [
                'subscription_id' => (int) ($subscription['id'] ?? 0),
                'provider' => $provider->providerCode(),
            ]);

            flash('error', (string) ($charge['message'] ?? 'Pagamento recusado. Tente novamente.'));
            Response::redirect('/onboarding/payment');
        }

        if ($chargeStatus === 'PAID') {
            $transactionModel->updateStatus($transactionId, 'PAID', $providerTxnId !== '' ? $providerTxnId : null, $chargeMetadata ?: null);
            (new Subscription())->markPaymentState(
                (int) ($subscription['id'] ?? 0),
                'ACTIVE',
                'PAID',
                $provider->providerCode(),
                $providerTxnId !== '' ? $providerTxnId : null
            );

            $eventModel->create($transactionId, 'payment.charge.succeeded', $provider->providerCode(), json_encode([
                'provider_txn_id' => $providerTxnId,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            (new Profile())->ensureDefaultAdultProfile($userId);

            $this->auditPublic('onboarding.payment.completed', 'payment_transaction', (string) $transactionId, [
                'subscription_id' => (int) ($subscription['id'] ?? 0),
                'provider' => $provider->providerCode(),
            ]);

            flash('success', 'Pagamento aprovado. Agora personalize seus perfis.');
            Response::redirect('/profiles?manage=1');
        }

        $transactionModel->updateStatus($transactionId, 'PENDING', $providerTxnId !== '' ? $providerTxnId : null, $chargeMetadata ?: null);
        (new Subscription())->markPaymentState(
            (int) ($subscription['id'] ?? 0),
            'PENDING',
            'PENDING',
            $provider->providerCode(),
            $providerTxnId !== '' ? $providerTxnId : null
        );

        $eventModel->create($transactionId, 'payment.charge.pending', $provider->providerCode(), json_encode([
            'provider_txn_id' => $providerTxnId,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->auditPublic('onboarding.payment.pending', 'payment_transaction', (string) $transactionId, [
            'subscription_id' => (int) ($subscription['id'] ?? 0),
            'provider' => $provider->providerCode(),
        ]);

        flash('warning', 'Pagamento pendente. Aguarde confirmacao para liberar o acesso.');
        Response::redirect('/onboarding/payment');
    }

    public function profiles(): void
    {
        Auth::requireLogin(false);
        $context = $this->ensureOnboardingAllowed([OnboardingService::STEP_PROFILES, OnboardingService::STEP_COMPLETE]);
        $userId = (int) (Auth::user()['id'] ?? 0);

        $profileModel = new Profile();
        $profileModel->ensureDefaultAdultProfile($userId);
        $profiles = $profileModel->findByUser($userId);
        $plan = $profileModel->planByUser($userId);
        $planLimit = (int) ($plan['profile_limit'] ?? 0);
        $currentCount = count($profiles);
        $manageMode = ((string) ($_GET['manage'] ?? '') === '1');
        if ((string) ($context['step'] ?? '') === OnboardingService::STEP_PROFILES) {
            $manageMode = true;
        }

        $avatarLibrary = new AvatarLibraryService();
        $avatarOptions = $avatarLibrary->profileAvatarOptions();
        $avatarSections = $avatarLibrary->profileAvatarSections();
        $avatarFallback = $avatarLibrary->defaultProfileAvatar();

        View::render('public/profiles', [
            'title' => 'Perfis | WisePlay',
            'currentPath' => current_path(),
            'profiles' => $profiles,
            'selectedProfileId' => (int) ($_SESSION['profile_id'] ?? 0),
            'manageMode' => $manageMode,
            'plan' => $plan,
            'planLimit' => $planLimit,
            'currentCount' => $currentCount,
            'canCreateProfile' => ($planLimit > 0 && $currentCount < $planLimit),
            'avatarOptions' => $avatarOptions,
            'avatarSections' => $avatarSections,
            'avatarFallback' => $avatarFallback,
            'onboardingNeedsProfile' => ((string) ($context['step'] ?? '') === OnboardingService::STEP_PROFILES),
        ], 'public');
    }

    public function selectProfile(): void
    {
        Auth::requireLogin(false);
        $this->requireCsrf();
        $this->ensureOnboardingAllowed([OnboardingService::STEP_PROFILES, OnboardingService::STEP_COMPLETE]);

        $profileId = (int) ($_POST['profile_id'] ?? 0);
        if ($profileId <= 0) {
            flash('error', 'Selecione um perfil valido.');
            Response::redirect('/profiles');
        }

        $profile = (new Profile())->findById($profileId);
        $userId = (int) (Auth::user()['id'] ?? 0);

        if (!$profile || (int) $profile['user_id'] !== $userId) {
            flash('error', 'Perfil invalido para este usuario.');
            Response::redirect('/profiles');
        }

        $_SESSION['profile_id'] = $profileId;
        Response::redirect('/browse');
    }

    public function createProfile(): void
    {
        Auth::requireLogin(false);
        $this->requireCsrf();
        $this->ensureOnboardingAllowed([OnboardingService::STEP_PROFILES, OnboardingService::STEP_COMPLETE]);

        $userId = (int) (Auth::user()['id'] ?? 0);
        $profileName = trim((string) ($_POST['profile_name'] ?? ''));
        $avatarPath = trim((string) ($_POST['avatar_path'] ?? ''));
        $isKids = (int) ($_POST['is_kids'] ?? 0) === 1 ? 1 : 0;

        $model = new Profile();
        $planLimit = $model->profileLimitByUser($userId);
        $currentCount = $model->countByUser($userId);

        if ($planLimit <= 0) {
            flash('error', 'Conta sem plano ativo. Selecione um plano para criar perfis.');
            Response::redirect('/profiles?manage=1');
        }

        if ($currentCount >= $planLimit) {
            flash('error', 'Voce atingiu o limite de perfis do seu plano (' . $planLimit . ').');
            Response::redirect('/profiles?manage=1');
        }

        if (!Validator::required($profileName) || !Validator::minLength($profileName, 2)) {
            flash('error', 'Informe um nome valido para o perfil.');
            Response::redirect('/profiles?manage=1');
        }

        if ($avatarPath !== '' && !Validator::url($avatarPath) && !str_starts_with($avatarPath, '/')) {
            flash('error', 'Avatar invalido.');
            Response::redirect('/profiles?manage=1');
        }

        if ($avatarPath === '') {
            $avatarPath = (new AvatarLibraryService())->defaultProfileAvatar();
        }

        try {
            $profileId = $model->create([
                'user_id' => $userId,
                'profile_name' => $profileName,
                'avatar_path' => $avatarPath,
                'is_kids' => $isKids,
                'maturity_rating' => $isKids === 1 ? '12' : '18',
                'language' => 'pt-BR',
                'autoplay' => 1,
            ]);
        } catch (\Throwable) {
            flash('error', 'Nao foi possivel criar o perfil.');
            Response::redirect('/profiles?manage=1');
        }

        $this->auditPublic('profile.create', 'profile', (string) $profileId, ['is_kids' => $isKids]);
        flash('success', 'Perfil criado com sucesso.');
        Response::redirect('/profiles?manage=1');
    }

    public function updateProfile(): void
    {
        Auth::requireLogin(false);
        $this->requireCsrf();
        $this->ensureOnboardingAllowed([OnboardingService::STEP_PROFILES, OnboardingService::STEP_COMPLETE]);

        $userId = (int) (Auth::user()['id'] ?? 0);
        $profileId = (int) ($_POST['profile_id'] ?? 0);

        if ($profileId <= 0) {
            flash('error', 'Perfil invalido.');
            Response::redirect('/profiles?manage=1');
        }

        $model = new Profile();
        $profile = $model->findById($profileId);
        if (!$profile || (int) $profile['user_id'] !== $userId) {
            flash('error', 'Perfil nao encontrado para esta conta.');
            Response::redirect('/profiles?manage=1');
        }

        $profileName = trim((string) ($_POST['profile_name'] ?? ''));
        $avatarPath = trim((string) ($_POST['avatar_path'] ?? ''));
        $isKids = (int) ($_POST['is_kids'] ?? 0) === 1 ? 1 : 0;

        if (!Validator::required($profileName) || !Validator::minLength($profileName, 2)) {
            flash('error', 'Informe um nome valido para o perfil.');
            Response::redirect('/profiles?manage=1');
        }

        if ($avatarPath !== '' && !Validator::url($avatarPath) && !str_starts_with($avatarPath, '/')) {
            flash('error', 'Avatar invalido.');
            Response::redirect('/profiles?manage=1');
        }

        if ($avatarPath === '') {
            $avatarPath = (string) ($profile['avatar_path'] ?: (new AvatarLibraryService())->defaultProfileAvatar());
        }

        try {
            $model->update($profileId, [
                'user_id' => $userId,
                'profile_name' => $profileName,
                'avatar_path' => $avatarPath,
                'is_kids' => $isKids,
                'maturity_rating' => $isKids === 1 ? '12' : '18',
                'language' => (string) ($profile['language'] ?? 'pt-BR'),
                'autoplay' => (int) ($profile['autoplay'] ?? 1),
            ]);
        } catch (\Throwable) {
            flash('error', 'Nao foi possivel atualizar o perfil.');
            Response::redirect('/profiles?manage=1');
        }

        $this->auditPublic('profile.update', 'profile', (string) $profileId, ['is_kids' => $isKids]);
        flash('success', 'Perfil atualizado com sucesso.');
        Response::redirect('/profiles?manage=1');
    }

    public function deleteProfile(): void
    {
        Auth::requireLogin(false);
        $this->requireCsrf();
        $this->ensureOnboardingAllowed([OnboardingService::STEP_PROFILES, OnboardingService::STEP_COMPLETE]);

        $userId = (int) (Auth::user()['id'] ?? 0);
        $profileId = (int) ($_POST['profile_id'] ?? 0);

        if ($profileId <= 0) {
            flash('error', 'Perfil invalido.');
            Response::redirect('/profiles?manage=1');
        }

        $model = new Profile();
        $profile = $model->findById($profileId);
        if (!$profile || (int) $profile['user_id'] !== $userId) {
            flash('error', 'Perfil nao encontrado para esta conta.');
            Response::redirect('/profiles?manage=1');
        }

        $count = $model->countByUser($userId);
        if ($count <= 1) {
            flash('error', 'Sua conta precisa manter ao menos 1 perfil.');
            Response::redirect('/profiles?manage=1');
        }

        try {
            $model->delete($profileId);
        } catch (\Throwable) {
            flash('error', 'Nao foi possivel remover o perfil.');
            Response::redirect('/profiles?manage=1');
        }

        if ((int) ($_SESSION['profile_id'] ?? 0) === $profileId) {
            unset($_SESSION['profile_id']);
        }

        $this->auditPublic('profile.delete', 'profile', (string) $profileId, null);
        flash('success', 'Perfil removido com sucesso.');
        Response::redirect('/profiles?manage=1');
    }

    public function browse(): void
    {
        Auth::requireLogin(false);
        $this->ensureOnboardingAllowed([OnboardingService::STEP_COMPLETE]);
        $profile = $this->requireSelectedProfile();

        $kidsMode = ((int) ($profile['is_kids'] ?? 0) === 1);

        $titleModel = new Title();
        $mediaModel = new Media();
        $featured = $titleModel->featuredForBrowse($kidsMode);
        $rows = $titleModel->rowsForBrowse($kidsMode);
        $continueWatching = (new WatchHistory())->recentByProfile((int) $profile['id'], 12);
        $subscription = (new Subscription())->findActiveByUser((int) (Auth::user()['id'] ?? 0));
        $appTagline = (new Setting())->value('app.home_tagline', 'Seu streaming inteligente para filmes, series e cursos.');

        if ($kidsMode) {
            $continueWatching = array_values(array_filter($continueWatching, function (array $item): bool {
                return $this->isKidsAllowed((string) ($item['maturity_rating'] ?? '18'));
            }));
        }

        $titleIds = [];
        if ($featured) {
            $titleIds[] = (int) $featured['id'];
        }
        foreach ($rows as $rowItems) {
            foreach ($rowItems as $rowItem) {
                $titleIds[] = (int) ($rowItem['id'] ?? 0);
            }
        }
        $firstPlayableByTitle = $mediaModel->firstPlayableByTitleIds($titleIds);
        $mediaHierarchyByTitle = $mediaModel->hierarchyForTitleIds($titleIds, true);
        $browseMetaByTitle = $this->buildBrowseMetaByTitle($featured, $rows, $firstPlayableByTitle, $mediaHierarchyByTitle);
        $featuredHeroTrailer = null;
        if ($featured) {
            $featuredPlayable = $firstPlayableByTitle[(int) $featured['id']] ?? null;
            $featuredHeroTrailer = $this->resolveHeroPreview($featuredPlayable);
        }

        View::render('public/browse', [
            'title' => 'Browse | WisePlay',
            'currentPath' => current_path(),
            'profile' => $profile,
            'availableProfiles' => (new Profile())->findByUser((int) (Auth::user()['id'] ?? 0)),
            'selectedProfileId' => (int) ($_SESSION['profile_id'] ?? 0),
            'featured' => $featured,
            'rows' => $rows,
            'continueWatching' => $continueWatching,
            'subscription' => $subscription,
            'firstPlayableByTitle' => $firstPlayableByTitle,
            'browseMetaByTitle' => $browseMetaByTitle,
            'featuredHeroTrailer' => $featuredHeroTrailer,
            'appTagline' => $appTagline,
        ], 'public');
    }

    public function title(array $params): void
    {
        Auth::requireLogin(false);
        $this->ensureOnboardingAllowed([OnboardingService::STEP_COMPLETE]);
        $profile = $this->requireSelectedProfile();

        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            Response::redirect('/browse');
        }

        $title = (new Title())->findPublishedById($id);
        if (!$title) {
            flash('error', 'Titulo nao encontrado.');
            Response::redirect('/browse');
        }

        if ((int) $profile['is_kids'] === 1 && !$this->isKidsAllowed((string) $title['maturity_rating'])) {
            flash('error', 'Este titulo nao esta disponivel no perfil infantil.');
            Response::redirect('/browse');
        }

        $mediaHierarchy = (new Media())->hierarchyForTitle((int) $title['id'], true);
        $firstPlayable = (new Media())->firstPlayableByTitle((int) $title['id']);
        $seasonTrailers = $this->buildSeasonTrailers($mediaHierarchy, (string) ($title['type'] ?? 'MOVIE'));
        $heroTrailer = $this->resolveHeroPreview($firstPlayable);

        View::render('public/title', [
            'title' => (string) $title['title_name'] . ' | WisePlay',
            'currentPath' => current_path(),
            'profile' => $profile,
            'availableProfiles' => (new Profile())->findByUser((int) (Auth::user()['id'] ?? 0)),
            'selectedProfileId' => (int) ($_SESSION['profile_id'] ?? 0),
            'titleItem' => $title,
            'mediaHierarchy' => $mediaHierarchy,
            'firstPlayable' => $firstPlayable,
            'seasonTrailers' => $seasonTrailers,
            'heroTrailer' => $heroTrailer,
        ], 'public');
    }

    public function watch(array $params): void
    {
        Auth::requireLogin(false);
        $this->ensureOnboardingAllowed([OnboardingService::STEP_COMPLETE]);
        $profile = $this->requireSelectedProfile();

        $mediaId = (int) ($params['id'] ?? 0);
        if ($mediaId <= 0) {
            Response::redirect('/browse');
        }

        $media = (new Media())->findPlayableById($mediaId);
        if (!$media) {
            flash('error', 'Midia nao encontrada ou indisponivel.');
            Response::redirect('/browse');
        }

        if ((int) $profile['is_kids'] === 1 && !$this->isKidsAllowed((string) $media['maturity_rating'])) {
            flash('error', 'Este conteudo nao esta disponivel no perfil infantil.');
            Response::redirect('/browse');
        }

        $resolved = (new MediaSourceResolver())->resolve($media);
        $media['playback_url'] = (string) ($resolved['url'] ?? '');
        $media['playback_message'] = (string) ($resolved['message'] ?? '');
        $media['playback_from_cache'] = (bool) ($resolved['from_cache'] ?? false);
        $media['playback_mime_type'] = (string) ($resolved['mime_type'] ?? ($media['mime_type'] ?? ''));

        $suggestions = (new Media())->suggestionsForTitle((int) $media['title_id'], $mediaId, 8);

        View::render('public/watch', [
            'title' => 'Assistir | WisePlay',
            'currentPath' => current_path(),
            'profile' => $profile,
            'availableProfiles' => (new Profile())->findByUser((int) (Auth::user()['id'] ?? 0)),
            'selectedProfileId' => (int) ($_SESSION['profile_id'] ?? 0),
            'mediaItem' => $media,
            'suggestions' => $suggestions,
        ], 'public');
    }

    public function updateWatchProgress(): void
    {
        Auth::requireLogin(false);
        $this->requireCsrf();
        $this->ensureOnboardingAllowed([OnboardingService::STEP_COMPLETE]);

        $profile = $this->requireSelectedProfile(false);
        if (!$profile) {
            Response::json(['ok' => false, 'message' => 'Perfil nao selecionado.'], 422);
        }

        $mediaId = (int) ($_POST['media_id'] ?? 0);
        $progressPercent = (int) ($_POST['progress_percent'] ?? 0);
        $lastPosition = (int) ($_POST['last_position_seconds'] ?? 0);

        if ($mediaId <= 0) {
            Response::json(['ok' => false, 'message' => 'Midia invalida.'], 422);
        }

        $media = (new Media())->findPlayableById($mediaId);
        if (!$media) {
            Response::json(['ok' => false, 'message' => 'Midia nao encontrada.'], 404);
        }

        try {
            (new WatchHistory())->upsertProgress(
                (int) $profile['id'],
                (int) $media['title_id'],
                $mediaId,
                $progressPercent,
                $lastPosition
            );
        } catch (\Throwable) {
            Response::json(['ok' => false, 'message' => 'Falha ao salvar progresso.'], 500);
        }

        Response::json(['ok' => true, 'message' => 'Progresso atualizado.']);
    }

    private function requireSelectedProfile(bool $redirectOnMissing = true): ?array
    {
        $userId = (int) (Auth::user()['id'] ?? 0);
        $profileId = (int) ($_SESSION['profile_id'] ?? 0);

        if ($profileId <= 0) {
            if ($redirectOnMissing) {
                flash('error', 'Selecione um perfil antes de continuar.');
                Response::redirect('/profiles');
            }
            return null;
        }

        $profile = (new Profile())->findById($profileId);
        if (!$profile || (int) $profile['user_id'] !== $userId) {
            unset($_SESSION['profile_id']);
            if ($redirectOnMissing) {
                flash('error', 'Perfil invalido. Selecione novamente.');
                Response::redirect('/profiles');
            }
            return null;
        }

        return $profile;
    }

    private function ensureOnboardingAllowed(array $allowedSteps): array
    {
        $userId = (int) (Auth::user()['id'] ?? 0);
        $context = (new OnboardingService())->contextForUser($userId);
        $step = (string) ($context['step'] ?? OnboardingService::STEP_PLAN);

        if (!in_array($step, $allowedSteps, true)) {
            $this->redirectByOnboardingStep($step);
        }

        return $context;
    }

    private function buildSeasonTrailers(array $mediaHierarchy, string $titleType): array
    {
        if ($titleType === 'MOVIE') {
            return [];
        }

        $resolver = new MediaSourceResolver();
        $trailers = [];

        foreach ($mediaHierarchy as $seasonNumber => $episodes) {
            if (!is_array($episodes) || $episodes === []) {
                continue;
            }

            $firstEpisode = $episodes[0];
            $resolved = $resolver->resolve($firstEpisode);
            $url = trim((string) ($resolved['url'] ?? ''));
            if ($url === '') {
                continue;
            }

            $mimeType = trim((string) (($resolved['mime_type'] ?? '') !== '' ? $resolved['mime_type'] : ($firstEpisode['mime_type'] ?? '')));
            $mimeTypeLower = strtolower($mimeType);
            $isTsSource = (
                preg_match('/\.ts(\?.*)?$/i', $url) === 1
                || str_contains($mimeTypeLower, 'mp2t')
                || str_contains($mimeTypeLower, 'mpegts')
                || str_contains($mimeTypeLower, 'mpeg-tts')
                || (str_contains($mimeTypeLower, 'mpeg') && str_contains($mimeTypeLower, 'ts'))
            );

            $trailers[(int) $seasonNumber] = [
                'media_id' => (int) ($firstEpisode['id'] ?? 0),
                'item_label' => (string) ($firstEpisode['item_label'] ?? ''),
                'episode_number' => (int) ($firstEpisode['episode_number'] ?? 1),
                'duration_minutes' => (int) ($firstEpisode['duration_minutes'] ?? 0),
                'url' => $url,
                'mime_type' => $mimeType,
                'source_is_ts' => $isTsSource ? 1 : 0,
                'poster' => (string) (($firstEpisode['thumbnail_url'] ?? '') !== '' ? $firstEpisode['thumbnail_url'] : ($firstEpisode['backdrop_url'] ?? '')),
            ];
        }

        return $trailers;
    }

    private function buildBrowseMetaByTitle(?array $featured, array $rows, array $firstPlayableByTitle, array $mediaHierarchyByTitle): array
    {
        $meta = [];
        $append = function (array $title) use (&$meta, $firstPlayableByTitle, $mediaHierarchyByTitle): void {
            $titleId = (int) ($title['id'] ?? 0);
            if ($titleId <= 0 || isset($meta[$titleId])) {
                return;
            }

            $playable = $firstPlayableByTitle[$titleId] ?? null;
            $preview = is_array($playable) ? $this->buildPreviewFromPlayable($playable) : null;
            $titleType = (string) ($title['type'] ?? 'TITLE');
            $genre = (string) ($title['genre'] ?? 'General');
            $hierarchy = $mediaHierarchyByTitle[$titleId] ?? [];
            $seasonCount = count($hierarchy);
            $episodeCount = 0;
            $seasons = [];

            foreach ($hierarchy as $seasonNumber => $episodes) {
                $seasonItems = [];
                foreach ($episodes as $episode) {
                    $episodeCount++;
                    $thumbnail = trim((string) (($episode['thumbnail_url'] ?? '') !== '' ? $episode['thumbnail_url'] : (($episode['backdrop_url'] ?? '') !== '' ? $episode['backdrop_url'] : (($title['backdrop_image'] ?? '') !== '' ? $title['backdrop_image'] : ($title['cover_image'] ?? '')))));

                    $seasonItems[] = [
                        'id' => (int) ($episode['id'] ?? 0),
                        'number' => (int) ($episode['episode_number'] ?? 1),
                        'label' => (string) ($episode['item_label'] ?? ''),
                        'duration_label' => $this->formatMediaDuration((int) ($episode['duration_minutes'] ?? 0)),
                        'thumbnail' => $thumbnail,
                        'watch_url' => '/watch/' . (int) ($episode['id'] ?? 0),
                    ];
                }

                $seasons[] = [
                    'number' => (int) $seasonNumber,
                    'label' => $this->seasonDisplayLabel($titleType, (int) $seasonNumber, $seasonCount),
                    'items' => $seasonItems,
                ];
            }

            $runtimeLabel = $this->browseRuntimeLabel($titleType, $playable, $seasonCount, $episodeCount);
            $facts = [
                ['label' => 'Genres', 'value' => $genre],
                ['label' => 'Format', 'value' => $this->browseTypeLabel($titleType)],
            ];
            if ($runtimeLabel !== '') {
                $facts[] = ['label' => 'Watch time', 'value' => $runtimeLabel];
            }

            $meta[$titleId] = [
                'id' => $titleId,
                'title_name' => (string) ($title['title_name'] ?? ''),
                'description' => (string) ($title['description'] ?? ''),
                'type' => $titleType,
                'type_label' => $this->browseTypeLabel($titleType),
                'genre' => $genre,
                'year' => (string) (($title['year'] ?? '') !== '' ? $title['year'] : '-'),
                'maturity_rating' => (string) ($title['maturity_rating'] ?? '16'),
                'cover_image' => (string) ($title['cover_image'] ?? ''),
                'backdrop_image' => (string) ($title['backdrop_image'] ?? ''),
                'watch_media_id' => is_array($playable) ? (int) ($playable['id'] ?? 0) : 0,
                'preview_url' => is_array($preview) ? (string) ($preview['url'] ?? '') : '',
                'preview_mime' => is_array($preview) ? (string) ($preview['mime_type'] ?? '') : '',
                'hover_badges' => array_values(array_filter([
                    (string) ($title['maturity_rating'] ?? '16+'),
                    $runtimeLabel,
                    'HD',
                ])),
                'tags' => $this->browseGenreTags($genre),
                'season_label' => $seasonCount > 0 ? (string) ($seasons[0]['label'] ?? '') : '',
                'season_count' => $seasonCount,
                'episode_count' => $episodeCount,
                'facts' => $facts,
                'seasons' => $seasons,
            ];
        };

        if (is_array($featured) && $featured !== []) {
            $append($featured);
        }
        foreach ($rows as $rowItems) {
            foreach ((array) $rowItems as $title) {
                if (is_array($title)) {
                    $append($title);
                }
            }
        }

        return $meta;
    }

    private function browseTypeLabel(string $type): string
    {
        return match (strtoupper($type)) {
            'SERIES' => 'Series',
            'COURSE' => 'Course',
            'MOVIE' => 'Movie',
            default => 'Title',
        };
    }

    private function seasonDisplayLabel(string $type, int $seasonNumber, int $seasonCount = 1): string
    {
        $normalized = strtoupper($type);
        if ($normalized === 'MOVIE') {
            return 'Movie';
        }

        if ($normalized === 'COURSE') {
            return 'Module ' . $seasonNumber;
        }

        return $seasonCount > 1 ? 'Season ' . $seasonNumber : 'Season 1';
    }

    private function browseGenreTags(string $genre): array
    {
        $parts = preg_split('/[,\\/|]+/', $genre) ?: [];
        $tags = [];

        foreach ($parts as $part) {
            $label = trim((string) $part);
            if ($label === '' || in_array($label, $tags, true)) {
                continue;
            }
            $tags[] = $label;
            if (count($tags) >= 3) {
                break;
            }
        }

        return $tags;
    }

    private function browseRuntimeLabel(string $type, ?array $playable, int $seasonCount, int $episodeCount): string
    {
        $normalized = strtoupper($type);
        if ($normalized === 'MOVIE') {
            return $this->formatMediaDuration((int) ($playable['duration_minutes'] ?? 0));
        }

        if ($normalized === 'COURSE') {
            if ($seasonCount > 1) {
                return $seasonCount . ' modules';
            }

            return $episodeCount > 0 ? $episodeCount . ' lessons' : '';
        }

        if ($seasonCount > 1) {
            return $seasonCount . ' seasons';
        }

        return $episodeCount > 0 ? $episodeCount . ' episodes' : '';
    }

    private function formatMediaDuration(int $minutes): string
    {
        if ($minutes <= 0) {
            return '';
        }

        if ($minutes < 60) {
            return $minutes . ' min';
        }

        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;
        if ($remaining === 0) {
            return $hours . 'h';
        }

        return $hours . 'h ' . $remaining . 'min';
    }

    private function buildPreviewFromPlayable(array $playable): ?array
    {
        $url = trim((string) ($playable['source_path'] ?? ''));
        if ($url === '') {
            return null;
        }

        $mimeType = trim((string) ($playable['mime_type'] ?? ''));
        $mimeTypeLower = strtolower($mimeType);
        $sourceType = strtoupper(trim((string) ($playable['source_type'] ?? 'URL')));
        $canEmbed = (
            $sourceType === 'ONEDRIVE'
            || preg_match('/\.(mp4|webm|ogg|mov|mkv)(\\?.*)?$/i', $url) === 1
            || str_starts_with($mimeTypeLower, 'video/')
        );

        if (!$canEmbed) {
            return null;
        }

        return [
            'url' => $url,
            'mime_type' => $mimeType,
        ];
    }

    private function resolveHeroPreview(?array $mediaRef): ?array
    {
        if (!is_array($mediaRef) || (int) ($mediaRef['id'] ?? 0) <= 0) {
            return null;
        }

        $media = (new Media())->findPlayableById((int) $mediaRef['id']);
        if (!$media) {
            return null;
        }

        $resolved = (new MediaSourceResolver())->resolve($media);
        $url = trim((string) ($resolved['url'] ?? ''));
        if ($url === '') {
            return null;
        }

        $mimeType = trim((string) (($resolved['mime_type'] ?? '') !== '' ? $resolved['mime_type'] : ($media['mime_type'] ?? '')));
        $mimeTypeLower = strtolower($mimeType);
        $isTsSource = (
            preg_match('/\.ts(\?.*)?$/i', $url) === 1
            || str_contains($mimeTypeLower, 'mp2t')
            || str_contains($mimeTypeLower, 'mpegts')
            || str_contains($mimeTypeLower, 'mpeg-tts')
            || (str_contains($mimeTypeLower, 'mpeg') && str_contains($mimeTypeLower, 'ts'))
        );

        if ($isTsSource) {
            return null;
        }

        return [
            'media_id' => (int) ($media['id'] ?? 0),
            'url' => $url,
            'mime_type' => $mimeType,
            'poster' => (string) (($media['backdrop_url'] ?? '') !== '' ? $media['backdrop_url'] : ($media['thumbnail_url'] ?? '')),
            'item_label' => (string) ($media['item_label'] ?? ''),
        ];
    }

    private function redirectByOnboardingStep(string $step): never
    {
        if ($step === OnboardingService::STEP_PLAN) {
            Response::redirect('/onboarding/plan');
        }

        if ($step === OnboardingService::STEP_PAYMENT) {
            Response::redirect('/onboarding/payment');
        }

        if ($step === OnboardingService::STEP_PROFILES) {
            Response::redirect('/profiles?manage=1');
        }

        Response::redirect('/profiles');
    }

    private function isKidsAllowed(string $maturityRating): bool
    {
        return in_array($maturityRating, ['Livre', '10', '12'], true);
    }

    private function auditPublic(string $action, string $entityType, ?string $entityId = null, ?array $metadata = null): void
    {
        try {
            (new AuditLog())->log(
                (int) (Auth::user()['id'] ?? 0),
                $action,
                $entityType,
                $entityId,
                $metadata
            );
        } catch (\Throwable) {
            // do not interrupt public flow
        }
    }
}
