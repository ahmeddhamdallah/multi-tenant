
# Multi-Tenant Laravel Setup with Automatic Database Creation

This guide explains how to build a **multi-tenant Laravel application** where each tenant has its **own isolated database**.
This setup ensures data separation, and tenants’ databases are **created automatically** when a new tenant interacts with the system.

---

## Features
- **Automatic tenant database creation** on the first product insertion.
- **Tenant-specific migrations** for each database.
- **Middleware for dynamic database switching** based on tenant ID.
- **Reusable helper service** to manage database creation and migrations.

---

## Prerequisites
- Laravel 10 or later installed.
- MySQL database installed and accessible.
- Basic understanding of Laravel middleware, migrations, and commands.

---

## Step-by-Step Implementation

### 1. Install Laravel and Required Dependencies
Start by installing Laravel.

```bash
composer create-project --prefer-dist laravel/laravel multi-tenant
cd multi-tenant
```

Install **Stancl/Tenancy** (optional if you want a package for multi-tenancy).

```bash
composer require stancl/tenancy
```

---

### 2. Configure Database Settings
Open `.env` and set up the **MySQL database**.

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=central_db
DB_USERNAME=root
DB_PASSWORD=your_password
```

---

### 3. Create the Tenants Table
Create a migration for the **tenants table**.

```bash
php artisan make:migration create_tenants_table --create=tenants
```

Modify the migration file:

```php
public function up()
{
    Schema::create('tenants', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('database_name');
        $table->json('data')->nullable();
        $table->timestamps();
    });
}
```

Run the migration:

```bash
php artisan migrate
```

---

### 4. Create the Tenant Model
Create a **Tenant model**.

```bash
php artisan make:model Tenant
```

Modify the model:

```php
<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Illuminate\Support\Str;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    protected $fillable = ['id', 'name', 'database_name', 'data', 'created_at', 'updated_at'];

    protected $casts = [
        'data' => 'array',
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = Str::uuid()->toString();
            }
        });
    }
}
```

---

### 5. Create Middleware for Database Switching
Create a middleware for **dynamic database switching**.

```bash
php artisan make:middleware TenantDatabaseSwitcher
```

Modify the middleware:

```php
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

```

Register the middleware in `Kernel.php`:

```php
'api' => [
            \App\Http\Middleware\TenantDatabaseSwitcher::class,
        ],
```

---
### 6. Create Command to Generate Tenant and Database
Create a new Artisan command to **generate tenants and their databases** automatically.

```bash
php artisan make:command CreateTenant
```

Modify the command:

```php
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

```

Register the command in `Kernel.php`:

```php
protected $commands = [
    \App\Console\Commands\CreateTenant::class,
];
```
---
### 7. Create Helper for Database Management
Create a **helper** to manage database creation and migrations.

```php
<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class TenantDatabaseManager
{
    public static function createDatabase($databaseName)
    {
        DB::statement("CREATE DATABASE IF NOT EXISTS {$databaseName}");
        Log::info("Database {$databaseName} created successfully.");
    }

    public static function runMigrations($databaseName)
    {
        config(['database.connections.tenant.database' => $databaseName]);
        DB::purge('tenant');
        DB::reconnect('tenant');
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);
    }
}
```

---

### 8. Define API Routes and Controller for Products
Define the API routes in `api.php`.

```php
use App\Http\Controllers\API\ProductController;

Route::post('/products', [ProductController::class, 'store']);
```

Create the **Product Controller**.

```bash
php artisan make:controller ProductController --api
```

Modify the controller:

```php
<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use App\Http\Requests\ProductRequest;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    private $productRepository;

    public function __construct(ProductRepositoryInterface $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function store(ProductRequest $request)
    {
        Log::info('ProductController store method hit.');

        $validated = $request->validated();
        Log::info('Validation successful: ', $validated);

        $product = $this->productRepository->create($validated);

        return response()->json($product, 201);
    }
}
```

---

### 9. Create Unit Tests for Product Creation
Create a **feature test** to validate product creation.

```bash
php artisan make:test ProductTest --unit
```

Modify the test:

```php
<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Tenant;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_product_for_tenant()
    {
        $tenant = Tenant::create([
            'id' => '123e4567-e89b-12d3-a456-426614174000',
            'name' => 'Test Tenant',
            'database_name' => 'tenant_test_db',
        ]);

        $response = $this->withHeaders([
            'X-Tenant-ID' => $tenant->id,
        ])->postJson('/api/products', [
            'name' => 'Sample Product',
            'description' => 'A sample product',
            'price' => 29.99,
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Sample Product']);
    }
}
```

---

### 10. Push the Project to a New GitHub Repository

#### Step 1: Initialize a Local Git Repository

```bash
git init
git add .
git commit -m "Initial commit for multi-tenant Laravel project"
```

#### Step 2: Create a New Repository on GitHub

1. Go to [GitHub](https://github.com).
2. Click **"New Repository"**.
3. Name the repository and click **"Create Repository"**.

#### Step 3: Add the GitHub Remote and Push

```bash
git remote add origin https://github.com/your-username/multi-tenant.git
git branch -M main
git push -u origin main
```

---
## **How to Clone and Access the Repository**

To clone the repository to your local machine, use:

```bash
git clone git@github.com:ahmeddhamdallah/multi-tenant.git
cd multi-tenant-laravel
```

After cloning, install the dependencies:

```bash
composer install
```

Copy the `.env.example` to `.env`:

```bash
cp .env.example .env
```

Generate the application key:

```bash
php artisan key:generate
```

Run migrations:

```bash
php artisan migrate
```

---
## Conclusion
This guide demonstrates how to set up a **multi-tenant Laravel application** with **dynamic database creation**. With the provided middleware, helper classes, and unit tests, each tenant will have their **own database**, and the system will **automatically handle migrations and product insertion**.
