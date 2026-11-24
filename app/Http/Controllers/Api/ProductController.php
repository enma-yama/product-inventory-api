<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexProductRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;

class ProductController extends Controller
{
    public function index(IndexProductRequest $request)
    {
        $filters = $request->validated();

        $products = Product::with(['category', 'suppliers'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->when($filters['category_id'] ?? null, fn ($query, $id) => $query->where('category_id', $id))
            ->when(isset($filters['min_price']), fn ($query) => $query->where('price', '>=', $filters['min_price']))
            ->when(isset($filters['max_price']), fn ($query) => $query->where('price', '<=', $filters['max_price']))
            ->when(isset($filters['min_stock']), fn ($query) => $query->where('stock', '>=', $filters['min_stock']))
            ->when(isset($filters['max_stock']), fn ($query) => $query->where('stock', '<=', $filters['max_stock']))
            ->when($request->boolean('in_stock'), fn ($query) => $query->inStock())
            ->when($request->boolean('low_stock'), fn ($query) => $query->lowStock())
            ->latest('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->validated();

        $product = Product::create(Arr::except($data, 'supplier_ids'));
        $product->suppliers()->sync($data['supplier_ids'] ?? []);

        $product->load(['category', 'suppliers']);

        return (new ProductResource($product))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Product $product): ProductResource
    {
        $product->load(['category', 'suppliers']);

        return new ProductResource($product);
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $data = $request->validated();

        $product->update(Arr::except($data, 'supplier_ids'));

        if (array_key_exists('supplier_ids', $data)) {
            $product->suppliers()->sync($data['supplier_ids'] ?? []);
        }

        $product->load(['category', 'suppliers']);

        return new ProductResource($product);
    }

    public function destroy(Product $product): Response
    {
        $product->delete();

        return response()->noContent();
    }
}
