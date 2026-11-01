<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductModule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    // Public List
    public function index(Request $request): JsonResponse
    {
        $products = Product::with(['modules', 'categories', 'showcases', 'images'])
            ->where('is_active', true)
            ->latest()
            ->paginate($request->integer('per_page', 12));

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    // Public Detail (Customer Customizer)
    public function show(string $slug): JsonResponse
    {
        $product = Product::with(['modules', 'categories', 'showcases', 'images'])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $product,
        ]);
    }

    // Admin List
    public function adminIndex(Request $request): JsonResponse
    {
        $query = Product::with(['modules', 'categories', 'showcases', 'images'])->latest();

        if ($request->filled('category_id')) {
            $catId = $request->integer('category_id');
            $query->whereHas('categories', function ($q) use ($catId) {
                $q->where('categories.id', $catId);
            });
        }

        if ($request->filled('showcase_id')) {
            $showId = $request->integer('showcase_id');
            $query->whereHas('showcases', function ($q) use ($showId) {
                $q->where('showcases.id', $showId);
            });
        }

        if ($request->filled('search')) {
            $s = $request->string('search');
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('summary', 'like', "%{$s}%");
            });
        }

        $products = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    // Admin Create Product + Modules/Toppings + Multi-Category
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'showcase_ids' => 'nullable|array',
            'showcase_ids.*' => 'exists:showcases,id',
            'name' => 'required|string|max:255',
            'summary' => 'nullable|string',
            'description' => 'nullable|string',
            'base_price' => 'required|numeric|min:0',
            'thumbnail' => 'nullable|image|max:5120',
            'is_active' => 'nullable|boolean',
            'modules' => 'nullable|array',
            'modules.*.name' => 'required|string|max:255',
            'modules.*.description' => 'nullable|string',
            'modules.*.price' => 'required|numeric|min:0',
            'modules.*.is_required' => 'nullable|boolean',
            'modules.*.sort_order' => 'nullable|integer',
        ]);

        $product = DB::transaction(function () use ($request, $validated) {
            $baseSlug = Str::slug($validated['name']);
            $slug = $baseSlug;
            $counter = 1;
            while (Product::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter++;
            }

            $thumbnailPath = null;
            if ($request->hasFile('thumbnail')) {
                $thumbnailPath = $request->file('thumbnail')->store('products', 'public');
            }

            $product = Product::create([
                'name' => $validated['name'],
                'slug' => $slug,
                'summary' => $validated['summary'] ?? null,
                'description' => $validated['description'] ?? null,
                'base_price' => $validated['base_price'],
                'thumbnail' => $thumbnailPath,
                'is_active' => $request->boolean('is_active', true),
            ]);

            // Sync Multi Kategori
            if (!empty($validated['category_ids'])) {
                $product->categories()->sync($validated['category_ids']);
            }

            // Sync ke Etalase
            if (!empty($validated['showcase_ids'])) {
                $product->showcases()->sync($validated['showcase_ids']);
            }

            // Upload Multiple Image Screenshots / Carousels
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $imgIdx => $imgFile) {
                    $imgPath = $imgFile->store('product_galleries', 'public');
                    \App\Models\ProductImage::create([
                        'product_id' => $product->id,
                        'image_url' => $imgPath,
                        'sort_order' => $imgIdx,
                    ]);
                }
            }

            if (!empty($validated['modules'])) {
                foreach ($validated['modules'] as $idx => $mod) {
                    ProductModule::create([
                        'product_id' => $product->id,
                        'name' => $mod['name'],
                        'description' => $mod['description'] ?? null,
                        'price' => $mod['price'],
                        'is_required' => $mod['is_required'] ?? false,
                        'sort_order' => $mod['sort_order'] ?? $idx,
                    ]);
                }
            }

            return $product->load(['modules', 'categories', 'showcases', 'images']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil disimpan ke etalase',
            'data' => $product,
        ], 201);
    }

    // Admin Update
    public function update(Request $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'summary' => 'nullable|string',
            'description' => 'nullable|string',
            'base_price' => 'sometimes|required|numeric|min:0',
            'thumbnail' => 'nullable|image|max:5120',
            'is_active' => 'nullable|boolean',
            'modules' => 'nullable|array',
            'modules.*.id' => 'nullable|integer',
            'modules.*.name' => 'required|string|max:255',
            'modules.*.description' => 'nullable|string',
            'modules.*.price' => 'required|numeric|min:0',
            'modules.*.is_required' => 'nullable|boolean',
            'modules.*.sort_order' => 'nullable|integer',
        ]);

        DB::transaction(function () use ($request, $product, $validated) {
            if (isset($validated['name']) && $validated['name'] !== $product->name) {
                $baseSlug = Str::slug($validated['name']);
                $slug = $baseSlug;
                $counter = 1;
                while (Product::where('slug', $slug)->where('id', '!=', $product->id)->exists()) {
                    $slug = $baseSlug . '-' . $counter++;
                }
                $product->slug = $slug;
                $product->name = $validated['name'];
            }

            if (isset($validated['summary'])) {
                $product->summary = $validated['summary'];
            }
            if (isset($validated['description'])) {
                $product->description = $validated['description'];
            }
            if (isset($validated['base_price'])) {
                $product->base_price = $validated['base_price'];
            }
            if ($request->has('is_active')) {
                $product->is_active = $request->boolean('is_active');
            }

            if ($request->hasFile('thumbnail')) {
                if ($product->thumbnail && Storage::disk('public')->exists($product->thumbnail)) {
                    Storage::disk('public')->delete($product->thumbnail);
                }
                $product->thumbnail = $request->file('thumbnail')->store('products', 'public');
            }

            // Multi Category Sync
            if (isset($validated['category_ids'])) {
                $product->categories()->sync($validated['category_ids']);
            }

            // Showcase Sync
            if (isset($validated['showcase_ids'])) {
                $product->showcases()->sync($validated['showcase_ids']);
            }

            // Upload Multiple Image Screenshots / Carousels
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $imgIdx => $imgFile) {
                    $imgPath = $imgFile->store('product_galleries', 'public');
                    \App\Models\ProductImage::create([
                        'product_id' => $product->id,
                        'image_url' => $imgPath,
                        'sort_order' => $imgIdx,
                    ]);
                }
            }

            $product->save();

            // Sync Modules / Toppings jika dikirimkan
            if (isset($validated['modules'])) {
                $submittedIds = array_filter(array_column($validated['modules'], 'id'));
                // Hapus modul lama yang tidak ada di request
                $product->modules()->whereNotIn('id', $submittedIds)->delete();

                foreach ($validated['modules'] as $idx => $modData) {
                    if (!empty($modData['id'])) {
                        ProductModule::where('id', $modData['id'])
                            ->where('product_id', $product->id)
                            ->update([
                                'name' => $modData['name'],
                                'description' => $modData['description'] ?? null,
                                'price' => $modData['price'],
                                'is_required' => $modData['is_required'] ?? false,
                                'sort_order' => $modData['sort_order'] ?? $idx,
                            ]);
                    } else {
                        ProductModule::create([
                            'product_id' => $product->id,
                            'name' => $modData['name'],
                            'description' => $modData['description'] ?? null,
                            'price' => $modData['price'],
                            'is_required' => $modData['is_required'] ?? false,
                            'sort_order' => $modData['sort_order'] ?? $idx,
                        ]);
                    }
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Produk dan modul berhasil diperbarui',
            'data' => $product->fresh(['modules', 'categories', 'showcases', 'images']),
        ]);
    }

    // Admin Delete
    public function destroy(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        DB::transaction(function () use ($product) {
            if ($product->thumbnail && Storage::disk('public')->exists($product->thumbnail)) {
                Storage::disk('public')->delete($product->thumbnail);
            }
            $product->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil dihapus',
        ]);
    }
}
