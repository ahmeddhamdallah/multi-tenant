<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductsTable extends Migration
{
    public function up()
    {
        // Ensure the migration runs on the tenant connection
        Schema::connection('tenant')->create('products', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->string('name'); // Product name
            $table->text('description'); // Product description
            $table->decimal('price', 8, 2); // Product price
            $table->timestamps(); // created_at and updated_at
        });
    }

    public function down()
    {
        Schema::connection('tenant')->dropIfExists('products');
    }
}
