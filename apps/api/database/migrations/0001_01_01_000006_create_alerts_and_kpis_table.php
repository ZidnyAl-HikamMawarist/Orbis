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
        Schema::create('alerts', function (Blueprint $table) {
            $table->char('id', 26)->charset('ascii')->collation('ascii_bin')->primary();
            $table->char('company_id', 26)->charset('ascii')->collation('ascii_bin');
            $table->char('branch_id', 26)->charset('ascii')->collation('ascii_bin')->nullable();
            $table->char('department_id', 26)->charset('ascii')->collation('ascii_bin')->nullable();
            $table->char('project_id', 26)->charset('ascii')->collation('ascii_bin')->nullable();
            $table->char('task_id', 26)->charset('ascii')->collation('ascii_bin')->nullable();
            $table->string('source', 80);
            $table->string('dedupe_key', 160);
            $table->enum('severity', ['info', 'warning', 'critical'])->default('info');
            $table->enum('status', ['open', 'acknowledged', 'resolved'])->default('open');
            $table->string('title', 200);
            $table->text('message')->nullable();
            $table->dateTime('resolved_at', 6)->nullable();
            $table->dateTime('acknowledged_at', 6)->nullable();
            $table->char('acknowledged_by_membership_id', 26)->charset('ascii')->collation('ascii_bin')->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->unique(['company_id', 'source', 'dedupe_key'], 'uq_alert_dedupe');
            $table->unique(['company_id', 'id'], 'uq_alert_company_id');
            $table->index(['company_id', 'status', 'severity', 'created_at'], 'ix_alert_status_sev');

            $table->foreign('company_id', 'fk_alert_company')->references('id')->on('companies')->onDelete('restrict');
            $table->foreign(['company_id', 'branch_id'], 'fk_alert_branch_same_company')->references(['company_id', 'id'])->on('branches')->onDelete('restrict');
            $table->foreign(['company_id', 'department_id'], 'fk_alert_department_same_company')->references(['company_id', 'id'])->on('departments')->onDelete('restrict');
            $table->foreign(['company_id', 'project_id'], 'fk_alert_project_same_company')->references(['company_id', 'id'])->on('projects')->onDelete('restrict');
            $table->foreign(['company_id', 'task_id'], 'fk_alert_task_same_company')->references(['company_id', 'id'])->on('tasks')->onDelete('cascade');
            $table->foreign(['company_id', 'acknowledged_by_membership_id'], 'fk_alert_ack_same_company')->references(['company_id', 'id'])->on('company_user')->onDelete('restrict');
        });

        Schema::create('kpi_definitions', function (Blueprint $table) {
            $table->char('id', 26)->charset('ascii')->collation('ascii_bin')->primary();
            $table->char('company_id', 26)->charset('ascii')->collation('ascii_bin');
            $table->string('key', 80);
            $table->string('name', 120);
            $table->string('unit', 30);
            $table->unsignedInteger('definition_version')->default(1);
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->unique(['company_id', 'key', 'definition_version'], 'uq_kpi_key_version');
            $table->unique(['company_id', 'id'], 'uq_kpi_definition_company_id');

            $table->foreign('company_id', 'fk_kpi_def_company')->references('id')->on('companies')->onDelete('restrict');
        });

        Schema::create('kpi_snapshots', function (Blueprint $table) {
            $table->char('id', 26)->charset('ascii')->collation('ascii_bin')->primary();
            $table->char('company_id', 26)->charset('ascii')->collation('ascii_bin');
            $table->char('kpi_definition_id', 26)->charset('ascii')->collation('ascii_bin');
            $table->char('branch_id', 26)->charset('ascii')->collation('ascii_bin')->nullable();
            $table->char('department_id', 26)->charset('ascii')->collation('ascii_bin')->nullable();
            $table->dateTime('window_start', 6);
            $table->dateTime('window_end', 6);
            $table->decimal('value_decimal', 20, 6)->nullable();
            $table->json('value_json')->nullable();
            $table->enum('freshness', ['fresh', 'stale', 'unavailable'])->default('fresh');
            $table->dateTime('computed_at', 6);
            $table->dateTime('created_at', 6)->nullable();

            $table->unique(['company_id', 'id'], 'uq_kpi_snapshot_company_id');
            $table->unique(['company_id', 'kpi_definition_id', 'branch_id', 'department_id', 'window_start', 'window_end'], 'uq_kpi_snapshot_window');
            $table->index(['company_id', 'kpi_definition_id', 'window_end'], 'ix_kpi_snapshot_lookup');

            $table->foreign('company_id', 'fk_kpi_snapshot_company')->references('id')->on('companies')->onDelete('restrict');
            $table->foreign(['company_id', 'kpi_definition_id'], 'fk_kpi_snapshot_def_same_company')->references(['company_id', 'id'])->on('kpi_definitions')->onDelete('restrict');
            $table->foreign(['company_id', 'branch_id'], 'fk_kpi_snapshot_branch_same_company')->references(['company_id', 'id'])->on('branches')->onDelete('restrict');
            $table->foreign(['company_id', 'department_id'], 'fk_kpi_snapshot_dept_same_company')->references(['company_id', 'id'])->on('departments')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kpi_snapshots');
        Schema::dropIfExists('kpi_definitions');
        Schema::dropIfExists('alerts');
    }
};
