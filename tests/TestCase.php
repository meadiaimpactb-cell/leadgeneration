<?php

namespace Tests;

use Database\Seeders\TestSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Seed once per process, before the transaction — not once per test.
     *
     * `RefreshDatabase` reads this property when it runs its one-time
     * `migrate:fresh`, so the rows land before `beginDatabaseTransaction()`
     * and every test rolls back to them instead of rebuilding them. Seeding
     * inside `setUp()` instead put the same work inside the transaction, where
     * it was repeated and discarded for all 521 tests.
     *
     * It is declared here rather than on individual test classes on purpose:
     * the framework reads `migrateFreshUsing()` off whichever test triggers
     * the migration first, so a per-class property would make the seeded state
     * depend on test ordering. See Database\Seeders\TestSeeder for the rest,
     * including what a test must do when it needs a table empty.
     *
     * Classes that do not use RefreshDatabase are unaffected — nothing reads
     * this unless a migration is triggered.
     *
     * @var class-string<Seeder>
     */
    protected $seeder = TestSeeder::class;
}
