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
        Schema::create('api_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('service'); // attom, estated, mapbox, openai
            $table->string('endpoint');
            $table->string('method', 10)->default('GET'); // GET, POST, etc.
            $table->integer('status_code')->nullable();
            $table->text('request_body')->nullable();
            $table->longText('response_body')->nullable();
            $table->integer('response_time_ms')->nullable(); // Response time in milliseconds
            $table->boolean('success')->default(true);
            $table->text('error_message')->nullable();
            $table->foreignUuid('property_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignUuid('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index(['service', 'created_at']);
            $table->index(['success', 'created_at']);
            $table->index('property_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_logs');
    }
};
