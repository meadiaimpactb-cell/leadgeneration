<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * The one seed the test suite is built on — run once per process, never per test.
 *
 * WHY THIS EXISTS
 *
 * 47 of 54 test files used to call `$this->seed([...])` inside `setUp()`.
 * `setUp()` runs once per *test method*, not per file, and it runs inside the
 * transaction `RefreshDatabase` opens — so every seeded row was written and
 * then rolled back again for each of 521 tests. 262 of those tests pulled in
 * DemoContentSeeder, which cost ~34 seconds a time. The suite passed an hour
 * twice without finishing, and a suite nobody can run has stopped protecting
 * anything.
 *
 * `RefreshDatabase` already has the right seam for this. From the framework
 * source: `refreshTestDatabase()` runs `migrateDatabases()` exactly once per
 * process — guarded by `RefreshDatabaseState::$migrated` — and only then calls
 * `beginDatabaseTransaction()`. `migrateDatabases()` passes `--seeder` from
 * `CanConfigureMigrationCommands::migrateFreshUsing()`. So a seeder named on
 * the `$seeder` property runs BEFORE the transaction, once; each test then
 * simply rolls back to it.
 *
 * WHY IT IS DECLARED ON THE BASE TestCase AND NOT PER FILE
 *
 * `migrateFreshUsing()` is read off whichever test instance happens to trigger
 * the one-time migration first. Declaring `$seeder` on some classes and not
 * others would make what gets seeded depend on test ordering — green in
 * isolation, arbitrary in a full run. It is therefore declared once, on
 * `Tests\TestCase`, so every process seeds identically.
 *
 * ⚠️ THE COST OF THIS DESIGN
 *
 * The seeded state is shared by every test in the process. A test that needs a
 * table empty cannot assume it is — it must empty that table itself in
 * `setUp()`, which is cheap and rolls back with everything else. Two tests
 * needed exactly that and say so at the call site:
 * LeadFormBuilderTest and LanguagesScreenTest.
 *
 * Demo content is included deliberately: 25 test files assert against it, and
 * splitting it out would only move the per-test cost somewhere else. Both demo
 * seeders carry their own production guard, so this cannot leak live.
 */
class TestSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            StructureSeeder::class,
            NavigationSeeder::class,
            LeadFieldsSeeder::class,
            RedirectsSeeder::class,
            DemoContentSeeder::class,
            DemoExtrasSeeder::class,
        ]);
    }
}
