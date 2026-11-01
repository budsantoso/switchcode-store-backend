<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabel Pivot Multi-Kategori: product_category
        if (!Schema::hasTable('category_product')) {
            Schema::create('category_product', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['product_id', 'category_id']);
            });
        }

        // 2. Tabel Showcases (Etalase)
        if (!Schema::hasTable('showcases')) {
            Schema::create('showcases', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->string('banner_image')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        // 3. Tabel Pivot Etalase & Produk: product_showcase (1 etalase berisi banyak produk)
        if (!Schema::hasTable('product_showcase')) {
            Schema::create('product_showcase', function (Blueprint $table) {
                $table->id();
                $table->foreignId('showcase_id')->constrained('showcases')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['showcase_id', 'product_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_showcase');
        Schema::dropIfExists('showcases');
        Schema::dropIfExists('category_product');
    }
};
