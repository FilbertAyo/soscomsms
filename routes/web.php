<?php

use App\Http\Controllers\Api\Admin\ApiUsageController as AdminApiUsageController;
use App\Http\Controllers\Api\Admin\CreditTransactionController as AdminCreditTransactionController;
use App\Http\Controllers\Api\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Api\Admin\CustomerPriceController as AdminCustomerPriceController;
use App\Http\Controllers\Api\Admin\DeliveryReportController as AdminDeliveryReportController;
use App\Http\Controllers\Api\Admin\LogController as AdminLogController;
use App\Http\Controllers\Api\Admin\OverviewController as AdminOverviewController;
use App\Http\Controllers\Api\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\Admin\PricingTierController as AdminPricingTierController;
use App\Http\Controllers\Api\Admin\ProductionReadinessController as AdminProductionReadinessController;
use App\Http\Controllers\Api\Admin\ProviderBalanceController as AdminProviderBalanceController;
use App\Http\Controllers\Api\Admin\SenderIdController as AdminSenderIdController;
use App\Http\Controllers\Api\Admin\SenderIdRequestController as AdminSenderIdRequestController;
use App\Http\Controllers\Api\Admin\TrafficController as AdminTrafficController;
use App\Http\Controllers\Api\ApiKeyController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\ContactGroupController;
use App\Http\Controllers\Api\ContactImportController;
use App\Http\Controllers\Api\DeliveryReportController;
use App\Http\Controllers\Api\MessageTemplateController;
use App\Http\Controllers\Api\PasswordController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PricingTierController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProviderDeliveryReportController;
use App\Http\Controllers\Api\QuoteController;
use App\Http\Controllers\Api\SenderIdController;
use App\Http\Controllers\Api\SenderIdRequestController;
use App\Http\Controllers\Api\SessionAuthController;
use App\Http\Controllers\Api\SnippeWebhookController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

Route::redirect('/login', '/sign-in');
Route::redirect('/register', '/sign-up');

