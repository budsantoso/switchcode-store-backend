<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ArticleImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_id',
        'image_path',
        'caption',
        'sort_order',
    ];

    protected $appends = ['url'];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function getUrlAttribute(): ?string
    {
        if (!$this->image_path) {
            return null;
        }

        if (str_starts_with($this->image_path, 'http://') || str_starts_with($this->image_path, 'https://')) {
            return $this->image_path;
        }

        return Storage::disk('public')->url($this->image_path);
    }
}
