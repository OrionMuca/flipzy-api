<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::prefix('v1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    
    // Public property viewing
    Route::get('/properties', [\App\Http\Controllers\PropertyController::class, 'index']);
    Route::get('/properties/{property}', [\App\Http\Controllers\PropertyController::class, 'show']);
});

// Protected routes
Route::middleware('auth:api')->prefix('v1')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    // Property CRUD (create, update, delete require auth)
    Route::post('/properties', [\App\Http\Controllers\PropertyController::class, 'store']);
    Route::put('/properties/{property}', [\App\Http\Controllers\PropertyController::class, 'update']);
    Route::delete('/properties/{property}', [\App\Http\Controllers\PropertyController::class, 'destroy']);
    
    // Property image routes (require auth)
    Route::post('/properties/{property}/images', [\App\Http\Controllers\PropertyController::class, 'uploadImages']);
    Route::delete('/properties/{property}/images/{image}', [\App\Http\Controllers\PropertyController::class, 'deleteImage']);
    Route::put('/properties/{property}/images/{image}/primary', [\App\Http\Controllers\PropertyController::class, 'setPrimaryImage']);
    
    // Property enrichment route (require auth)
    Route::post('/properties/{property}/enrich', [\App\Http\Controllers\PropertyController::class, 'enrich']);
    
    // Conversation routes (require auth)
    Route::get('/conversations', [\App\Http\Controllers\ConversationController::class, 'index']);
    Route::post('/conversations', [\App\Http\Controllers\ConversationController::class, 'store']);
    Route::get('/conversations/{conversation}', [\App\Http\Controllers\ConversationController::class, 'show']);
    
    // Message routes (require auth)
    Route::get('/conversations/{conversation}/messages', [\App\Http\Controllers\MessageController::class, 'index']);
    Route::post('/conversations/{conversation}/messages', [\App\Http\Controllers\MessageController::class, 'store']);
    Route::put('/conversations/{conversation}/messages/read', [\App\Http\Controllers\MessageController::class, 'markConversationAsRead']);
    Route::put('/messages/{message}/read', [\App\Http\Controllers\MessageController::class, 'markAsRead']);
    Route::get('/messages/unread-count', [\App\Http\Controllers\MessageController::class, 'unreadCount']);
    
    // Analytics routes (require auth)
    Route::post('/properties/{property}/view', [\App\Http\Controllers\AnalyticsController::class, 'trackView']);
    Route::post('/properties/{property}/save', [\App\Http\Controllers\AnalyticsController::class, 'trackSave']);
    Route::post('/properties/{property}/inquiry', [\App\Http\Controllers\AnalyticsController::class, 'trackInquiry']);
    Route::get('/properties/{property}/analytics', [\App\Http\Controllers\AnalyticsController::class, 'getPropertyAnalytics']);
    Route::get('/users/{user}/credibility', [\App\Http\Controllers\AnalyticsController::class, 'getCredibilityScore']);
    Route::get('/analytics/my-analytics', [\App\Http\Controllers\AnalyticsController::class, 'getMyAnalytics']);
    
    // Rehab estimate routes (require auth + premium/VIP or admin)
    Route::post('/properties/{property}/estimate', [\App\Http\Controllers\RehabEstimateController::class, 'generateEstimate']);
    Route::get('/properties/{property}/estimates', [\App\Http\Controllers\RehabEstimateController::class, 'getEstimateHistory']);
    Route::get('/estimates/{estimate}', [\App\Http\Controllers\RehabEstimateController::class, 'show']);
    
    // Payment routes (require auth)
    Route::post('/payments/intent', [\App\Http\Controllers\PaymentController::class, 'createIntent']);
    Route::post('/payments/confirm', [\App\Http\Controllers\PaymentController::class, 'confirm']);
    Route::get('/payments/transactions', [\App\Http\Controllers\PaymentController::class, 'transactions']);
    Route::get('/payments/transactions/{transaction}', [\App\Http\Controllers\PaymentController::class, 'show']);
    
    // Refund routes (require auth)
    Route::post('/refunds', [\App\Http\Controllers\RefundController::class, 'create']);
    Route::get('/refunds/{refund}', [\App\Http\Controllers\RefundController::class, 'show']);
    
    // Subscription routes (require auth)
    Route::get('/subscriptions/plans', [\App\Http\Controllers\SubscriptionController::class, 'plans']);
    Route::post('/subscriptions/checkout', [\App\Http\Controllers\SubscriptionController::class, 'checkout']);
    Route::post('/subscriptions', [\App\Http\Controllers\SubscriptionController::class, 'store']);
    Route::get('/subscriptions/current', [\App\Http\Controllers\SubscriptionController::class, 'current']);
    Route::post('/subscriptions/cancel', [\App\Http\Controllers\SubscriptionController::class, 'cancel']);
    Route::get('/subscriptions/history', [\App\Http\Controllers\SubscriptionController::class, 'history']);
    
    // Admin routes (require admin role)
    Route::middleware('admin')->prefix('admin')->group(function () {
        // User management
        Route::get('/users', [\App\Http\Controllers\Admin\AdminUserController::class, 'index']);
        Route::get('/users/{user}', [\App\Http\Controllers\Admin\AdminUserController::class, 'show']);
        Route::put('/users/{user}', [\App\Http\Controllers\Admin\AdminUserController::class, 'update']);
        Route::delete('/users/{user}', [\App\Http\Controllers\Admin\AdminUserController::class, 'destroy']);
        Route::post('/users/{user}/suspend', [\App\Http\Controllers\Admin\AdminUserController::class, 'suspend']);
        Route::post('/users/{user}/activate', [\App\Http\Controllers\Admin\AdminUserController::class, 'activate']);
        
        // Property management
        Route::get('/properties', [\App\Http\Controllers\Admin\AdminPropertyController::class, 'index']);
        Route::get('/properties/{property}', [\App\Http\Controllers\Admin\AdminPropertyController::class, 'show']);
        Route::put('/properties/{property}', [\App\Http\Controllers\Admin\AdminPropertyController::class, 'update']);
        Route::delete('/properties/{property}', [\App\Http\Controllers\Admin\AdminPropertyController::class, 'destroy']);
        Route::post('/properties/{property}/approve', [\App\Http\Controllers\Admin\AdminPropertyController::class, 'approve']);
        Route::post('/properties/{property}/feature', [\App\Http\Controllers\Admin\AdminPropertyController::class, 'feature']);
        Route::post('/properties/{property}/verify', [\App\Http\Controllers\Admin\AdminPropertyController::class, 'verify']);
        
        // Analytics & Statistics
        Route::get('/analytics/overview', [\App\Http\Controllers\Admin\AdminAnalyticsController::class, 'overview']);
        Route::get('/analytics/users', [\App\Http\Controllers\Admin\AdminAnalyticsController::class, 'users']);
        Route::get('/analytics/properties', [\App\Http\Controllers\Admin\AdminAnalyticsController::class, 'properties']);
        Route::get('/analytics/engagement', [\App\Http\Controllers\Admin\AdminAnalyticsController::class, 'engagement']);
        Route::get('/analytics/trends', [\App\Http\Controllers\Admin\AdminAnalyticsController::class, 'trends']);
        Route::get('/analytics/top-properties', [\App\Http\Controllers\Admin\AdminAnalyticsController::class, 'topProperties']);
        Route::get('/analytics/top-wholesalers', [\App\Http\Controllers\Admin\AdminAnalyticsController::class, 'topWholesalers']);
        Route::get('/analytics/geographic', [\App\Http\Controllers\Admin\AdminAnalyticsController::class, 'geographicDistribution']);
        
        // Subscription management
        Route::get('/subscriptions', [\App\Http\Controllers\Admin\AdminSubscriptionController::class, 'index']);
        Route::get('/subscriptions/{subscription}', [\App\Http\Controllers\Admin\AdminSubscriptionController::class, 'show']);
        Route::get('/subscriptions/plans', [\App\Http\Controllers\Admin\AdminSubscriptionController::class, 'plans']);
        Route::get('/subscriptions/stats', [\App\Http\Controllers\Admin\AdminSubscriptionController::class, 'stats']);
        
        // Transaction management
        Route::get('/transactions', [\App\Http\Controllers\Admin\AdminTransactionController::class, 'index']);
        Route::get('/transactions/stats', [\App\Http\Controllers\Admin\AdminTransactionController::class, 'stats']);
        Route::get('/transactions/{transaction}', [\App\Http\Controllers\Admin\AdminTransactionController::class, 'show']);
        
        // System management
        Route::get('/system/health', [\App\Http\Controllers\Admin\AdminSystemController::class, 'health']);
        Route::get('/system/stats', [\App\Http\Controllers\Admin\AdminSystemController::class, 'stats']);
        Route::get('/system/logs', [\App\Http\Controllers\Admin\AdminSystemController::class, 'logs']);
        Route::get('/system/queue', [\App\Http\Controllers\Admin\AdminSystemController::class, 'queue']);
    });
});

// Webhook routes (no auth required, but signature verified)
Route::post('/webhooks/stripe', [\App\Http\Controllers\StripeWebhookController::class, 'handle']);

