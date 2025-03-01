<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ShopifyOrderItem extends Model
{
    //

    protected $guarded = [];
    public $timestamps = false;

    public function order()
    {
        return $this->belongsTo(ShopifyOrder::class, 'order_id');
    }

    public function inventoryItem()
    {
        // Simple relationship using variant_id
        return $this->belongsTo(ShopifyInventoryItem::class, 'variant_id', 'variant_id');
    }

    public function inventoryItemBySku()
    {
        return $this->belongsTo(ShopifyInventoryItem::class, 'sku', 'sku');
    }
}
