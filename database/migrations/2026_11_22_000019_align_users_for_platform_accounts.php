<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        $this->updateUsers(function (): void {
            $this->addFields();
        });
    }

    private function addFields(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 64)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->string('locale_code', 20)->nullable();
            $table->foreign('locale_code')->references('code')->on('locales')->restrictOnDelete()->restrictOnUpdate();
            $table->timestamp('last_login_at')->nullable();
        });

        foreach (DB::table('users')->select('id')->lazyById() as $user) {
            DB::table('users')->where('id', $user->id)->update(['username' => 'user-'.$user->id]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 64)->nullable(false)->change();
        });

        // Add the enum after SQLite's rebuild so its CHECK constraint stays intact.
        Schema::table('users', function (Blueprint $table) {
            $table->enum('scope', ['platform', 'clinic'])->default('platform');
        });
    }

    public function down(): void
    {
        $this->updateUsers(function (): void {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['locale_code']);
                $table->dropUnique(['username']);
                $table->dropColumn(['username', 'scope', 'is_active', 'locale_code', 'last_login_at']);
            });
        });
    }

    /** @param Closure(): void $operation */
    private function updateUsers(Closure $operation): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // The table rebuild must preserve existing rows in the dependent passkeys table.
            Schema::withoutForeignKeyConstraints(fn () => DB::transaction($operation));

            return;
        }

        DB::transaction($operation);
    }
};