Route::prefix('api')->group(function (): void {
    Route::match(['get', 'post'], '/provider/delivery-reports', [ProviderDeliveryReportController::class, 'store'])
        ->middleware('throttle:120,1');
    Route::post('/webhooks/snippe', [SnippeWebhookController::class, 'store'])
        ->middleware('throttle:120,1');
    Route::post('/register', [SessionAuthController::class, 'register'])
        ->middleware('throttle:register');
    Route::post('/login', [SessionAuthController::class, 'login'])
        ->middleware('throttle:login');
    Route::post('/otp/verify', [SessionAuthController::class, 'verifyOtp'])
        ->middleware('throttle:otp');
    Route::post('/otp/resend', [SessionAuthController::class, 'resendOtp'])
        ->middleware('throttle:otp');
    Route::post('/forgot-password', [PasswordResetController::class, 'forgot'])
        ->middleware('throttle:password-reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:password-reset');

    Route::middleware('auth')->group(function (): void {
        Route::post('/logout', [SessionAuthController::class, 'logout']);
        Route::get('/me', [SessionAuthController::class, 'me']);
        Route::patch('/profile', [ProfileController::class, 'update']);
        Route::put('/password', [PasswordController::class, 'update']);
        Route::get('/pricing-tiers', [PricingTierController::class, 'index']);
        Route::post('/quotes', [QuoteController::class, 'store']);

        Route::middleware('profile.complete')->middleware('throttle:api')->group(function (): void {
            Route::get('/wallet', [WalletController::class, 'show']);
            Route::get('/credit-transactions', [WalletController::class, 'transactions']);

            Route::get('/contact-groups', [ContactGroupController::class, 'index']);
            Route::post('/contact-groups', [ContactGroupController::class, 'store']);
            Route::patch('/contact-groups/{contactGroup}', [ContactGroupController::class, 'update']);
            Route::delete('/contact-groups/{contactGroup}', [ContactGroupController::class, 'destroy']);

            Route::get('/contacts', [ContactController::class, 'index']);
            Route::post('/contacts', [ContactController::class, 'store']);
            Route::patch('/contacts/{contact}', [ContactController::class, 'update']);
            Route::post('/contacts/import', [ContactImportController::class, 'store']);

            Route::get('/sender-ids', [SenderIdController::class, 'index']);
            Route::post('/sender-ids/assert', [SenderIdController::class, 'assert']);
            Route::get('/sender-id-requests', [SenderIdRequestController::class, 'index']);
            Route::post('/sender-id-requests', [SenderIdRequestController::class, 'store']);
            Route::get('/sender-id-requests/{senderIdRequest}/identity-document', [SenderIdRequestController::class, 'identityDocument']);
            Route::post('/sender-id-requests/{senderIdRequest}/resubmissions', [SenderIdRequestController::class, 'resubmit']);
            Route::get('/sender-id-requests/{senderIdRequest}', [SenderIdRequestController::class, 'show']);

            Route::get('/message-templates', [MessageTemplateController::class, 'index']);
            Route::post('/message-templates', [MessageTemplateController::class, 'store']);
            Route::patch('/message-templates/{messageTemplate}', [MessageTemplateController::class, 'update']);
            Route::delete('/message-templates/{messageTemplate}', [MessageTemplateController::class, 'destroy']);

            Route::post('/sms/preview', [CampaignController::class, 'preview']);
            Route::post('/sms/segment', [CampaignController::class, 'segment']);
            Route::get('/dashboard/stats', [CampaignController::class, 'stats']);
            Route::get('/campaigns', [CampaignController::class, 'index']);
            Route::post('/campaigns', [CampaignController::class, 'store'])
                ->middleware('throttle:dashboard-send');
            Route::get('/campaigns/{campaign}', [CampaignController::class, 'show']);
            Route::post('/campaigns/{campaign}/cancel', [CampaignController::class, 'cancel']);
            Route::get('/delivery-reports', [DeliveryReportController::class, 'index']);
            Route::get('/delivery-reports/export', [DeliveryReportController::class, 'export']);

            Route::get('/api-keys', [ApiKeyController::class, 'index']);
            Route::post('/api-keys', [ApiKeyController::class, 'store']);
            Route::post('/api-keys/{apiKey}/revoke', [ApiKeyController::class, 'revoke']);

            Route::get('/webhooks', [WebhookController::class, 'index']);
            Route::post('/webhooks', [WebhookController::class, 'store']);
            Route::patch('/webhooks/{webhook}', [WebhookController::class, 'update']);
            Route::delete('/webhooks/{webhook}', [WebhookController::class, 'destroy']);
            Route::post('/webhooks/{webhook}/rotate-secret', [WebhookController::class, 'rotateSecret']);
            Route::get('/webhooks/{webhook}/deliveries', [WebhookController::class, 'deliveries']);

            Route::get('/payments', [PaymentController::class, 'index']);
            Route::post('/payments', [PaymentController::class, 'store']);
            Route::get('/payments/{payment}', [PaymentController::class, 'show']);
        });

        Route::middleware('admin')->prefix('admin')->group(function (): void {
            Route::get('/overview', [AdminOverviewController::class, 'show']);
            Route::get('/traffic', [AdminTrafficController::class, 'index']);
            Route::get('/delivery-reports', [AdminDeliveryReportController::class, 'index']);
            Route::get('/logs', [AdminLogController::class, 'index']);
            Route::get('/customers', [AdminCustomerController::class, 'index']);
            Route::get('/customers/{customer}', [AdminCustomerController::class, 'show']);
            Route::get('/customers/{customer}/prices', [AdminCustomerPriceController::class, 'index']);
            Route::post('/customers/{customer}/prices', [AdminCustomerPriceController::class, 'store']);
            Route::delete('/customers/{customer}/prices/{customerPrice}', [AdminCustomerPriceController::class, 'destroy']);
            Route::post('/customers/{customer}/credits', [AdminCustomerController::class, 'grantCredits']);
            Route::post('/customers/{customer}/suspension', [AdminCustomerController::class, 'suspend']);
            Route::post('/customers/{customer}/unsuspension', [AdminCustomerController::class, 'unsuspend']);
            Route::get('/credit-transactions', [AdminCreditTransactionController::class, 'index']);
            Route::post('/credit-transactions/{creditTransaction}/reversals', [AdminCreditTransactionController::class, 'reverse']);
            Route::get('/pricing-tiers', [AdminPricingTierController::class, 'index']);
            Route::patch('/pricing-tiers/{pricingTier}', [AdminPricingTierController::class, 'update']);
            Route::get('/provider-balance', [AdminProviderBalanceController::class, 'show']);
            Route::post('/provider-balance/dlr', [AdminProviderBalanceController::class, 'checkDlr']);
            Route::post('/provider-balance/dlr-poll', [AdminProviderBalanceController::class, 'runDlrPoll']);
            Route::get('/production', [AdminProductionReadinessController::class, 'show']);
            Route::patch('/production', [AdminProductionReadinessController::class, 'update']);
            Route::post('/production/whitelist', [AdminProductionReadinessController::class, 'confirmWhitelist']);
            Route::post('/production/account', [AdminProductionReadinessController::class, 'confirmAccount']);
            Route::post('/production/backups', [AdminProductionReadinessController::class, 'backup']);
            Route::get('/sender-ids', [AdminSenderIdController::class, 'index']);
            Route::post('/sender-ids', [AdminSenderIdController::class, 'store']);
            Route::patch('/sender-ids/{senderId}', [AdminSenderIdController::class, 'update']);
            Route::delete('/sender-ids/{senderId}', [AdminSenderIdController::class, 'destroy']);
            Route::post('/sender-ids/{senderId}/grants', [AdminSenderIdController::class, 'grant']);
            Route::delete('/sender-ids/{senderId}/grants/{customer}', [AdminSenderIdController::class, 'revoke']);
            Route::get('/sender-id-requests', [AdminSenderIdRequestController::class, 'index']);
            Route::get('/sender-id-requests/{senderIdRequest}/identity-document', [AdminSenderIdRequestController::class, 'identityDocument']);
            Route::get('/sender-id-requests/{senderIdRequest}', [AdminSenderIdRequestController::class, 'show']);
            Route::post('/sender-id-requests/{senderIdRequest}/review', [AdminSenderIdRequestController::class, 'review']);
            Route::post('/sender-id-requests/{senderIdRequest}/provider-submissions', [AdminSenderIdRequestController::class, 'submitToProvider']);
            Route::post('/sender-id-requests/{senderIdRequest}/approval', [AdminSenderIdRequestController::class, 'approve']);
            Route::post('/sender-id-requests/{senderIdRequest}/rejection', [AdminSenderIdRequestController::class, 'reject']);
            Route::post('/sender-id-requests/{senderIdRequest}/resubmission-requests', [AdminSenderIdRequestController::class, 'requestResubmission']);
            Route::get('/api-usage/requests', [AdminApiUsageController::class, 'requests']);
            Route::get('/api-usage/keys', [AdminApiUsageController::class, 'keys']);
            Route::get('/api-usage/webhook-deliveries', [AdminApiUsageController::class, 'webhookDeliveries']);
            Route::get('/payments', [AdminPaymentController::class, 'index']);
        });
    });
});

Route::view('/{any?}', 'spa')->where('any', '^(?!api(?:/|$)|sanctum(?:/|$)|up$).*');
