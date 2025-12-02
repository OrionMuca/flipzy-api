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
        Schema::table('properties', function (Blueprint $table) {
            // Add comprehensive ATTOM data columns
            $table->json('attom_sale_history')->nullable()->after('attom_data');
            $table->json('attom_comparable_sales')->nullable()->after('attom_sale_history');
            $table->json('attom_property_events')->nullable()->after('attom_comparable_sales');
            $table->json('attom_enrichment_status')->nullable()->after('attom_property_events');
            $table->timestamp('attom_enriched_at')->nullable()->after('attom_enrichment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn([
                'attom_sale_history',
                'attom_comparable_sales',
                'attom_property_events',
                'attom_enrichment_status',
                'attom_enriched_at',
            ]);
        });
    }
};
