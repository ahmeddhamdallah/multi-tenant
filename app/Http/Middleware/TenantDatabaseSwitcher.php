<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Models\Tenant;
use App\Helpers\TenantDatabaseManager;

class TenantDatabaseSwitcher
{
    public function handle($request, Closure $next)
    {
        $tenantId = $request->header('X-Tenant-ID');
        Log::info('Received Tenant ID: ' . $tenantId);

        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            Log::error('Tenant not found with ID: ' . $tenantId);
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        Log::info('Tenant Data: ', $tenant->toArray());

        $databaseName = $tenant->getDatabaseName();
        if (!$databaseName) {
            Log::error('Database name not found for tenant: ' . $tenantId);
            return response()->json(['error' => 'Database name not found'], 404);
        }

        TenantDatabaseManager::createDatabase($databaseName);

        TenantDatabaseManager::runMigrations($databaseName);

        Config::set('database.connections.tenant.database', $databaseName);
        DB::purge('tenant');
        DB::setDefaultConnection('tenant');

        Log::info('Database switched to: ' . DB::connection()->getDatabaseName());

        return $next($request);
    }
}
