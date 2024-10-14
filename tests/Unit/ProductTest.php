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
