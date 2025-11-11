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
        Schema::create('coupons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('discount_type', ['percentage', 'fixed_amount']);
            $table->decimal('discount_value', 10, 2); // Percentage (0-100) or fixed amount
            $table->decimal('minimum_amount', 10, 2)->nullable();
            $table->decimal('maximum_discount', 10, 2)->nullable(); // Max discount for percentage types
            $table->timestamp('valid_from');
            $table->timestamp('valid_until')->nullable();
            $table->integer('usage_limit')->nullable(); // Total uses allowed (null = unlimited)
            $table->integer('usage_count')->default(0);
            $table->integer('user_limit')->default(1); // Uses per user/email
            $table->boolean('is_active')->default(true);
            $table->json('applicable_plans')->nullable(); // Array of plan IDs (null = all plans)
            $table->timestamps();
            
            $table->index('code');
            $table->index('is_active');
            $table->index(['valid_from', 'valid_until']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
