<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds composite indexes (company_id, archived_at, created_at) to eliminate MySQL filesort
     * on default paginated task and project listing endpoints.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->index(['company_id', 'archived_at', 'created_at'], 'ix_task_listing_created');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->index(['company_id', 'archived_at', 'created_at'], 'ix_project_listing_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex('ix_project_listing_created');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('ix_task_listing_created');
        });
    }
};
