<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** P03.04 database rules for roles. */
class RoleSchemaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertTrue(app()->environment('testing'));
        $this->assertSame(':memory:', DB::connection()->getConfig('database'));
    }

    public function test_six_columns_with_only_clinic_id_nullable(): void
    {
        $columns = collect(Schema::getColumns('roles'))->keyBy('name');

        $this->assertEqualsCanonicalizing(['id', 'clinic_id', 'code', 'name', 'scope', 'is_system'], $columns->keys()->all());
        foreach ($columns as $name => $column) {
            $this->assertSame($name === 'clinic_id', $column['nullable'], $name.' nullability');
        }

        $indexes = collect(Schema::getIndexes('roles'))->keyBy('name');
        $this->assertTrue($indexes['roles_global_code_unique']['unique']);
        $this->assertTrue($indexes['roles_clinic_code_unique']['unique']);
        $this->assertSame(['id', 'clinic_id'], $indexes['roles_id_clinic_unique']['columns']);
    }

    public function test_valid_role_kinds_are_accepted(): void
    {
        $this->role('platform_admin', 'platform', true);
        $this->role('support_desk', 'platform', false);
        $this->role('dentist', 'clinic', true);
        $this->role('night_shift', 'clinic', false, 5);
        $this->role('night_shift', 'clinic', false, 6); // same code in another clinic
        $this->role('dentist', 'clinic', false, 5); // a clinic may reuse a template's code for its own role

        $this->assertDatabaseCount('roles', 6);
    }

    public function test_scope_and_clinic_rules_are_enforced(): void
    {
        $this->role('platform_admin', 'platform', true);
        $this->role('night_shift', 'clinic', false, 5);

        $invalid = [
            'platform role with a clinic' => fn () => $this->role('bad_one', 'platform', false, 5),
            'platform system role with a clinic' => fn () => $this->role('bad_two', 'platform', true, 5),
            'clinic custom role without a clinic' => fn () => $this->role('bad_three', 'clinic', false),
            'clinic system role owned by a clinic' => fn () => $this->role('bad_four', 'clinic', true, 5),
            'unknown scope' => fn () => $this->role('bad_five', 'admin', false),
            'upper-case code' => fn () => $this->role('Bad_Code', 'platform', false),
            'code with a space' => fn () => $this->role('bad code', 'platform', false),
            'one-character code' => fn () => $this->role('x', 'platform', false),
            'code starting with a digit' => fn () => $this->role('1st_role', 'platform', false),
            'blank name' => fn () => $this->role('blank_name', 'platform', false, null, '   '),
            'duplicate code without a clinic' => fn () => $this->role('platform_admin', 'clinic', true),
            'duplicate code within a clinic' => fn () => $this->role('night_shift', 'clinic', false, 5),
        ];

        foreach ($invalid as $case => $insert) {
            try {
                $insert();
                $this->fail("Accepted: {$case}");
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertDatabaseCount('roles', 2);
    }

    private function role(string $code, string $scope, bool $system, ?int $clinic = null, string $name = 'Role'): void
    {
        DB::table('roles')->insert([
            'clinic_id' => $clinic, 'code' => $code, 'name' => $name, 'scope' => $scope, 'is_system' => $system,
        ]);
    }
}
