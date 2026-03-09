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
        Schema::table('users', function (Blueprint $table) {
            // Status: unverified, pending, processing, verified, failed
            $table->string('id_verification_status')->default('unverified')->after('email_verified_at');
            // Timestamp when Stripe confirmed the identity as verified
            $table->timestamp('id_verified_at')->nullable()->after('id_verification_status');
            // Stripe VerificationSession ID (vs_...) for webhook matching
            $table->string('stripe_identity_session_id')->nullable()->after('id_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['id_verification_status', 'id_verified_at', 'stripe_identity_session_id']);
        });
    }
};
