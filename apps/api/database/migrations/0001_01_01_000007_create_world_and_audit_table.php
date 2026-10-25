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
        Schema::create('world_configs', function (Blueprint $table) {
            $table->char('id', 26)->charset('ascii')->collation('ascii_bin')->primary();
            $table->char('company_id', 26)->charset('ascii')->collation('ascii_bin');
            $table->char('branch_id', 26)->charset('ascii')->collation('ascii_bin')->nullable();
            $table->char('department_id', 26)->charset('ascii')->collation('ascii_bin')->nullable();
            $table->enum('entity_type', ['company', 'branch', 'department']);
            $table->decimal('position_x', 10, 3)->default(0);
            $table->decimal('position_y', 10, 3)->default(0);
            $table->decimal('position_z', 10, 3)->default(0);
            $table->decimal('rotation_y', 10, 3)->default(0);
            $table->decimal('scale', 8, 3)->default(1);
            $table->unsignedInteger('layout_version')->default(1);
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->unique(['company_id', 'id'], 'uq_world_config_company_id');
            $table->unique(['company_id', 'branch_id'], 'uq_world_config_branch');
            $table->unique(['company_id', 'department_id'], 'uq_world_config_department');

            $table->foreign('company_id', 'fk_world_config_company')->references('id')->on('companies')->onDelete('restrict');
            $table->foreign(['company_id', 'branch_id'], 'fk_world_config_branch_same_company')->references(['company_id', 'id'])->on('branches')->onDelete('restrict');
            $table->foreign(['company_id', 'department_id'], 'fk_world_config_dept_same_company')->references(['company_id', 'id'])->on('departments')->onDelete('restrict');
        });

        Schema::create('audit_events', function (Blueprint $table) {
            $table->char('id', 26)->charset('ascii')->collation('ascii_bin')->primary();
            $table->char('company_id', 26)->charset('ascii')->collation('ascii_bin');
            $table->char('actor_membership_id', 26)->charset('ascii')->collation('ascii_bin')->nullable();
            $table->string('action', 100);
            $table->string('resource_type', 100);
            $table->char('resource_id', 26)->charset('ascii')->collation('ascii_bin')->nullable();
            $table->string('request_id', 100)->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('created_at', 6)->index();

            $table->index(['company_id', 'created_at'], 'ix_audit_company_time');
            $table->index(['company_id', 'resource_type', 'resource_id', 'created_at'], 'ix_audit_resource');

            $table->foreign('company_id', 'fk_audit_company')->references('id')->on('companies')->onDelete('restrict');
            $table->foreign(['company_id', 'actor_membership_id'], 'fk_audit_actor_same_company')->references(['company_id', 'id'])->on('company_user')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('world_configs');
    }
};
