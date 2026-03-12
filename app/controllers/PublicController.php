<?php

declare(strict_types=1);

namespace App\controllers;

use App\core\Auth;
use App\core\Response;
use App\core\Validator;
use App\core\View;
use App\models\AuditLog;
use App\models\BillingPlan;
use App\models\Entitlement;
use App\models\Media;
use App\models\Offer;
use App\models\PaymentMethod;
use App\models\PublicNotification;
use App\models\PaymentEvent;
use App\models\PaymentTransaction;
use App\models\Profile;
use App\models\Setting;
use App\models\Subscription;
use App\models\Title;
use App\models\User;
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
        foreach ($continueWatching as $historyItem) {
            $titleIds[] = (int) ($historyItem['title_id'] ?? 0);
        }
        $titleIds = array_values(array_unique(array_filter(array_map('intval', $titleIds), static fn (int $value): bool => $value > 0)));
        $firstPlayableByTitle = $mediaModel->firstPlayableByTitleIds($titleIds);
        $mediaHierarchyByTitle = $mediaModel->hierarchyForTitleIds($titleIds, true);
        $browseMetaByTitle = $this->buildBrowseMetaByTitle($featured, $rows, $firstPlayableByTitle, $mediaHierarchyByTitle);
        $marketplaceAccessByTitle = $this->buildMarketplaceAccessMap((int) (Auth::user()['id'] ?? 0), $titleIds);
        foreach ($marketplaceAccessByTitle as $titleId => $access) {
            $browseMetaByTitle[$titleId] = array_merge($browseMetaByTitle[$titleId] ?? [], $access);
            if ((int) ($access['is_locked'] ?? 0) === 1) {
                $browseMetaByTitle[$titleId]['watch_media_id'] = 0;
                foreach (($browseMetaByTitle[$titleId]['seasons'] ?? []) as $seasonIndex => $season) {
                    foreach (($season['items'] ?? []) as $itemIndex => $item) {
                        $browseMetaByTitle[$titleId]['seasons'][$seasonIndex]['items'][$itemIndex]['watch_url'] = (string) ($access['pay_url'] ?? '/title/' . $titleId . '/pay');
                    }
                }
            }
        }
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
            'marketplaceAccessByTitle' => $marketplaceAccessByTitle,
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
        $marketplaceAccess = $this->marketplaceAccessState((int) (Auth::user()['id'] ?? 0), (int) $title['id']);
        $marketplaceOffers = (new Offer())->publicActiveForTitle((int) $title['id']);

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
            'marketplaceAccess' => $marketplaceAccess,
            'marketplaceOffers' => $marketplaceOffers,
        ], 'public');
    }

    public function pay(array $params): void
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

        if ((int) ($profile['is_kids'] ?? 0) === 1 && !$this->isKidsAllowed((string) ($title['maturity_rating'] ?? '18'))) {
            flash('error', 'Este titulo nao esta disponivel no perfil infantil.');
            Response::redirect('/browse');
        }

        $access = $this->marketplaceAccessState((int) (Auth::user()['id'] ?? 0), (int) $title['id']);
        if ((int) ($access['has_public_offers'] ?? 0) !== 1) {
            flash('error', 'Este titulo nao possui ofertas publicas disponiveis.');
            Response::redirect('/title/' . (int) $title['id']);
        }

        if ((int) ($access['has_entitlement'] ?? 0) === 1) {
            Response::redirect('/title/' . (int) $title['id']);
        }

        $settingModel = new Setting();
        $paymentGatewayEnabled = $settingModel->value('payment.gateway_enabled', '0') === '1';
        $checkoutReady = false;

        View::render('public/title_pay', [
            'title' => 'Unlock | ' . (string) ($title['title_name'] ?? 'WisePlay'),
            'currentPath' => current_path(),
            'profile' => $profile,
            'availableProfiles' => (new Profile())->findByUser((int) (Auth::user()['id'] ?? 0)),
            'selectedProfileId' => (int) ($_SESSION['profile_id'] ?? 0),
            'titleItem' => $title,
            'offers' => (new Offer())->publicActiveForTitle((int) $title['id']),
            'paymentGatewayEnabled' => $paymentGatewayEnabled,
            'checkoutReady' => $checkoutReady,
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

        $marketplaceAccess = $this->marketplaceAccessState((int) (Auth::user()['id'] ?? 0), (int) ($media['title_id'] ?? 0));
        if ((int) ($marketplaceAccess['is_locked'] ?? 0) === 1) {
            flash('error', 'Voce precisa desbloquear este titulo antes de assistir.');
            Response::redirect('/title/' . (int) ($media['title_id'] ?? 0) . '/pay');
        }

        $resolved = (new MediaSourceResolver())->resolve($media);
        $media['playback_url'] = (string) ($resolved['url'] ?? '');
        $media['playback_message'] = (string) ($resolved['message'] ?? '');
        $media['playback_from_cache'] = (bool) ($resolved['from_cache'] ?? false);
        $media['playback_mime_type'] = (string) ($resolved['mime_type'] ?? ($media['mime_type'] ?? ''));

        $episodeQueue = (new Media())->listForTitle((int) $media['title_id'], true);
        $suggestions = (new Media())->suggestionsForTitle((int) $media['title_id'], $mediaId, 8);

        View::render('public/watch', [
            'title' => 'Assistir | WisePlay',
            'currentPath' => current_path(),
            'profile' => $profile,
            'availableProfiles' => (new Profile())->findByUser((int) (Auth::user()['id'] ?? 0)),
            'selectedProfileId' => (int) ($_SESSION['profile_id'] ?? 0),
            'mediaItem' => $media,
            'episodeQueue' => $episodeQueue,
            'suggestions' => $suggestions,
        ], 'public');
    }


    public function account(): void
    {
        Auth::requireLogin(false);
        $this->ensureOnboardingAllowed([OnboardingService::STEP_COMPLETE]);

        $userId = (int) (Auth::user()['id'] ?? 0);
        $userModel = new User();
        $profileModel = new Profile();

        $userRow = $userModel->findById($userId);
        if (!$userRow) {
            Auth::logout();
            flash('error', 'Sessao invalida. Entre novamente.');
            Response::redirect('/login');
        }

        $selectedProfile = $this->requireSelectedProfile(false);
        $availableProfiles = $profileModel->findByUser($userId);
        if (!$selectedProfile && $availableProfiles !== []) {
            $selectedProfile = $availableProfiles[0];
            $_SESSION['profile_id'] = (int) ($selectedProfile['id'] ?? 0);
        }

        $subscription = (new Subscription())->latestByUser($userId);
        $plans = array_values(array_filter((new BillingPlan())->listPublicPlans(), static function (array $plan): bool {
            return (int) ($plan['active'] ?? 0) === 1 && (int) ($plan['is_selectable'] ?? 0) === 1;
        }));

        // Account page aggregates subscription, billing and profile management.
        $paymentMethods = (new PaymentMethod())->listByUser($userId);

        View::render('public/account', [
            'title' => 'Conta | WisePlay',
            'currentPath' => current_path(),
            'profile' => $selectedProfile,
            'availableProfiles' => $availableProfiles,
            'selectedProfileId' => (int) ($_SESSION['profile_id'] ?? 0),
            'userAccount' => $userRow,
            'subscription' => $subscription,
            'plans' => $plans,
            'paymentMethods' => $paymentMethods,
            'profiles' => $availableProfiles,
        ], 'public');
    }

    public function changePlan(): void
    {
        Auth::requireLogin(false);
        $this->requireCsrf();
        $this->ensureOnboardingAllowed([OnboardingService::STEP_COMPLETE]);

        $userId = (int) (Auth::user()['id'] ?? 0);
        $planId = (int) ($_POST['plan_id'] ?? 0);
        if ($planId <= 0) {
            flash('error', 'Selecione um plano valido.');
            Response::redirect('/account#subscription');
        }

        $planModel = new BillingPlan();
        $plan = $planModel->findById($planId);
        if (
            !$plan
            || (int) ($plan['active'] ?? 0) !== 1
            || (int) ($plan['is_selectable'] ?? 0) !== 1
            || !in_array((string) ($plan['billing_cycle'] ?? ''), ['MONTHLY', 'YEARLY'], true)
        ) {
            flash('error', 'Plano indisponivel no momento.');
            Response::redirect('/account#subscription');
        }

        $methodModel = new PaymentMethod();
        $hasPaymentMethod = $methodModel->countByUser($userId) > 0;
        $requiresPayment = (int) ($plan['requires_payment_method'] ?? 0) === 1;

        if ($requiresPayment && !$hasPaymentMethod) {
            flash('error', 'Adicione um meio de pagamento antes de migrar para esse plano.');
            Response::redirect('/account#payment');
        }

        $subscriptionModel = new Subscription();
        $current = $subscriptionModel->latestByUser($userId);
        $status = 'ACTIVE';
        if ($current && in_array((string) ($current['status'] ?? ''), Subscription::STATUSES, true)) {
            $currentStatus = (string) $current['status'];
            $status = in_array($currentStatus, ['CANCELED', 'EXPIRED'], true) ? 'ACTIVE' : $currentStatus;
        }

        $paymentRequired = $requiresPayment;
        $paymentStatus = $requiresPayment ? 'PAID' : 'NOT_REQUIRED';

        try {
            $subscriptionId = $subscriptionModel->assignOrUpdate(
                $userId,
                (int) $plan['id'],
                $status,
                null,
                true,
                $paymentRequired,
                $paymentStatus,
                $requiresPayment ? 'manual' : null,
                null
            );
        } catch (\Throwable) {
            flash('error', 'Nao foi possivel alterar o plano agora.');
            Response::redirect('/account#subscription');
        }

        $this->auditPublic('account.plan.change', 'subscription', (string) $subscriptionId, [
            'plan_id' => (int) $plan['id'],
            'plan_slug' => (string) ($plan['slug'] ?? ''),
            'requires_payment' => $requiresPayment ? 1 : 0,
        ]);

        flash('success', 'Plano atualizado para ' . (string) ($plan['plan_name'] ?? 'novo plano') . '.');
        Response::redirect('/account#subscription');
    }

    public function addPaymentMethod(): void
    {
        Auth::requireLogin(false);
        $this->requireCsrf();
        $this->ensureOnboardingAllowed([OnboardingService::STEP_COMPLETE]);

        $userId = (int) (Auth::user()['id'] ?? 0);
        $holder = trim((string) ($_POST['card_holder'] ?? ''));
        $cardNumber = preg_replace('/\D+/', '', (string) ($_POST['card_number'] ?? ''));
        $expMonth = (int) ($_POST['exp_month'] ?? 0);
        $expYear = (int) ($_POST['exp_year'] ?? 0);
        $setDefault = (int) ($_POST['set_default'] ?? 0) === 1;

        if (!Validator::required($holder) || !Validator::minLength($holder, 3)) {
            flash('error', 'Nome do titular invalido.');
            Response::redirect('/account#payment');
        }

        if (!is_string($cardNumber) || strlen($cardNumber) < 13 || strlen($cardNumber) > 19) {
            flash('error', 'Numero do cartao invalido.');
            Response::redirect('/account#payment');
        }

        if ($expMonth < 1 || $expMonth > 12) {
            flash('error', 'Mes de expiracao invalido.');
            Response::redirect('/account#payment');
        }

        $currentYear = (int) date('Y');
        if ($expYear < $currentYear || $expYear > ($currentYear + 20)) {
            flash('error', 'Ano de expiracao invalido.');
            Response::redirect('/account#payment');
        }

        $model = new PaymentMethod();
        $isFirstMethod = $model->countByUser($userId) === 0;
        $brand = $this->detectCardBrand($cardNumber);

        try {
            $methodId = $model->create([
                'user_id' => $userId,
                'provider' => 'CARD',
                'token_ref' => 'pm_' . bin2hex(random_bytes(8)),
                'brand' => $brand,
                'last4' => substr($cardNumber, -4),
                'exp_month' => $expMonth,
                'exp_year' => $expYear,
                'is_default' => ($setDefault || $isFirstMethod) ? 1 : 0,
            ]);
        } catch (\Throwable) {
            flash('error', 'Falha ao salvar meio de pagamento.');
            Response::redirect('/account#payment');
        }

        if ($setDefault || $isFirstMethod) {
            $model->setDefault($methodId, $userId);
        }

        $this->auditPublic('account.payment_method.add', 'payment_method', (string) $methodId, [
            'brand' => $brand,
            'last4' => substr($cardNumber, -4),
        ]);

        flash('success', 'Meio de pagamento adicionado com sucesso.');
        Response::redirect('/account#payment');
    }

    public function setDefaultPaymentMethod(): void
    {
        Auth::requireLogin(false);
        $this->requireCsrf();
        $this->ensureOnboardingAllowed([OnboardingService::STEP_COMPLETE]);

        $userId = (int) (Auth::user()['id'] ?? 0);
        $methodId = (int) ($_POST['method_id'] ?? 0);
        if ($methodId <= 0) {
            flash('error', 'Metodo de pagamento invalido.');
            Response::redirect('/account#payment');
        }

        $model = new PaymentMethod();
        $method = $model->findByIdForUser($methodId, $userId);
        if (!$method) {
            flash('error', 'Metodo de pagamento nao encontrado.');
            Response::redirect('/account#payment');
        }

        $model->setDefault($methodId, $userId);

        $this->auditPublic('account.payment_method.default', 'payment_method', (string) $methodId, [
            'brand' => (string) ($method['brand'] ?? ''),
            'last4' => (string) ($method['last4'] ?? ''),
        ]);

        flash('success', 'Metodo de pagamento padrao atualizado.');
        Response::redirect('/account#payment');
    }

    public function deletePaymentMethod(): void
    {
        Auth::requireLogin(false);
        $this->requireCsrf();
        $this->ensureOnboardingAllowed([OnboardingService::STEP_COMPLETE]);

        $userId = (int) (Auth::user()['id'] ?? 0);
        $methodId = (int) ($_POST['method_id'] ?? 0);
        if ($methodId <= 0) {
            flash('error', 'Metodo de pagamento invalido.');
            Response::redirect('/account#payment');
        }

        $model = new PaymentMethod();
        $method = $model->findByIdForUser($methodId, $userId);
        if (!$method) {
            flash('error', 'Metodo de pagamento nao encontrado.');
            Response::redirect('/account#payment');
        }

        $requiredPlan = (new Subscription())->latestByUser($userId);
        $requiresPayment = (int) ($requiredPlan['requires_payment_method'] ?? 0) === 1;
        if ($requiresPayment && $model->countByUser($userId) <= 1) {
            flash('error', 'Seu plano atual exige ao menos um meio de pagamento ativo.');
            Response::redirect('/account#payment');
        }

        $model->delete($methodId, $userId);

        $this->auditPublic('account.payment_method.delete', 'payment_method', (string) $methodId, [
            'brand' => (string) ($method['brand'] ?? ''),
            'last4' => (string) ($method['last4'] ?? ''),
        ]);

        flash('success', 'Meio de pagamento removido.');
        Response::redirect('/account#payment');
    }

    public function updatePassword(): void
    {
        Auth::requireLogin(false);
        $this->requireCsrf();
        $this->ensureOnboardingAllowed([OnboardingService::STEP_COMPLETE]);

        $userId = (int) (Auth::user()['id'] ?? 0);
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $nextPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if (!Validator::minLength($nextPassword, 8)) {
            flash('error', 'A nova senha precisa ter pelo menos 8 caracteres.');
            Response::redirect('/account#security');
        }

        if ($nextPassword !== $confirmPassword) {
            flash('error', 'Confirmacao de senha nao confere.');
            Response::redirect('/account#security');
        }

        $userModel = new User();
        $user = $userModel->findById($userId);
        if (!$user || !password_verify($currentPassword, (string) ($user['password_hash'] ?? ''))) {
            flash('error', 'Senha atual invalida.');
            Response::redirect('/account#security');
        }

        if (password_verify($nextPassword, (string) ($user['password_hash'] ?? ''))) {
            flash('error', 'A nova senha deve ser diferente da senha atual.');
            Response::redirect('/account#security');
        }

        $hashed = password_hash($nextPassword, PASSWORD_DEFAULT);

        try {
            $userModel->update($userId, [
                'name' => (string) ($user['name'] ?? ''),
                'email' => (string) ($user['email'] ?? ''),
                'account_avatar' => (string) ($user['account_avatar'] ?? ''),
                'role' => (string) ($user['role'] ?? 'CUSTOMER'),
                'status' => (string) ($user['status'] ?? 'ACTIVE'),
                'password_hash' => $hashed,
                'password_set_at' => date('Y-m-d H:i:s'),
                'email_verified_at' => $user['email_verified_at'] ?? null,
                'onboarding_completed_at' => $user['onboarding_completed_at'] ?? null,
            ]);
            $updated = $userModel->findById($userId);
            if ($updated) {
                Auth::login($updated);
            }
        } catch (\Throwable) {
            flash('error', 'Nao foi possivel atualizar a senha agora.');
            Response::redirect('/account#security');
        }

        $this->auditPublic('account.password.update', 'user', (string) $userId, null);

        flash('success', 'Senha atualizada com sucesso.');
        Response::redirect('/account#security');
    }

    public function deleteAccount(): void
    {
        Auth::requireLogin(false);
        $this->requireCsrf();

        $userId = (int) (Auth::user()['id'] ?? 0);
        $confirmation = strtoupper(trim((string) ($_POST['confirmation_text'] ?? '')));
        $password = (string) ($_POST['current_password'] ?? '');

        if ($confirmation !== 'EXCLUIR') {
            flash('error', 'Digite EXCLUIR para confirmar.');
            Response::redirect('/account#danger');
        }

        $userModel = new User();
        $user = $userModel->findById($userId);
        if (!$user || !password_verify($password, (string) ($user['password_hash'] ?? ''))) {
            flash('error', 'Senha invalida para excluir conta.');
            Response::redirect('/account#danger');
        }

        $this->auditPublic('account.delete', 'user', (string) $userId, [
            'email' => (string) ($user['email'] ?? ''),
        ]);

        try {
            $userModel->delete($userId);
        } catch (\Throwable) {
            flash('error', 'Nao foi possivel excluir a conta agora.');
            Response::redirect('/account#danger');
        }

        Auth::logout();
        unset($_SESSION['profile_id']);

        flash('success', 'Sua conta foi removida.');
        Response::redirect('/');
    }

    public function markNotificationsRead(): void
    {
        Auth::requireLogin(false);
        $this->requireCsrf();

        $userId = (int) (Auth::user()['id'] ?? 0);
        $marked = 0;

        try {
            $marked = (new PublicNotification())->markAllAsRead($userId);
        } catch (\Throwable) {
            if ($this->isAjaxRequest()) {
                Response::json(['ok' => false, 'message' => 'Falha ao atualizar notificacoes.'], 500);
            }
            flash('error', 'Falha ao atualizar notificacoes.');
            Response::redirect('/browse');
        }

        if ($this->isAjaxRequest()) {
            Response::json(['ok' => true, 'marked' => $marked]);
        }

        flash('success', 'Notificacoes marcadas como lidas.');
        Response::redirect($_SERVER['HTTP_REFERER'] ?? '/browse');
    }

    public function titlePreview(array $params): void
    {
        Auth::requireLogin(false);
        $this->ensureOnboardingAllowed([OnboardingService::STEP_COMPLETE]);

        $profile = $this->requireSelectedProfile(false);
        if (!$profile) {
            Response::json(['ok' => false, 'message' => 'Perfil nao selecionado.'], 422);
        }

        $titleId = (int) ($params['id'] ?? 0);
        if ($titleId <= 0) {
            Response::json(['ok' => false, 'message' => 'Titulo invalido.'], 422);
        }

        $title = (new Title())->findPublishedById($titleId);
        if (!$title) {
            Response::json(['ok' => false, 'message' => 'Titulo nao encontrado.'], 404);
        }

        if ((int) ($profile['is_kids'] ?? 0) === 1 && !$this->isKidsAllowed((string) ($title['maturity_rating'] ?? '18'))) {
            Response::json(['ok' => false, 'message' => 'Titulo indisponivel para perfil infantil.'], 403);
        }

        $playable = (new Media())->firstPlayableByTitle($titleId);
        if (!$playable) {
            Response::json(['ok' => false, 'message' => 'Sem midia reproduzivel para este titulo.'], 404);
        }

        $preview = $this->resolveHeroPreview($playable);
        if (!is_array($preview) || trim((string) ($preview['url'] ?? '')) === '') {
            Response::json(['ok' => false, 'message' => 'Trailer indisponivel para este titulo.'], 404);
        }

        Response::json([
            'ok' => true,
            'url' => (string) ($preview['url'] ?? ''),
            'mime_type' => (string) ($preview['mime_type'] ?? ''),
            'media_id' => (int) ($preview['media_id'] ?? 0),
        ]);
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

        $marketplaceAccess = $this->marketplaceAccessState((int) (Auth::user()['id'] ?? 0), (int) ($media['title_id'] ?? 0));
        if ((int) ($marketplaceAccess['is_locked'] ?? 0) === 1) {
            Response::json(['ok' => false, 'message' => 'Este titulo exige compra antes de salvar progresso.', 'redirect' => '/title/' . (int) ($media['title_id'] ?? 0) . '/pay'], 403);
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
                        'season_number' => (int) $seasonNumber,
                        'number' => (int) ($episode['episode_number'] ?? 1),
                        'label' => (string) ($episode['item_label'] ?? ''),
                        'summary' => $this->browseEpisodeSummary(
                            $titleType,
                            (int) $seasonNumber,
                            (int) ($episode['episode_number'] ?? 1),
                            (string) ($episode['item_label'] ?? ''),
                            (string) ($title['description'] ?? '')
                        ),
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

    private function buildMarketplaceAccessMap(int $userId, array $titleIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $titleIds), static fn (int $id): bool => $id > 0)));
        if ($userId <= 0 || $ids === []) {
            return [];
        }

        $offersByTitle = (new Offer())->publicActiveGroupedByTitleIds($ids);
        $entitledIds = (new Entitlement())->activeTitleIdsForUser($userId, $ids);
        $entitledLookup = array_fill_keys($entitledIds, true);

        $map = [];
        foreach ($ids as $titleId) {
            $offers = $offersByTitle[$titleId] ?? [];
            $hasOffers = $offers !== [];
            $hasEntitlement = isset($entitledLookup[$titleId]);
            $map[$titleId] = [
                'has_public_offers' => $hasOffers ? 1 : 0,
                'has_entitlement' => $hasEntitlement ? 1 : 0,
                'is_locked' => ($hasOffers && !$hasEntitlement) ? 1 : 0,
                'offer_count' => count($offers),
                'pay_url' => '/title/' . $titleId . '/pay',
                'watch_url' => '/title/' . $titleId,
            ];
        }

        return $map;
    }

    private function marketplaceAccessState(int $userId, int $titleId): array
    {
        if ($titleId <= 0) {
            return [
                'has_public_offers' => 0,
                'has_entitlement' => 0,
                'is_locked' => 0,
                'offer_count' => 0,
                'pay_url' => '/browse',
                'watch_url' => '/browse',
            ];
        }

        $map = $this->buildMarketplaceAccessMap($userId, [$titleId]);
        return $map[$titleId] ?? [
            'has_public_offers' => 0,
            'has_entitlement' => 0,
            'is_locked' => 0,
            'offer_count' => 0,
            'pay_url' => '/title/' . $titleId . '/pay',
            'watch_url' => '/title/' . $titleId,
        ];
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

    private function browseEpisodeSummary(string $type, int $seasonNumber, int $episodeNumber, string $itemLabel, string $titleDescription): string
    {
        $normalized = strtoupper($type);
        $cleanLabel = trim(preg_replace('/^S\d+\s*E\d+\s*-\s*/i', '', $itemLabel) ?? '');
        $cleanLabel = trim(preg_replace('/^M[oó]dulo\s*\d+\s*\/\s*L[ií]?[cç][aã]o\s*\d+\s*-\s*/i', '', $cleanLabel) ?? $cleanLabel);

        if ($cleanLabel === '') {
            $cleanLabel = $itemLabel !== '' ? $itemLabel : 'Sem titulo';
        }

        if ($normalized === 'COURSE') {
            return sprintf(
                'Modulo %d, licao %d: %s. Continue seu progresso neste curso.',
                max(1, $seasonNumber),
                max(1, $episodeNumber),
                $cleanLabel
            );
        }

        if ($normalized === 'SERIES') {
            return sprintf(
                'Temporada %d, episodio %d: %s. %s',
                max(1, $seasonNumber),
                max(1, $episodeNumber),
                $cleanLabel,
                trim($titleDescription) !== '' ? trim($titleDescription) : 'Continue assistindo para descobrir os proximos acontecimentos.'
            );
        }

        return trim($titleDescription) !== '' ? trim($titleDescription) : $cleanLabel;
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
        $resolved = $this->resolveHeroPreview($playable);
        if (!is_array($resolved) || $resolved === []) {
            return null;
        }

        return [
            'url' => (string) ($resolved['url'] ?? ''),
            'mime_type' => (string) ($resolved['mime_type'] ?? ''),
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


    private function detectCardBrand(string $cardNumber): string
    {
        if (preg_match('/^4\d{12}(?:\d{3})?(?:\d{3})?$/', $cardNumber) === 1) {
            return 'VISA';
        }

        if (preg_match('/^(?:5[1-5]\d{14}|2(?:2[2-9]|[3-6]\d|7[01])\d{12})$/', $cardNumber) === 1) {
            return 'MASTERCARD';
        }

        if (preg_match('/^3[47]\d{13}$/', $cardNumber) === 1) {
            return 'AMEX';
        }

        if (preg_match('/^3(?:0[0-5]|[68]\d)\d{11}$/', $cardNumber) === 1) {
            return 'DINERS';
        }

        if (preg_match('/^(?:6011\d{12}|65\d{14}|64[4-9]\d{13})$/', $cardNumber) === 1) {
            return 'DISCOVER';
        }

        if (preg_match('/^(?:2131|1800|35\d{3})\d{11}$/', $cardNumber) === 1) {
            return 'JCB';
        }

        return 'CARD';
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
