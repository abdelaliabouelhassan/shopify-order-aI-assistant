<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopifyInventoryLevel extends Model
{
    //

    protected $guarded = [];

    public function inventoryItem()
    {
        return $this->belongsTo(ShopifyInventoryItem::class, 'inventory_item_id', 'inventory_item_id');
    }
}
