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
        // Add additional performance indexes for users table
        Schema::table('users', function (Blueprint $table) {
            // Composite index for admin queries with filtering
            $table->index(['deleted_at', 'created_at', 'name'], 'users_admin_filter_idx');
            
            // Index for email domain analysis
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE users ADD INDEX users_email_domain_idx ((SUBSTRING_INDEX(email, "@", -1)))');
            }
        });

        // Add additional performance indexes for salaries table
        Schema::table('salaries', function (Blueprint $table) {
            // Composite index for salary range queries
            $table->index(['salary_euros', 'commission', 'user_id'], 'salaries_range_query_idx');
            
            // Index for currency-based filtering and sorting
            $table->index(['local_currency_code', 'salary_euros', 'created_at'], 'salaries_currency_sort_idx');
            
            // Index for displayed salary calculations and sorting
            $table->index(['displayed_salary', 'effective_date'], 'salaries_display_date_idx');
            
            // Index for commission analysis
            $table->index(['commission', 'salary_euros'], 'salaries_commission_analysis_idx');
            
            // Partial index for high earners (MySQL/PostgreSQL)
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE salaries ADD INDEX salaries_high_earners_idx (salary_euros, user_id) WHERE salary_euros > 75000');
            } elseif (DB::getDriverName() === 'pgsql') {
                DB::statement('CREATE INDEX CONCURRENTLY salaries_high_earners_idx ON salaries (salary_euros, user_id) WHERE salary_euros > 75000');
            }
        });

        // Add additional performance indexes for salary_histories table
        Schema::table('salary_histories', function (Blueprint $table) {
            // Composite index for audit trail queries
            $table->index(['user_id', 'created_at', 'change_type'], 'salary_histories_audit_idx');
            
            // Index for change analysis queries
            $table->index(['created_at', 'old_salary_euros', 'new_salary_euros'], 'salary_histories_change_analysis_idx');
            
            // Index for user activity tracking
            $table->index(['changed_by', 'created_at', 'user_id'], 'salary_histories_activity_idx');
            
            // Index for salary increase/decrease analysis
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE salary_histories ADD INDEX salary_histories_increases_idx (user_id, created_at) WHERE new_salary_euros > old_salary_euros');
                DB::statement('ALTER TABLE salary_histories ADD INDEX salary_histories_decreases_idx (user_id, created_at) WHERE new_salary_euros < old_salary_euros');
            }
        });

        // Add additional performance indexes for uploaded_documents table if it exists
        if (Schema::hasTable('uploaded_documents')) {
            Schema::table('uploaded_documents', function (Blueprint $table) {
                // Composite index for document management queries
                $table->index(['user_id', 'document_type', 'created_at'], 'documents_management_idx');
                
                // Index for file size analysis
                $table->index(['file_size', 'mime_type'], 'documents_size_analysis_idx');
                
                // Index for verification workflow
                $table->index(['is_verified', 'verified_at', 'verified_by'], 'documents_verification_idx');
                
                // Index for cleanup operations
                $table->index(['created_at', 'deleted_at'], 'documents_cleanup_idx');
            });
        }

        // Create covering indexes for common query patterns
        $this->createCoveringIndexes();
        
        // Create functional indexes for computed columns
        $this->createFunctionalIndexes();
        
        // Update table statistics after index creation
        $this->updateTableStatistics();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes from users table
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_admin_filter_idx');
        });
        
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE users DROP INDEX users_email_domain_idx');
        }

        // Drop indexes from salaries table
        Schema::table('salaries', function (Blueprint $table) {
            $table->dropIndex('salaries_range_query_idx');
            $table->dropIndex('salaries_currency_sort_idx');
            $table->dropIndex('salaries_display_date_idx');
            $table->dropIndex('salaries_commission_analysis_idx');
        });
        
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE salaries DROP INDEX salaries_high_earners_idx');
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS salaries_high_earners_idx');
        }

        // Drop indexes from salary_histories table
        Schema::table('salary_histories', function (Blueprint $table) {
            $table->dropIndex('salary_histories_audit_idx');
            $table->dropIndex('salary_histories_change_analysis_idx');
            $table->dropIndex('salary_histories_activity_idx');
        });
        
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE salary_histories DROP INDEX salary_histories_increases_idx');
            DB::statement('ALTER TABLE salary_histories DROP INDEX salary_histories_decreases_idx');
        }

        // Drop indexes from uploaded_documents table if it exists
        if (Schema::hasTable('uploaded_documents')) {
            Schema::table('uploaded_documents', function (Blueprint $table) {
                $table->dropIndex('documents_management_idx');
                $table->dropIndex('documents_size_analysis_idx');
                $table->dropIndex('documents_verification_idx');
                $table->dropIndex('documents_cleanup_idx');
            });
        }

        // Drop covering indexes
        $this->dropCoveringIndexes();
        
        // Drop functional indexes
        $this->dropFunctionalIndexes();
    }

    /**
     * Create covering indexes for common query patterns.
     */
    private function createCoveringIndexes(): void
    {
        $driver = DB::getDriverName();
        
        if ($driver === 'mysql') {
            // Covering index for user list with salary info
            DB::statement('
                ALTER TABLE users ADD INDEX users_salary_covering_idx 
                (id, name, email, created_at) 
                INCLUDE (updated_at, deleted_at)
            ');
            
            // Covering index for salary statistics
            DB::statement('
                ALTER TABLE salaries ADD INDEX salaries_stats_covering_idx 
                (local_currency_code, salary_euros, commission) 
                INCLUDE (user_id, created_at, effective_date)
            ');
        } elseif ($driver === 'pgsql') {
            // PostgreSQL covering indexes
            DB::statement('
                CREATE INDEX CONCURRENTLY users_salary_covering_idx 
                ON users (id, name, email, created_at) 
                INCLUDE (updated_at, deleted_at)
            ');
            
            DB::statement('
                CREATE INDEX CONCURRENTLY salaries_stats_covering_idx 
                ON salaries (local_currency_code, salary_euros, commission) 
                INCLUDE (user_id, created_at, effective_date)
            ');
        }
    }

    /**
     * Create functional indexes for computed values.
     */
    private function createFunctionalIndexes(): void
    {
        $driver = DB::getDriverName();
        
        if ($driver === 'mysql') {
            // Functional index for salary percentage calculations
            DB::statement('
                ALTER TABLE salaries ADD INDEX salaries_commission_percentage_idx 
                ((commission / salary_euros * 100))
            ');
            
            // Functional index for year-based queries
            DB::statement('
                ALTER TABLE salary_histories ADD INDEX salary_histories_year_idx 
                ((YEAR(created_at)))
            ');
        } elseif ($driver === 'pgsql') {
            // PostgreSQL functional indexes
            DB::statement('
                CREATE INDEX CONCURRENTLY salaries_commission_percentage_idx 
                ON salaries ((commission / salary_euros * 100))
            ');
            
            DB::statement('
                CREATE INDEX CONCURRENTLY salary_histories_year_idx 
                ON salary_histories ((EXTRACT(YEAR FROM created_at)))
            ');
        }
    }

    /**
     * Drop covering indexes.
     */
    private function dropCoveringIndexes(): void
    {
        $driver = DB::getDriverName();
        
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE users DROP INDEX users_salary_covering_idx');
            DB::statement('ALTER TABLE salaries DROP INDEX salaries_stats_covering_idx');
        } elseif ($driver === 'pgsql') {
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS users_salary_covering_idx');
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS salaries_stats_covering_idx');
        }
    }

    /**
     * Drop functional indexes.
     */
    private function dropFunctionalIndexes(): void
    {
        $driver = DB::getDriverName();
        
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE salaries DROP INDEX salaries_commission_percentage_idx');
            DB::statement('ALTER TABLE salary_histories DROP INDEX salary_histories_year_idx');
        } elseif ($driver === 'pgsql') {
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS salaries_commission_percentage_idx');
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS salary_histories_year_idx');
        }
    }

    /**
     * Update table statistics after index creation.
     */
    private function updateTableStatistics(): void
    {
        $driver = DB::getDriverName();
        $tables = ['users', 'salaries', 'salary_histories'];
        
        if (Schema::hasTable('uploaded_documents')) {
            $tables[] = 'uploaded_documents';
        }
        
        switch ($driver) {
            case 'mysql':
                foreach ($tables as $table) {
                    DB::statement("ANALYZE TABLE {$table}");
                }
                break;
                
            case 'pgsql':
                foreach ($tables as $table) {
                    DB::statement("ANALYZE {$table}");
                }
                break;
                
            case 'sqlite':
                DB::statement('ANALYZE');
                break;
        }
    }
};