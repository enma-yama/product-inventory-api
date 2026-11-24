<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use Illuminate\Support\Facades\Cache;

class SupplierController extends Controller
{
    public function index()
    {
        $suppliers = Cache::remember(Supplier::CACHE_KEY, now()->addHour(), function () {
            return Supplier::orderBy('name')->get();
        });

        return SupplierResource::collection($suppliers);
    }
}
