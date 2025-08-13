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
        // Optimize users table
        Schema::table('users', function (Blueprint $table) {
            // Add composite indexes for common query patterns
            $table->index(['email', 'created_at'], 'users_email_created_idx');
            $table->index(['name', 'email'], 'users_name_email_idx');
            $table->index(['deleted_at', 'created_at'], 'users_deleted_created_idx');
            
            // Add full-text index for name search (MySQL specific)
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE users ADD FULLTEXT(name)');
            }
        });

        // Optimize salaries table
        Schema::table('salaries', function (Blueprint $table) {
            // Add composite indexes for admin queries
            $table->index(['salary_euros', 'created_at'], 'salaries_euros_created_idx');
            $table->index(['commission', 'effective_date'], 'salaries_commission_date_idx');
            $table->index(['local_currency_code', 'salary_local_currency'], 'salaries_currency_amount_idx');
            $table->index(['effective_date', 'user_id'], 'salaries_date_user_idx');
            
            // Add index for displayed salary calculations
            $table->index(['displayed_salary', 'created_at'], 'salaries_displayed_created_idx');
        });

        // Optimize salary_histories table
        Schema::table('salary_histories', function (Blueprint $table) {
            // Add composite indexes for audit queries
            $table->index(['user_id', 'changed_at', 'action'], 'salary_histories_user_changed_action_idx');
            $table->index(['changed_by', 'changed_at'], 'salary_histories_changer_date_idx');
            $table->index(['salary_id', 'action', 'changed_at'], 'salary_histories_salary_action_date_idx');
            
            // Add index for change reason searches
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE salary_histories ADD FULLTEXT(change_reason)');
            }
        });

        // Optimize uploaded_documents table if it exists
        if (Schema::hasTable('uploaded_documents')) {
            Schema::table('uploaded_documents', function (Blueprint $table) {
                // Add composite indexes for file management
                $table->index(['user_id', 'created_at'], 'documents_user_created_idx');
                $table->index(['file_type', 'file_size'], 'documents_type_size_idx');
                $table->index(['is_verified', 'created_at'], 'documents_verified_created_idx');
            });
        }

        // Create database views for common queries
        $this->createPerformanceViews();
        
        // Set up database-specific optimizations
        $this->optimizeForDatabase();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop performance views
        DB::statement('DROP VIEW IF EXISTS user_salary_summary');
        DB::statement('DROP VIEW IF EXISTS salary_statistics');
        DB::statement('DROP VIEW IF EXISTS recent_salary_changes');

        // Drop indexes from users table
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_email_created_idx');
            $table->dropIndex('users_name_email_idx');
            $table->dropIndex('users_deleted_created_idx');
        });

        // Drop full-text indexes
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE users DROP INDEX name');
            if (Schema::hasTable('salary_histories')) {
                DB::statement('ALTER TABLE salary_histories DROP INDEX change_reason');
            }
        }

        // Drop indexes from salaries table
        Schema::table('salaries', function (Blueprint $table) {
            $table->dropIndex('salaries_euros_created_idx');
            $table->dropIndex('salaries_commission_date_idx');
            $table->dropIndex('salaries_currency_amount_idx');
            $table->dropIndex('salaries_date_user_idx');
            $table->dropIndex('salaries_displayed_created_idx');
        });

        // Drop indexes from salary_histories table
        Schema::table('salary_histories', function (Blueprint $table) {
            $table->dropIndex('salary_histories_user_changed_action_idx');
            $table->dropIndex('salary_histories_changer_date_idx');
            $table->dropIndex('salary_histories_salary_action_date_idx');
        });

        // Drop indexes from uploaded_documents table if it exists
        if (Schema::hasTable('uploaded_documents')) {
            Schema::table('uploaded_documents', function (Blueprint $table) {
                $table->dropIndex('documents_user_created_idx');
                $table->dropIndex('documents_type_size_idx');
                $table->dropIndex('documents_verified_created_idx');
            });
        }
    }

    /**
     * Create database views for common queries.
     */
    private function createPerformanceViews(): void
    {
        // View for user salary summary (commonly used in admin panel)
        DB::statement("
            CREATE VIEW user_salary_summary AS
            SELECT 
                u.id,
                u.name,
                u.email,
                u.created_at as user_created_at,
                s.salary_local_currency,
                s.local_currency_code,
                s.salary_euros,
                s.commission,
                s.displayed_salary,
                s.effective_date,
                s.updated_at as salary_updated_at,
                CASE 
                    WHEN s.updated_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 
                    ELSE 0 
                END as is_recently_updated
            FROM users u
            LEFT JOIN salaries s ON u.id = s.user_id
            WHERE u.deleted_at IS NULL
        ");

        // View for salary statistics (for dashboard)
        DB::statement("
            CREATE VIEW salary_statistics AS
            SELECT 
                COUNT(*) as total_users,
                COUNT(s.id) as users_with_salary,
                AVG(s.salary_euros) as avg_salary_euros,
                MIN(s.salary_euros) as min_salary_euros,
                MAX(s.salary_euros) as max_salary_euros,
                AVG(s.commission) as avg_commission,
                SUM(s.displayed_salary) as total_compensation,
                COUNT(DISTINCT s.local_currency_code) as currency_count
            FROM users u
            LEFT JOIN salaries s ON u.id = s.user_id
            WHERE u.deleted_at IS NULL
        ");

        // View for recent salary changes (for audit purposes)
        DB::statement("
            CREATE VIEW recent_salary_changes AS
            SELECT 
                sh.id,
                sh.user_id,
                u.name as user_name,
                u.email as user_email,
                sh.salary_id,
                sh.old_values,
                sh.new_values,
                sh.changed_by,
                changer.name as changed_by_name,
                sh.change_reason,
                sh.action,
                sh.changed_at
            FROM salary_histories sh
            JOIN users u ON sh.user_id = u.id
            LEFT JOIN users changer ON sh.changed_by = changer.id
            WHERE sh.changed_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY sh.changed_at DESC
        ");
    }

    /**
     * Apply database-specific optimizations.
     */
    private function optimizeForDatabase(): void
    {
        $driver = DB::getDriverName();

        switch ($driver) {
            case 'mysql':
                $this->optimizeForMySQL();
                break;
            case 'pgsql':
                $this->optimizeForPostgreSQL();
                break;
            case 'sqlite':
                $this->optimizeForSQLite();
                break;
        }
    }

    /**
     * MySQL-specific optimizations.
     */
    private function optimizeForMySQL(): void
    {
        // Set optimal table engine and character set
        DB::statement('ALTER TABLE users ENGINE=InnoDB ROW_FORMAT=DYNAMIC');
        DB::statement('ALTER TABLE salaries ENGINE=InnoDB ROW_FORMAT=DYNAMIC');
        DB::statement('ALTER TABLE salary_histories ENGINE=InnoDB ROW_FORMAT=DYNAMIC');

        // Optimize table statistics
        DB::statement('ANALYZE TABLE users, salaries, salary_histories');

        // Set up query cache optimization hints
        DB::statement("
            SET GLOBAL query_cache_type = ON;
            SET GLOBAL query_cache_size = 268435456;
        ");
    }

    /**
     * PostgreSQL-specific optimizations.
     */
    private function optimizeForPostgreSQL(): void
    {
        // Create partial indexes for soft deletes
        DB::statement('CREATE INDEX CONCURRENTLY users_active_idx ON users (id) WHERE deleted_at IS NULL');
        
        // Update table statistics
        DB::statement('ANALYZE users');
        DB::statement('ANALYZE salaries');
        DB::statement('ANALYZE salary_histories');

        // Create GIN indexes for JSON columns if they exist
        if (Schema::hasColumn('salary_histories', 'old_values')) {
            DB::statement('CREATE INDEX CONCURRENTLY salary_histories_old_values_gin ON salary_histories USING GIN (old_values)');
        }
        if (Schema::hasColumn('salary_histories', 'new_values')) {
            DB::statement('CREATE INDEX CONCURRENTLY salary_histories_new_values_gin ON salary_histories USING GIN (new_values)');
        }
    }

    /**
     * SQLite-specific optimizations.
     */
    private function optimizeForSQLite(): void
    {
        // Enable WAL mode for better concurrency
        DB::statement('PRAGMA journal_mode=WAL');
        
        // Optimize SQLite settings
        DB::statement('PRAGMA synchronous=NORMAL');
        DB::statement('PRAGMA cache_size=10000');
        DB::statement('PRAGMA temp_store=MEMORY');
        
        // Update statistics
        DB::statement('ANALYZE');
    }
};