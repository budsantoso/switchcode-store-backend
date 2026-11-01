<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Showcase;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ShowcaseController extends Controller
{
    // Public List: etalase aktif beserta produk di dalamnya
    public function index(): JsonResponse
    {
        $showcases = Showcase::with(['products' => function ($q) {
            $q->where('is_active', true)->with('categories');
        }])
        ->where('is_active', true)
        ->orderBy('sort_order', 'asc')
        ->get();

        return response()->json([
            'success' => true,
            'data' => $showcases,
        ]);
    }

    // Public Detail Etalase
    public function show(string $slug): JsonResponse
    {
        $showcase = Showcase::with(['products' => function ($q) {
            $q->where('is_active', true)->with('categories');
        }])
        ->where('slug', $slug)
        ->where('is_active', true)
        ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $showcase,
        ]);
    }

    // Admin List
    public function adminIndex(): JsonResponse
    {
        $showcases = Showcase::with(['products' => function ($q) {
            $q->select('products.id', 'products.name', 'products.base_price');
        }])
        ->orderBy('sort_order', 'asc')
        ->get();

        return response()->json([
            'success' => true,
            'data' => $showcases,
        ]);
    }

    // Admin Store Etalase
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:showcases,slug',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id',
        ]);

        $slug = !empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['name']);

        $showcase = Showcase::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        if (!empty($validated['product_ids'])) {
            $syncData = [];
            foreach ($validated['product_ids'] as $idx => $pId) {
                $syncData[$pId] = ['sort_order' => $idx];
            }
            $showcase->products()->sync($syncData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Etalase baru berhasil dibuat',
            'data' => $showcase->load('products'),
        ], 201);
    }

    // Admin Update Etalase & Assign Produk
    public function update(Request $request, int $id): JsonResponse
    {
        $showcase = Showcase::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:showcases,slug,' . $id,
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id',
        ]);

        $slug = !empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['name']);

        $showcase->update([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        if (isset($validated['product_ids'])) {
            $syncData = [];
            foreach ($validated['product_ids'] as $idx => $pId) {
                $syncData[$pId] = ['sort_order' => $idx];
            }
            $showcase->products()->sync($syncData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Etalase berhasil diperbarui',
            'data' => $showcase->load('products'),
        ]);
    }

    // Admin Destroy Etalase
    public function destroy(int $id): JsonResponse
    {
        $showcase = Showcase::findOrFail($id);
        $showcase->products()->detach();
        $showcase->delete();

        return response()->json([
            'success' => true,
            'message' => 'Etalase berhasil dihapus',
        ]);
    }
}
