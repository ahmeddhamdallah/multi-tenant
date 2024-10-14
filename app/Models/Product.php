<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $connection = 'tenant'; // Use the tenant connection
    protected $fillable = ['name', 'description', 'price'];
}
