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
        // Only add columns if they don't exist (for backward compatibility with existing databases)
        Schema::table('waiting_list_entries', function (Blueprint $table) {
            if (!Schema::hasColumn('waiting_list_entries', 'phone_number')) {
                $table->string('phone_number')->nullable()->after('name');
            }
            if (!Schema::hasColumn('waiting_list_entries', 'company_name')) {
                $table->string('company_name')->nullable()->after('phone_number');
            }
            if (!Schema::hasColumn('waiting_list_entries', 'selected_roles')) {
                $table->json('selected_roles')->nullable()->after('company_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('waiting_list_entries', function (Blueprint $table) {
            $table->dropColumn(['phone_number', 'company_name', 'selected_roles']);
        });
    }
};
