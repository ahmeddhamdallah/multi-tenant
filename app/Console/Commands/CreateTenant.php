<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use App\Models\Tenant;
use Illuminate\Support\Str;

class CreateTenant extends Command
{
    protected $signature = 'tenant:create {name} {database_name}';
    protected $description = 'Create a new tenant with a dedicated database';

    public function handle()
    {
        $name = $this->argument('name');
        $databaseName = $this->argument('database_name');

        // 1. Create the tenant database
        $this->createDatabase($databaseName);

        // 2. Create the tenant in the tenants table
        $tenant = Tenant::create([
            'id' => Str::uuid()->toString(),
            'data' => [
                'name' => $name,
                'database_name' => $databaseName,
            ],
        ]);

        $this->info("Tenant {$tenant->data['name']} created with ID: {$tenant->id}");

        // 3. Run migrations on the tenant's database
        $this->runMigrations($databaseName);

        $this->info("Migrations completed for database: {$databaseName}");
    }

    private function createDatabase($databaseName)
    {
        // Create the database using raw SQL
        DB::statement("CREATE DATABASE IF NOT EXISTS {$databaseName}");
        $this->info("Database {$databaseName} created successfully.");
    }

    private function runMigrations($databaseName)
    {
        // Temporarily switch to the new tenant database
        config(['database.connections.tenant.database' => $databaseName]);
        DB::purge('tenant'); // Clear the connection cache
        DB::reconnect('tenant'); // Reconnect with new config

        // Run migrations on the tenant's database
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);
    }
}
