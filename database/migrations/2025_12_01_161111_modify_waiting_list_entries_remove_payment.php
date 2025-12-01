<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Note: This migration is only needed for existing databases that have payment fields.
     * For fresh staging deployments, the create_waiting_list_entries_table migration
     * already creates the table in the correct state without payment fields.
     */
    public function up(): void
    {
        // Check if payment columns exist before trying to drop them
        // This makes the migration safe for both fresh and existing databases
        $columns = Schema::getColumnListing('waiting_list_entries');
        $hasPaymentFields = in_array('stripe_customer_id', $columns) || 
                           in_array('original_price', $columns);

        if ($hasPaymentFields) {
            // Drop foreign key constraint first (if it exists)
            try {
                Schema::table('waiting_list_entries', function (Blueprint $table) {
                    $table->dropForeign(['subscription_plan_id']);
                });
            } catch (\Exception $e) {
                // Foreign key might not exist, continue
            }

            Schema::table('waiting_list_entries', function (Blueprint $table) use ($columns) {
                // Make subscription_plan_id nullable (if it exists and is not already nullable)
                if (in_array('subscription_plan_id', $columns)) {
                    $table->uuid('subscription_plan_id')->nullable()->change();
                }
                
                // Remove payment-related fields (only if they exist)
                $fieldsToDrop = [];
                $paymentFields = [
                    'stripe_customer_id',
                    'stripe_subscription_id',
                    'stripe_checkout_session_id',
                    'original_price',
                    'discounted_price',
                    'discount_amount',
                ];
                
                foreach ($paymentFields as $field) {
                    if (in_array($field, $columns)) {
                        $fieldsToDrop[] = $field;
                    }
                }
                
                if (!empty($fieldsToDrop)) {
                    $table->dropColumn($fieldsToDrop);
                }
            });

            // Update status enum to remove 'payment_completed'
            // Convert existing 'payment_completed' entries to 'pending'
            DB::table('waiting_list_entries')
                ->where('status', 'payment_completed')
                ->update(['status' => 'pending']);

            // Modify status enum column (only if payment_completed was in the enum)
            try {
                DB::statement("ALTER TABLE waiting_list_entries MODIFY COLUMN status ENUM('pending', 'account_created', 'cancelled') DEFAULT 'pending'");
            } catch (\Exception $e) {
                // Status enum might already be correct, continue
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back payment fields
        Schema::table('waiting_list_entries', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->after('status');
            $table->string('stripe_subscription_id')->nullable()->after('stripe_customer_id');
            $table->string('stripe_checkout_session_id')->nullable()->after('stripe_subscription_id');
            $table->decimal('original_price', 10, 2)->after('stripe_checkout_session_id');
            $table->decimal('discounted_price', 10, 2)->after('original_price');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('discounted_price');
            
            $table->index('stripe_customer_id');
            
            // Restore foreign key constraint
            $table->foreign('subscription_plan_id')->references('id')->on('subscription_plans')->onDelete('cascade');
        });

        // Restore status enum with payment_completed
        DB::statement("ALTER TABLE waiting_list_entries MODIFY COLUMN status ENUM('pending', 'payment_completed', 'account_created', 'cancelled') DEFAULT 'pending'");
    }
};
