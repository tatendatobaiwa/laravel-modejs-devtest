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
        // Add additional indexes for search and filter operations
        Schema::table('users', function (Blueprint $table) {
            // Composite index for admin queries with search
            $table->index(['name', 'email', 'created_at'], 'users_search_created_idx');
            
            // Index for soft delete queries
            $table->index(['deleted_at', 'id'], 'users_deleted_id_idx');
            
            // Index for email domain searches (useful for admin filtering)
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE users ADD INDEX users_email_domain_idx ((SUBSTRING_INDEX(email, "@", -1)))');
            }
        });

        Schema::table('salaries', function (Blueprint $table) {
            // Composite indexes for range queries
            $table->index(['salary_euros', 'commission', 'user_id'], 'salaries_euros_commission_user_idx');
            $table->index(['local_currency_code', 'salary_euros', 'created_at'], 'salaries_currency_euros_created_idx');
            
            // Index for displayed salary calculations and sorting
            $table->index(['displayed_salary', 'effective_date'], 'salaries_displayed_effective_idx');
            
            // Index for commission range queries
            $table->index(['commission', 'salary_euros'], 'salaries_commission_euros_idx');
            
            // Index for recent salary updates
            $table->index(['updated_at', 'user_id'], 'salaries_updated_user_idx');
        });

        Schema::table('salary_histories', function (Blueprint $table) {
            // Composite indexes for audit and reporting queries
            $table->index(['user_id', 'created_at', 'change_type'], 'salary_histories_user_created_type_idx');
            $table->index(['changed_by', 'created_at', 'user_id'], 'salary_histories_changer_created_user_idx');
            
            // Index for salary change amount queries
            if (Schema::hasColumn('salary_histories', 'new_salary_euros') && Schema::hasColumn('salary_histories', 'old_salary_euros')) {
                DB::statement('ALTER TABLE salary_histories ADD INDEX salary_histories_change_amount_idx ((new_salary_euros - old_salary_euros))');
            }
            
            // Index for date range queries
            $table->index(['created_at', 'change_type'], 'salary_histories_created_type_idx');
        });

        Schema::table('uploaded_documents', function (Blueprint $table) {
            // Composite indexes for document management
            $table->index(['user_id', 'document_type', 'created_at'], 'documents_user_type_created_idx');
            $table->index(['is_verified', 'verified_at', 'user_id'], 'documents_verified_at_user_idx');
            $table->index(['mime_type', 'file_size'], 'documents_mime_size_idx');
            
            // Index for file integrity checks
            $table->index(['file_hash', 'user_id'], 'documents_hash_user_idx');
            
            // Index for cleanup operations
            $table->index(['deleted_at', 'created_at'], 'documents_deleted_created_idx');
        });

        // Create additional database views for complex queries
        $this->createAdvancedViews();
        
        // Add database-specific optimizations
        $this->addDatabaseSpecificOptimizations();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop advanced views
        DB::statement('DROP VIEW IF EXISTS user_salary_analytics');
        DB::statement('DROP VIEW IF EXISTS salary_change_analytics');
        DB::statement('DROP VIEW IF EXISTS document_analytics');

        // Drop indexes from users table
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_search_created_idx');
            $table->dropIndex('users_deleted_id_idx');
        });

        // Drop email domain index for MySQL
        if (DB::getDriverName() === 'mysql') {
            try {
                DB::statement('ALTER TABLE users DROP INDEX users_email_domain_idx');
            } catch (\Exception $e) {
                // Index might not exist
            }
        }

        // Drop indexes from salaries table
        Schema::table('salaries', function (Blueprint $table) {
            $table->dropIndex('salaries_euros_commission_user_idx');
            $table->dropIndex('salaries_currency_euros_created_idx');
            $table->dropIndex('salaries_displayed_effective_idx');
            $table->dropIndex('salaries_commission_euros_idx');
            $table->dropIndex('salaries_updated_user_idx');
        });

        // Drop indexes from salary_histories table
        Schema::table('salary_histories', function (Blueprint $table) {
            $table->dropIndex('salary_histories_user_created_type_idx');
            $table->dropIndex('salary_histories_changer_created_user_idx');
            $table->dropIndex('salary_histories_created_type_idx');
        });

        // Drop change amount index for MySQL
        if (DB::getDriverName() === 'mysql') {
            try {
                DB::statement('ALTER TABLE salary_histories DROP INDEX salary_histories_change_amount_idx');
            } catch (\Exception $e) {
                // Index might not exist
            }
        }

        // Drop indexes from uploaded_documents table
        Schema::table('uploaded_documents', function (Blueprint $table) {
            $table->dropIndex('documents_user_type_created_idx');
            $table->dropIndex('documents_verified_at_user_idx');
            $table->dropIndex('documents_mime_size_idx');
            $table->dropIndex('documents_hash_user_idx');
            $table->dropIndex('documents_deleted_created_idx');
        });
    }

    /**
     * Create advanced database views for complex analytics.
     */
    private function createAdvancedViews(): void
    {
        // Advanced user salary analytics view
        DB::statement("
            CREATE VIEW user_salary_analytics AS
            SELECT 
                u.id as user_id,
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
                DATEDIFF(NOW(), u.created_at) as account_age_days,
                DATEDIFF(NOW(), s.updated_at) as salary_age_days,
                (s.commission / s.salary_euros * 100) as commission_percentage,
                CASE 
                    WHEN s.salary_euros < 30000 THEN 'Junior'
                    WHEN s.salary_euros BETWEEN 30000 AND 60000 THEN 'Mid-level'
                    WHEN s.salary_euros BETWEEN 60000 AND 100000 THEN 'Senior'
                    ELSE 'Executive'
                END as salary_tier,
                CASE 
                    WHEN s.updated_at > DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 
                    ELSE 0 
                END as recently_updated
            FROM users u
            LEFT JOIN salaries s ON u.id = s.user_id
            WHERE u.deleted_at IS NULL
        ");

        // Salary change analytics view
        DB::statement("
            CREATE VIEW salary_change_analytics AS
            SELECT 
                sh.id,
                sh.user_id,
                u.name as user_name,
                u.email as user_email,
                sh.old_salary_euros,
                sh.new_salary_euros,
                (sh.new_salary_euros - sh.old_salary_euros) as salary_change_amount,
                ((sh.new_salary_euros - sh.old_salary_euros) / sh.old_salary_euros * 100) as salary_change_percentage,
                sh.old_commission,
                sh.new_commission,
                (sh.new_commission - sh.old_commission) as commission_change_amount,
                sh.changed_by,
                changer.name as changed_by_name,
                sh.change_reason,
                sh.change_type,
                sh.created_at as change_date,
                CASE 
                    WHEN sh.new_salary_euros > sh.old_salary_euros THEN 'Increase'
                    WHEN sh.new_salary_euros < sh.old_salary_euros THEN 'Decrease'
                    ELSE 'No Change'
                END as change_direction,
                CASE 
                    WHEN ABS(sh.new_salary_euros - sh.old_salary_euros) > 10000 THEN 'Large'
                    WHEN ABS(sh.new_salary_euros - sh.old_salary_euros) > 5000 THEN 'Medium'
                    ELSE 'Small'
                END as change_magnitude
            FROM salary_histories sh
            JOIN users u ON sh.user_id = u.id
            LEFT JOIN users changer ON sh.changed_by = changer.id
            WHERE sh.old_salary_euros IS NOT NULL AND sh.new_salary_euros IS NOT NULL
        ");

        // Document analytics view
        DB::statement("
            CREATE VIEW document_analytics AS
            SELECT 
                ud.id,
                ud.user_id,
                u.name as user_name,
                u.email as user_email,
                ud.document_type,
                ud.mime_type,
                ud.file_size,
                ud.is_verified,
                ud.verified_at,
                ud.created_at as upload_date,
                DATEDIFF(NOW(), ud.created_at) as days_since_upload,
                CASE 
                    WHEN ud.file_size < 1048576 THEN 'Small (<1MB)'
                    WHEN ud.file_size < 10485760 THEN 'Medium (1-10MB)'
                    ELSE 'Large (>10MB)'
                END as file_size_category,
                CASE 
                    WHEN ud.is_verified = 1 THEN 'Verified'
                    WHEN ud.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 'Pending (Old)'
                    ELSE 'Pending (Recent)'
                END as verification_status
            FROM uploaded_documents ud
            JOIN users u ON ud.user_id = u.id
            WHERE ud.deleted_at IS NULL
        ");
    }

    /**
     * Add database-specific optimizations.
     */
    private function addDatabaseSpecificOptimizations(): void
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
        // Enable query cache for repeated queries
        try {
            DB::statement("SET GLOBAL query_cache_type = ON");
            DB::statement("SET GLOBAL query_cache_size = 268435456"); // 256MB
        } catch (\Exception $e) {
            // Query cache might not be available in newer MySQL versions
        }

        // Optimize table settings for better performance
        DB::statement('ALTER TABLE users ENGINE=InnoDB ROW_FORMAT=DYNAMIC');
        DB::statement('ALTER TABLE salaries ENGINE=InnoDB ROW_FORMAT=DYNAMIC');
        DB::statement('ALTER TABLE salary_histories ENGINE=InnoDB ROW_FORMAT=DYNAMIC');
        DB::statement('ALTER TABLE uploaded_documents ENGINE=InnoDB ROW_FORMAT=DYNAMIC');

        // Update table statistics for better query planning
        DB::statement('ANALYZE TABLE users, salaries, salary_histories, uploaded_documents');

        // Add full-text indexes for better search performance
        try {
            DB::statement('ALTER TABLE users ADD FULLTEXT(name, email)');
            DB::statement('ALTER TABLE salary_histories ADD FULLTEXT(change_reason)');
        } catch (\Exception $e) {
            // Full-text indexes might already exist
        }
    }

    /**
     * PostgreSQL-specific optimizations.
     */
    private function optimizeForPostgreSQL(): void
    {
        // Create partial indexes for better performance
        try {
            DB::statement('CREATE INDEX CONCURRENTLY users_active_with_salary_idx ON users (id, created_at) WHERE deleted_at IS NULL');
            DB::statement('CREATE INDEX CONCURRENTLY salaries_high_earners_idx ON salaries (salary_euros, user_id) WHERE salary_euros > 75000');
            DB::statement('CREATE INDEX CONCURRENTLY salary_histories_recent_idx ON salary_histories (created_at, user_id) WHERE created_at > NOW() - INTERVAL \'30 days\'');
        } catch (\Exception $e) {
            // Indexes might already exist
        }

        // Update statistics
        DB::statement('ANALYZE users');
        DB::statement('ANALYZE salaries');
        DB::statement('ANALYZE salary_histories');
        DB::statement('ANALYZE uploaded_documents');

        // Create GIN indexes for JSON columns if they exist
        try {
            if (Schema::hasColumn('salary_histories', 'metadata')) {
                DB::statement('CREATE INDEX CONCURRENTLY salary_histories_metadata_gin ON salary_histories USING GIN (metadata)');
            }
        } catch (\Exception $e) {
            // Index might already exist
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
        DB::statement('PRAGMA mmap_size=268435456'); // 256MB
        
        // Update statistics
        DB::statement('ANALYZE');
    }
};