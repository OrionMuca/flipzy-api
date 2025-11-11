<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();
        
        if ($driver === 'mysql') {
            // Check if foreign key exists and drop it (MySQL only)
            try {
                $foreignKeys = DB::select("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'oauth_access_tokens' 
                    AND COLUMN_NAME = 'user_id' 
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                ");
                
                foreach ($foreignKeys as $fk) {
                    DB::statement("ALTER TABLE `oauth_access_tokens` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
                }
            } catch (\Exception $e) {
                // Foreign key might not exist, continue
            }
        }

        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            // Drop the index
            try {
                $table->dropIndex(['user_id']);
            } catch (\Exception $e) {
                // Index might not exist
            }
        });

        // Change the column type from bigint to uuid (CHAR(36))
        // Note: For SQLite (tests), this migration is skipped as the original migration
        // has been updated to use foreignUuid from the start
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `oauth_access_tokens` MODIFY `user_id` CHAR(36) NULL');
        }
        // SQLite: Skip - original migration now uses foreignUuid, so fresh migrations will be correct

        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            // Re-add the index
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            // Drop the index
            $table->dropIndex(['user_id']);
        });

        // Change back to bigint
        DB::statement('ALTER TABLE `oauth_access_tokens` MODIFY `user_id` BIGINT UNSIGNED NULL');

        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            // Re-add the index
            $table->index('user_id');
        });
    }
};
