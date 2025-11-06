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
        Schema::create('properties', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('wholesaler_id')->constrained('users')->onDelete('cascade');
            
            // Basic Information
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('property_type')->nullable(); // house, condo, townhouse, etc.
            $table->string('status')->default('active'); // active, pending, sold, inactive
            
            // Address Information
            $table->string('address');
            $table->string('city');
            $table->string('state', 2); // US state code
            $table->string('zip_code', 10);
            $table->string('country', 2)->default('US');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            
            // Property Details
            $table->integer('bedrooms')->nullable();
            $table->integer('bathrooms')->nullable();
            $table->integer('square_feet')->nullable();
            $table->integer('lot_size')->nullable(); // in square feet
            $table->integer('year_built')->nullable();
            $table->string('condition')->nullable(); // excellent, good, fair, poor
            
            // Financial Information
            $table->decimal('asking_price', 12, 2);
            $table->decimal('arv', 12, 2)->nullable(); // After Repair Value
            $table->decimal('repair_estimate', 12, 2)->nullable();
            $table->decimal('potential_profit', 12, 2)->nullable();
            
            // External API Data (from ATTOM, Estated, etc.)
            $table->json('attom_data')->nullable(); // Store full ATTOM response
            $table->json('estated_data')->nullable(); // Store Estated backup data
            $table->timestamp('enriched_at')->nullable(); // When data was last enriched
            
            // Flags
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->boolean('allow_inquiries')->default(true);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['wholesaler_id', 'status']);
            $table->index(['city', 'state']);
            $table->index('asking_price');
            $table->index('property_type');
            $table->fullText(['title', 'description', 'address']); // For search
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
