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
            $table->string('zoning_type')->nullable()->after('lot_size');
            $table->string('pool_type')->nullable()->after('zoning_type');
            $table->string('municipality')->nullable()->after('pool_type');
            $table->string('legal1')->nullable()->after('municipality');
            $table->string('cooling_type')->nullable()->after('legal1');
            $table->string('heating_fuel')->nullable()->after('cooling_type');
            $table->string('heating_type')->nullable()->after('heating_fuel');
            $table->date('last_sale_date')->nullable()->after('heating_type');
            $table->integer('living_size')->nullable()->after('last_sale_date');
            $table->integer('gross_size')->nullable()->after('living_size');
            $table->decimal('tax_amount', 12, 2)->nullable()->after('gross_size');
            $table->integer('tax_year')->nullable()->after('tax_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn([
                'zoning_type',
                'pool_type',
                'municipality',
                'legal1',
                'cooling_type',
                'heating_fuel',
                'heating_type',
                'last_sale_date',
                'living_size',
                'gross_size',
                'tax_amount',
                'tax_year',
            ]);
        });
    }
};
