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
        Schema::create('property_images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('property_id')->constrained()->onDelete('cascade');
            $table->string('path'); // Storage path (S3 or local)
            $table->string('url')->nullable(); // Full URL
            $table->string('type')->default('image'); // image, thumbnail, floor_plan
            $table->integer('order')->default(0); // For sorting
            $table->boolean('is_primary')->default(false);
            $table->text('alt_text')->nullable();
            $table->timestamps();
            
            $table->index('property_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_images');
    }
};
