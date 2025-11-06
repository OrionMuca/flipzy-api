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
        Schema::create('property_rehab_estimates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('property_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('requested_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('ai_response'); // The AI-generated estimate
            $table->json('property_data')->nullable(); // Data sent to AI
            $table->string('model_used')->nullable(); // gpt-4, gpt-3.5-turbo, etc.
            $table->decimal('estimated_cost', 12, 2)->nullable(); // Extracted from AI response if possible
            $table->integer('tokens_used')->nullable();
            $table->timestamps();
            
            $table->index('property_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_rehab_estimates');
    }
};
