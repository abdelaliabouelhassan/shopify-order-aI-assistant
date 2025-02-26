<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ShopifyService
{
    public function getOrders()
    {
        $url = "https://" . env('SHOPIFY_DOMAIN') . "/admin/api/" . env('SHOPIFY_API_VERSION') . "/orders.json";

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => env('SHOPIFY_API_SECRET')
        ])->get($url);

        return $response->json()['orders'];
    }

    public function getInventory()
    {
        $url = "https://" . env('SHOPIFY_DOMAIN') . "/admin/api/" . env('SHOPIFY_API_VERSION') . "/inventory_levels.json";

        $response = Http::withHeaders(
            ['X-Shopify-Access-Token' => env('SHOPIFY_API_SECRET')]
        )->get($url, [
            'location_ids' => '69621481526'
        ]);

        return $response->json();
    }
}
