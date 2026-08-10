<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\DemoContentSeeder;
use Database\Seeders\DemoExtrasSeeder;
use Database\Seeders\DemoLeadsSeeder;
use Database\Seeders\LeadFieldsSeeder;
use Database\Seeders\NavigationSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Fills the whole site with demonstration data in one step.
 *
 * For reviewing the build before Amad Craft's own content arrives. The copy it
 * writes is a first draft to be edited in the admin panel — §0.1 puts
 * copywriting on the client's side, and this exists only because the client
 * asked to see a finished-looking site rather than empty containers.
 *
 * Refuses to run in production.
 */
class SeedDemo extends Command
{
    protected $signature = 'amad:seed-demo {--fresh : Drop every table and rebuild from scratch}';

    protected $description = 'Fill the site with demonstration content, media and leads';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing to run: this command writes placeholder content and demo leads.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->warn('Dropping every table and rebuilding.');
            Artisan::call('migrate:fresh', ['--force' => true], $this->output);
            $this->purgeMediaDisk();
        }

        // Structural first — roles, pages, sectors, settings, menus, form
        // fields — then the demonstration layers on top of them.
        foreach ([
            RolesSeeder::class,
            StructureSeeder::class,
            LeadFieldsSeeder::class,
            NavigationSeeder::class,
            DemoContentSeeder::class,
            DemoExtrasSeeder::class,
            DemoLeadsSeeder::class,
        ] as $seeder) {
            $this->components->task(class_basename($seeder), function () use ($seeder): bool {
                $this->callSilent('db:seed', ['--class' => $seeder, '--force' => true]);

                return true;
            });
        }

        // Menus, settings and form fields are all cached per locale.
        Cache::flush();

        $this->newLine();
        $this->info('Demo data loaded.');
        $this->newLine();
        $this->warn('The copy is a DRAFT for review — edit it in the admin panel.');
        $this->line('  Not verified, and must be replaced before launch:');
        $this->line('   · the impact figures');
        $this->line('   · the artisan stories and their attributions');
        $this->line('   · the legal pages, which need a lawyer');
        $this->newLine();
        $this->line('The partners are real: the five published on amadcraft.sa, with their');
        $this->line('own logos. Nothing is filed as an accreditor or a client, because the');
        $this->line('official site does not publish those groups.');
        $this->newLine();
        $this->line('Create an admin account with: php artisan amad:create-admin');

        return self::SUCCESS;
    }

    /**
     * Empties the media disk after a --fresh rebuild.
     *
     * migrate:fresh drops the media table but leaves every uploaded file on
     * disk, and the next run starts numbering directories from 1 again. So
     * each rebuild left the previous run's images behind, unreferenced and
     * unreachable: 400 orphaned directories had accumulated in this working
     * copy before anyone noticed, because nothing on the site ever links to
     * them and no test looks at the disk.
     *
     * Only reached under --fresh, which has already dropped every table — so
     * there is nothing here left to lose. Directories only: .gitignore and
     * anything else the repository puts at the root of the disk stays.
     */
    private function purgeMediaDisk(): void
    {
        $disk = Storage::disk('public');
        $directories = $disk->directories();

        foreach ($directories as $directory) {
            $disk->deleteDirectory($directory);
        }

        if ($directories !== []) {
            $this->line('Cleared '.count($directories).' media directories left by the dropped tables.');
        }
    }
}
