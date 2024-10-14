<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;  // Import the correct base Controller
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
