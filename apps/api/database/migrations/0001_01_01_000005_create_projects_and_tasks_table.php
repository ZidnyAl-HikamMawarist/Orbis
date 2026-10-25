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
        Schema::create('projects', function (Blueprint $table) {
            $table->char('id', 26)->charset('ascii')->collation('ascii_bin')->primary();
            $table->char('company_id', 26)->charset('ascii')->collation('ascii_bin');
            $table->char('branch_id', 26)->charset('ascii')->collation('ascii_bin')->nullable();
            $table->char('department_id', 26)->charset('ascii')->collation('ascii_bin')->nullable();
            $table->char('lead_membership_id', 26)->charset('ascii')->collation('ascii_bin')->nullable();
            $table->string('code', 40);
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->enum('status', ['planned', 'active', 'on_hold', 'completed', 'cancelled', 'archived'])->default('planned');
            $table->enum('progress_mode', ['task_derived'])->default('task_derived');
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->dateTime('archived_at', 6)->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->unique(['company_id', 'code'], 'uq_project_code');
            $table->unique(['company_id', 'id'], 'uq_project_company_id');
            $table->index(['company_id', 'status', 'due_date'], 'ix_project_status_due');

            $table->foreign('company_id', 'fk_project_company')->references('id')->on('companies')->onDelete('restrict');
            $table->foreign(['company_id', 'branch_id'], 'fk_project_branch_same_company')->references(['company_id', 'id'])->on('branches')->onDelete('restrict');
            $table->foreign(['company_id', 'department_id'], 'fk_project_department_same_company')->references(['company_id', 'id'])->on('departments')->onDelete('restrict');
            $table->foreign(['company_id', 'lead_membership_id'], 'fk_project_lead_same_company')->references(['company_id', 'id'])->on('company_user')->onDelete('restrict');
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->char('id', 26)->charset('ascii')->collation('ascii_bin')->primary();
            $table->char('company_id', 26)->charset('ascii')->collation('ascii_bin');
            $table->char('branch_id', 26)->charset('ascii')->collation('ascii_bin')->nullable();
            $table->char('department_id', 26)->charset('ascii')->collation('ascii_bin')->nullable();
            $table->char('project_id', 26)->charset('ascii')->collation('ascii_bin')->nullable();
            $table->char('creator_membership_id', 26)->charset('ascii')->collation('ascii_bin');
            $table->char('assignee_membership_id', 26)->charset('ascii')->collation('ascii_bin')->nullable();
            $table->string('code', 40);
            $table->string('title', 200);
            $table->mediumText('description')->nullable();
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->enum('status', ['backlog', 'todo', 'in_progress', 'blocked', 'done', 'cancelled'])->default('todo');
            $table->dateTime('due_at', 6)->nullable();
            $table->dateTime('completed_at', 6)->nullable();
            $table->dateTime('archived_at', 6)->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->unique(['company_id', 'code'], 'uq_task_code');
            $table->unique(['company_id', 'id'], 'uq_task_company_id');
            $table->index(['company_id', 'status', 'due_at'], 'ix_task_status_due');
            $table->index(['company_id', 'project_id', 'status'], 'ix_task_project_status');
            $table->index(['company_id', 'assignee_membership_id', 'status'], 'ix_task_assignee_status');
            $table->index(['company_id', 'branch_id', 'department_id', 'status'], 'ix_task_org_scope');

            $table->foreign('company_id', 'fk_task_company')->references('id')->on('companies')->onDelete('restrict');
            $table->foreign(['company_id', 'branch_id'], 'fk_task_branch_same_company')->references(['company_id', 'id'])->on('branches')->onDelete('restrict');
            $table->foreign(['company_id', 'department_id'], 'fk_task_department_same_company')->references(['company_id', 'id'])->on('departments')->onDelete('restrict');
            $table->foreign(['company_id', 'project_id'], 'fk_task_project_same_company')->references(['company_id', 'id'])->on('projects')->onDelete('restrict');
            $table->foreign(['company_id', 'creator_membership_id'], 'fk_task_creator_same_company')->references(['company_id', 'id'])->on('company_user')->onDelete('restrict');
            $table->foreign(['company_id', 'assignee_membership_id'], 'fk_task_assignee_same_company')->references(['company_id', 'id'])->on('company_user')->onDelete('restrict');
        });

        Schema::create('task_status_history', function (Blueprint $table) {
            $table->char('id', 26)->charset('ascii')->collation('ascii_bin')->primary();
            $table->char('company_id', 26)->charset('ascii')->collation('ascii_bin');
            $table->char('task_id', 26)->charset('ascii')->collation('ascii_bin');
            $table->char('actor_membership_id', 26)->charset('ascii')->collation('ascii_bin');
            $table->enum('from_status', ['backlog', 'todo', 'in_progress', 'blocked', 'done', 'cancelled'])->nullable();
            $table->enum('to_status', ['backlog', 'todo', 'in_progress', 'blocked', 'done', 'cancelled']);
            $table->string('note', 500)->nullable();
            $table->dateTime('created_at', 6)->index();

            $table->unique(['company_id', 'id'], 'uq_history_company_id');
            $table->index(['company_id', 'task_id', 'created_at'], 'ix_history_task_timeline');

            $table->foreign('company_id', 'fk_history_company')->references('id')->on('companies')->onDelete('restrict');
            $table->foreign(['company_id', 'task_id'], 'fk_history_task_same_company')->references(['company_id', 'id'])->on('tasks')->onDelete('restrict');
            $table->foreign(['company_id', 'actor_membership_id'], 'fk_history_actor_same_company')->references(['company_id', 'id'])->on('company_user')->onDelete('restrict');
        });

        Schema::create('task_comments', function (Blueprint $table) {
            $table->char('id', 26)->charset('ascii')->collation('ascii_bin')->primary();
            $table->char('company_id', 26)->charset('ascii')->collation('ascii_bin');
            $table->char('task_id', 26)->charset('ascii')->collation('ascii_bin');
            $table->char('author_membership_id', 26)->charset('ascii')->collation('ascii_bin');
            $table->text('body');
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->unique(['company_id', 'id'], 'uq_comment_company_id');
            $table->index(['company_id', 'task_id', 'created_at'], 'ix_comment_task_timeline');

            $table->foreign('company_id', 'fk_comment_company')->references('id')->on('companies')->onDelete('restrict');
            $table->foreign(['company_id', 'task_id'], 'fk_comment_task_same_company')->references(['company_id', 'id'])->on('tasks')->onDelete('cascade');
            $table->foreign(['company_id', 'author_membership_id'], 'fk_comment_author_same_company')->references(['company_id', 'id'])->on('company_user')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_comments');
        Schema::dropIfExists('task_status_history');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('projects');
    }
};
