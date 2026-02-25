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
        // Helper to check if index exists
        $hasIndex = function ($table, $index) {
            $conn = Schema::getConnection();
            $dbName = $conn->getDatabaseName();
            $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$index]);
            return count($indexes) > 0;
        };

        Schema::table('users', function (Blueprint $table) use ($hasIndex) {
            if (!$hasIndex('users', 'users_role_index')) $table->index('role');
            if (!$hasIndex('users', 'users_is_active_index')) $table->index('is_active');
            if (!$hasIndex('users', 'users_created_at_index')) $table->index('created_at');
        });

        Schema::table('products', function (Blueprint $table) use ($hasIndex) {
            if (!$hasIndex('products', 'products_category_id_index')) $table->index('category_id');
            if (!$hasIndex('products', 'products_is_active_index')) $table->index('is_active');
            if (!$hasIndex('products', 'products_is_featured_index')) $table->index('is_featured');
            if (!$hasIndex('products', 'products_created_at_index')) $table->index('created_at');
        });

        Schema::table('orders', function (Blueprint $table) use ($hasIndex) {
            if (!$hasIndex('orders', 'orders_user_id_index')) $table->index('user_id');
            if (!$hasIndex('orders', 'orders_created_at_index')) $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Don't really need to drop if we are just optimizing, but for completeness:
        try {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['role']);
                $table->dropIndex(['is_active']);
                $table->dropIndex(['created_at']);
            });
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex(['category_id']);
                $table->dropIndex(['is_active']);
                $table->dropIndex(['is_featured']);
                $table->dropIndex(['created_at']);
            });
            Schema::table('orders', function (Blueprint $table) {
                $table->dropIndex(['user_id']);
                $table->dropIndex(['created_at']);
            });
        } catch (\Exception $e) {}
    }
};
