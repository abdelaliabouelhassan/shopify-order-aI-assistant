<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopifyOrder extends Model
{
    //

    protected $guarded = [];

    public function items()
    {
        return $this->hasMany(ShopifyOrderItem::class, 'order_id');
    }


    public function orderItems()
    {
        return $this->hasMany(ShopifyOrderItem::class, 'order_id');
    }

    public function inventoryItems()
    {
        return $this->hasManyThrough(
            ShopifyInventoryItem::class,
            ShopifyOrderItem::class,
            'order_id',
            'variant_id',
            'id',
            'variant_id'
        );
    }
}
