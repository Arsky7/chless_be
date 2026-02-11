<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CollectionProduct extends Model
{
    use HasFactory;

    protected $table = 'collection_products';
    
    protected $fillable = [
        'collection_id',
        'product_id',
        'sort_order',
    ];

    // RELATIONSHIPS
    public function collection()
    {
        return $this->belongsTo(Collection::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // SCOPES
    public function scopeByCollection($query, $collectionId)
    {
        return $query->where('collection_id', $collectionId);
    }

    public function scopeByProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeSorted($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    // HELPERS
    public static function updateSortOrder($collectionId, $productId, $sortOrder)
    {
        $relation = self::where('collection_id', $collectionId)
                        ->where('product_id', $productId)
                        ->first();
        
        if ($relation) {
            $relation->update(['sort_order' => $sortOrder]);
        }
        
        return $relation;
    }
}