<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'category_id', 'name_lo', 'name_en', 'description_lo', 'description_en',
    'price', 'image_path', 'is_available', 'is_active', 'sort_order',
])]
class MenuItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_available' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** Items customers may order right now. */
    public function scopeOrderable(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('is_available', true);
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'lo' ? $this->name_lo : $this->name_en;
    }

    public function getDescriptionAttribute(): ?string
    {
        return app()->getLocale() === 'lo' ? $this->description_lo : $this->description_en;
    }

    /**
     * Host-relative photo URL so it works from any address the app is opened on
     * (Herd domain on the PC, LAN IP on customer phones).
     */
    public function imageUrl(): ?string
    {
        return $this->image_path ? '/storage/'.ltrim($this->image_path, '/') : null;
    }
}
