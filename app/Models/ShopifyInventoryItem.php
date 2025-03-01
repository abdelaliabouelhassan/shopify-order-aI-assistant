<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopifyInventoryItem extends Model
{
    //

    protected $guarded = [];

    public function inventoryLevels()
    {
        return $this->hasMany(ShopifyInventoryLevel::class, 'inventory_item_id', 'inventory_item_id');
    }
}
