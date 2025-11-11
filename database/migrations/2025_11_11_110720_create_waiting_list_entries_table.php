<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('waiting_list_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email')->unique();
            $table->string('name');
            $table->foreignUuid('subscription_plan_id')->constrained('subscription_plans')->onDelete('cascade');
            $table->uuid('coupon_id')->nullable();
            $table->string('coupon_code')->nullable(); // Store the code used for reference
            $table->enum('status', ['pending', 'payment_completed', 'account_created', 'cancelled'])->default('pending');
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->string('stripe_checkout_session_id')->nullable();
            $table->decimal('original_price', 10, 2);
            $table->decimal('discounted_price', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->string('verification_token')->nullable()->unique(); // For email verification
            $table->timestamp('email_verified_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('account_created_at')->nullable();
            $table->timestamps();
            
            $table->index('email');
            $table->index('status');
            $table->index('stripe_customer_id');
            $table->index('verification_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waiting_list_entries');
    }
};
