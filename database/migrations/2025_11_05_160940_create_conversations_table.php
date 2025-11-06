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
        Schema::dropIfExists('conversations');
        Schema::create('conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('property_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignUuid('participant_one_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('participant_two_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            
            // Ensure unique conversations between two users
            $table->unique(['participant_one_id', 'participant_two_id', 'property_id'], 'conv_participants_property_unique');
            $table->index(['participant_one_id', 'participant_two_id'], 'conv_participants_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
