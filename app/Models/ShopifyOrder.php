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
}
