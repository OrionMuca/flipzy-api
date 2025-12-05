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
        // Only add foreign key if coupons table exists
        if (Schema::hasTable('coupons') && Schema::hasTable('waiting_list_entries')) {
            try {
                Schema::table('waiting_list_entries', function (Blueprint $table) {
                    $table->foreign('coupon_id')->references('id')->on('coupons')->onDelete('set null');
                });
            } catch (\Exception $e) {
                // Foreign key might already exist, skip if it does
                if (strpos($e->getMessage(), 'Duplicate key name') === false && 
                    strpos($e->getMessage(), 'already exists') === false) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('waiting_list_entries', function (Blueprint $table) {
            $table->dropForeign(['coupon_id']);
        });
    }
};
