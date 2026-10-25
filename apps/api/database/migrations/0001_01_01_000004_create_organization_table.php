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
        Schema::create('branches', function (Blueprint $table) {
            $table->char('id', 26)->charset('ascii')->collation('ascii_bin')->primary();
            $table->char('company_id', 26)->charset('ascii')->collation('ascii_bin');
            $table->string('code', 40);
            $table->string('name', 160);
            $table->string('address', 500)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->enum('status', ['active', 'inactive', 'archived'])->default('active');
            $table->dateTime('archived_at', 6)->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->unique(['company_id', 'code'], 'uq_branch_code');
            $table->unique(['company_id', 'id'], 'uq_branch_company_id');
            $table->index(['company_id', 'status'], 'ix_branch_status');

            $table->foreign('company_id', 'fk_branch_company')->references('id')->on('companies')->onDelete('restrict');
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->char('id', 26)->charset('ascii')->collation('ascii_bin')->primary();
            $table->char('company_id', 26)->charset('ascii')->collation('ascii_bin');
            $table->char('branch_id', 26)->charset('ascii')->collation('ascii_bin')->nullable();
            $table->string('code', 40);
            $table->string('name', 160);
            $table->enum('status', ['active', 'inactive', 'archived'])->default('active');
            $table->dateTime('archived_at', 6)->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            // Generated column for strict scope uniqueness handling MySQL multiple NULLs
            $table->string('scope_key', 191)->virtualAs("CONCAT(company_id, ':', COALESCE(branch_id, '__GLOBAL__'), ':', code)");

            $table->unique('scope_key', 'uq_department_scope_key');
            $table->unique(['company_id', 'id'], 'uq_department_company_id');
            $table->index(['company_id', 'branch_id', 'status'], 'ix_department_lookup');

            $table->foreign('company_id', 'fk_department_company')->references('id')->on('companies')->onDelete('restrict');
            $table->foreign(['company_id', 'branch_id'], 'fk_department_branch_same_company')->references(['company_id', 'id'])->on('branches')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('departments');
        Schema::dropIfExists('branches');
    }
};
