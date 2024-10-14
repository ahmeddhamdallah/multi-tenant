<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class TenantDatabaseManager
{
    /**
     * Create the tenant's database if it doesn't exist.
     */
    public static function createDatabase($databaseName)
    {
        try {
            DB::statement("CREATE DATABASE IF NOT EXISTS {$databaseName}");
            Log::info("Database {$databaseName} created successfully.");
        } catch (\Exception $e) {
            Log::error("Failed to create database {$databaseName}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Run tenant-specific migrations.
     */
    public static function runMigrations($databaseName)
    {
        config(['database.connections.tenant.database' => $databaseName]);

        DB::purge('tenant'); // Clear any cached connections
        DB::reconnect('tenant'); // Reconnect with the new tenant database

        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);

        Log::info("Migrations completed for database: {$databaseName}");
    }
}
