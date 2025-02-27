<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopifyOrderItem extends Model
{
    //

    protected $guarded = [];

    public function order()
    {
        return $this->belongsTo(ShopifyOrder::class);
    }
}
