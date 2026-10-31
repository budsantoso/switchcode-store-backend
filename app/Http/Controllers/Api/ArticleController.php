<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ArticleController extends Controller
{
    // Public List
    public function index(Request $request): JsonResponse
    {
        $query = Article::with('images', 'author:id,name')
            ->where('is_published', true)
            ->latest('published_at');

        $articles = $query->paginate($request->integer('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => $articles,
        ]);
    }

    // Public Detail
    public function show(string $slug): JsonResponse
    {
        $article = Article::with('images', 'author:id,name')
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $article,
        ]);
    }

    // Admin List (All, including drafts)
    public function adminIndex(Request $request): JsonResponse
    {
        $articles = Article::with('images', 'author:id,name')
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $articles,
        ]);
    }

    // Admin Create
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'summary' => 'nullable|string',
            'content' => 'required|string',
            'is_published' => 'nullable|boolean',
            'images.*' => 'nullable|image|max:5120', // max 5MB per file
        ]);

        $article = DB::transaction(function () use ($request, $validated) {
            $baseSlug = Str::slug($validated['title']);
            $slug = $baseSlug;
            $counter = 1;
            while (Article::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter++;
            }

            $isPublished = $request->boolean('is_published', true);

            $article = Article::create([
                'author_id' => $request->user()->id,
                'title' => $validated['title'],
                'slug' => $slug,
                'summary' => $validated['summary'] ?? Str::limit(strip_tags($validated['content']), 160),
                'content' => $validated['content'],
                'is_published' => $isPublished,
                'published_at' => $isPublished ? now() : null,
            ]);

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $idx => $file) {
                    $path = $file->store('articles', 'public');
                    ArticleImage::create([
                        'article_id' => $article->id,
                        'image_path' => $path,
                        'sort_order' => $idx,
                    ]);
                }
            }

            return $article->load('images');
        });

        return response()->json([
            'success' => true,
            'message' => 'Berita berhasil dipublikasikan',
            'data' => $article,
        ], 201);
    }

    // Admin Update
    public function update(Request $request, int $id): JsonResponse
    {
        $article = Article::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'summary' => 'nullable|string',
            'content' => 'sometimes|required|string',
            'is_published' => 'nullable|boolean',
            'images.*' => 'nullable|image|max:5120',
        ]);

        DB::transaction(function () use ($request, $article, $validated) {
            if (isset($validated['title']) && $validated['title'] !== $article->title) {
                $baseSlug = Str::slug($validated['title']);
                $slug = $baseSlug;
                $counter = 1;
                while (Article::where('slug', $slug)->where('id', '!=', $article->id)->exists()) {
                    $slug = $baseSlug . '-' . $counter++;
                }
                $article->slug = $slug;
                $article->title = $validated['title'];
            }

            if (isset($validated['summary'])) {
                $article->summary = $validated['summary'];
            }

            if (isset($validated['content'])) {
                $article->content = $validated['content'];
            }

            if ($request->has('is_published')) {
                $isPublished = $request->boolean('is_published');
                $article->is_published = $isPublished;
                if ($isPublished && !$article->published_at) {
                    $article->published_at = now();
                }
            }

            $article->save();

            if ($request->hasFile('images')) {
                $startOrder = $article->images()->count();
                foreach ($request->file('images') as $idx => $file) {
                    $path = $file->store('articles', 'public');
                    ArticleImage::create([
                        'article_id' => $article->id,
                        'image_path' => $path,
                        'sort_order' => $startOrder + $idx,
                    ]);
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Berita berhasil diperbarui',
            'data' => $article->fresh(['images']),
        ]);
    }

    // Admin Delete
    public function destroy(int $id): JsonResponse
    {
        $article = Article::with('images')->findOrFail($id);

        DB::transaction(function () use ($article) {
            foreach ($article->images as $img) {
                if ($img->image_path && Storage::disk('public')->exists($img->image_path)) {
                    Storage::disk('public')->delete($img->image_path);
                }
            }
            $article->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Berita berhasil dihapus',
        ]);
    }

    // Admin Upload Media/Image for WYSIWYG
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|max:10240', // Max 10MB
        ]);

        $path = $request->file('image')->store('articles/inline', 'public');
        $fullUrl = 'https://api.switchcode.id/api/storage/' . $path;

        return response()->json([
            'success' => true,
            'message' => 'Gambar berhasil diunggah',
            'data' => [
                'path' => $path,
                'url' => $fullUrl,
            ],
        ]);
    }
}
