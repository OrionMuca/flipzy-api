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
        Schema::table('waiting_list_entries', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('company_name');
            $table->string('state', 2)->nullable()->after('ip_address');
            
            $table->index('state');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('waiting_list_entries', function (Blueprint $table) {
            $table->dropIndex(['state']);
            $table->dropColumn(['ip_address', 'state']);
        });
    }
};
