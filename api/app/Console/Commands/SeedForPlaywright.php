<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Database\Seeders\DemoIndiaSeeder;

class SeedForPlaywright extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seed:playwright';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed database for Playwright tests with tenant database creation enabled';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Seeding database for Playwright tests...');

        // Disable automatic transaction wrapping for this command
        DB::connection()->disableQueryLog();
        
        // Run the seeder without transaction wrapping
        $seeder = new DemoIndiaSeeder();
        $seeder->run();

        $this->info('Database seeded successfully for Playwright tests!');
    }
}