<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'role_permission_change_requests',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('role_id')
                    ->constrained('roles')
                    ->restrictOnDelete();

                $table->foreignId('permission_id')
                    ->constrained('permissions')
                    ->restrictOnDelete();

                // Allowed operations: assign, revoke.
                $table->string('operation', 8);

                $table->foreignId('requested_by')
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->foreignId('approved_by')
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                // pending, approved, rejected,
                // expired, consumed.
                $table->string('status', 20)
                    ->default('pending');

                $table->text('reason');

                // Binds approval to the exact request.
                $table->char('request_hash', 64)->unique();

                // Set only after server-verified MFA.
                $table->timestamp('owner_mfa_verified_at')
                    ->nullable();

                $table->timestamp('approved_at')
                    ->nullable();

                $table->timestamp('expires_at');

                $table->timestamp('consumed_at')
                    ->nullable();

                $table->timestamps();

                $table->index(
                    ['role_id', 'permission_id', 'status'],
                    'role_perm_changes_target_status_index'
                );

                $table->index(
                    ['approved_by', 'approved_at'],
                    'role_perm_changes_approver_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'role_permission_change_requests'
        );
    }
};
