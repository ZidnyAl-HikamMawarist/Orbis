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
        Schema::create('companies', function (Blueprint $table) {
            $table->char('id', 26)->charset('ascii')->collation('ascii_bin')->primary();
            $table->string('name', 160);
            $table->string('slug', 100)->unique('uq_companies_slug');
            $table->string('timezone', 64)->default('Asia/Jakarta');
            $table->enum('status', ['active', 'suspended', 'archived'])->default('active')->index('ix_companies_status');
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });

        Schema::create('company_user', function (Blueprint $table) {
            $table->char('id', 26)->charset('ascii')->collation('ascii_bin')->primary();
            $table->char('company_id', 26)->charset('ascii')->collation('ascii_bin');
            $table->char('user_id', 26)->charset('ascii')->collation('ascii_bin');
            $table->enum('role', [
                'owner',
                'company_admin',
                'operations_manager',
                'branch_manager',
                'department_lead',
                'staff',
                'auditor'
            ]);
            $table->enum('status', ['invited', 'active', 'inactive'])->default('active');
            $table->char('invited_by_membership_id', 26)->charset('ascii')->collation('ascii_bin')->nullable();
            $table->dateTime('joined_at', 6)->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->unique(['company_id', 'user_id'], 'uq_company_user');
            $table->unique(['company_id', 'id'], 'uq_membership_company_id');
            $table->index(['user_id', 'status'], 'ix_membership_user_status');

            $table->foreign('company_id', 'fk_company_user_company')->references('id')->on('companies')->onDelete('restrict');
            $table->foreign('user_id', 'fk_company_user_user')->references('id')->on('users')->onDelete('restrict');
            $table->foreign(['company_id', 'invited_by_membership_id'], 'fk_membership_inviter_same_company')->references(['company_id', 'id'])->on('company_user')->onDelete('restrict');
        });

        Schema::create('company_invitations', function (Blueprint $table) {
            $table->char('id', 26)->charset('ascii')->collation('ascii_bin')->primary();
            $table->char('company_id', 26)->charset('ascii')->collation('ascii_bin');
            $table->string('email', 254);
            $table->enum('role', [
                'owner',
                'company_admin',
                'operations_manager',
                'branch_manager',
                'department_lead',
                'staff',
                'auditor'
            ])->default('staff');
            $table->char('branch_id', 26)->charset('ascii')->collation('ascii_bin')->nullable();
            $table->char('department_id', 26)->charset('ascii')->collation('ascii_bin')->nullable();
            $table->char('token_hash', 64)->charset('ascii')->collation('ascii_bin')->unique('uq_invitation_token_hash');
            $table->char('invited_by_membership_id', 26)->charset('ascii')->collation('ascii_bin');
            $table->enum('status', ['pending', 'accepted', 'revoked', 'expired'])->default('pending');
            $table->dateTime('expires_at', 6);
            $table->dateTime('accepted_at', 6)->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->index(['company_id', 'email', 'status'], 'ix_invitation_lookup');

            $table->foreign('company_id', 'fk_invitation_company')->references('id')->on('companies')->onDelete('restrict');
            $table->foreign(['company_id', 'invited_by_membership_id'], 'fk_invitation_inviter_same_company')->references(['company_id', 'id'])->on('company_user')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_invitations');
        Schema::dropIfExists('company_user');
        Schema::dropIfExists('companies');
    }
};
