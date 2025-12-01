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
            $table->uuid('subscription_plan_id')->nullable(); // Made nullable, no foreign key constraint
            $table->uuid('coupon_id')->nullable();
            $table->string('coupon_code')->nullable(); // Store the code used for reference
            $table->enum('status', ['pending', 'account_created', 'cancelled'])->default('pending');
            $table->string('verification_token')->nullable()->unique(); // For email verification
            $table->timestamp('email_verified_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('account_created_at')->nullable();
            $table->timestamps();
            
            $table->index('email');
            $table->index('status');
            $table->index('verification_token');
            
            // Add foreign key for coupon_id (consolidated from separate migration)
            $table->foreign('coupon_id')->references('id')->on('coupons')->onDelete('set null');
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
