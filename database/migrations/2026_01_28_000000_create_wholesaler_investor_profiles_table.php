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
        Schema::create('wholesaler_investor_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('wholesaler_id')->constrained('users')->onDelete('cascade');

            // Contact information
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('company')->nullable();
            $table->text('notes')->nullable();

            // Location Preferences (mirroring buy_boxes)
            $table->json('preferred_cities')->nullable();
            $table->json('preferred_zip_codes')->nullable();
            $table->json('target_counties')->nullable();
            $table->json('target_neighborhoods')->nullable();
            $table->json('must_have_amenities')->nullable();

            // Property Details
            $table->integer('min_bedrooms')->nullable();
            $table->integer('max_bedrooms')->nullable();
            $table->decimal('min_bathrooms', 4, 2)->nullable();
            $table->decimal('max_bathrooms', 4, 2)->nullable();
            $table->integer('min_square_feet')->nullable();
            $table->integer('max_square_feet')->nullable();
            $table->integer('min_lot_size')->nullable();
            $table->integer('max_lot_size')->nullable();

            // Property Condition
            $table->json('property_conditions')->nullable(); // Turnkey, Retail Ready, Rental Ready

            // Property Type
            $table->json('property_types')->nullable(); // Single-Family, Land, Multifamily, Commercial

            // Additional Considerations
            $table->boolean('has_adu_potential')->nullable();

            // Home Construction
            $table->json('construction_types')->nullable(); // Brick, Stick, Block, Stucco, Other

            // Amenities & Features
            $table->boolean('has_pool')->nullable(); // true = with pool, false = without pool
            $table->boolean('is_waterfront')->nullable(); // true = waterfront, false = non-waterfront

            // Desired Layout
            $table->json('layout_types')->nullable(); // Open floor plan, Traditional, Custom

            // Funding Methods
            $table->json('funding_methods')->nullable(); // Cash, Hard money, Private money, DSCR, Conventional, FHA, VA

            // Rental Investment Criteria
            $table->decimal('min_profit', 12, 2)->nullable();
            $table->decimal('min_roi', 5, 2)->nullable(); // percentage
            $table->decimal('target_cap_rate', 5, 2)->nullable(); // percentage
            $table->decimal('desired_occupancy_rate', 5, 2)->nullable(); // percentage
            $table->decimal('expected_monthly_cash_flow', 12, 2)->nullable();
            $table->decimal('expected_annual_cash_flow', 12, 2)->nullable();

            // Investment Strategies
            $table->json('investment_strategies')->nullable(); // Fix and Flip, Short-Term Rental, etc.

            $table->timestamps();

            // Indexes
            $table->index('wholesaler_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wholesaler_investor_profiles');
    }
};

