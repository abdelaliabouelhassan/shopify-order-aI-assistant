<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OpenAIService;
use Illuminate\Http\Request;

class OrderController extends Controller
{

    public function index()
    {
        $orders = Order::all();
        return view('ask', compact('orders'));
    }

    public function ask(Request $request)
    {
        $question = $request->question;

        // Choose between single order or all orders
        if ($request->has('order_id')) {
            // $order = Order::findOrFail($request->order_id);
            // $answer = (new OpenAIService())->askAboutOrder($order, $question);
        } else {
            $answer = (new OpenAIService())->askAboutAllOrders($question);
        }

        return view('answer', [
            'question' => $question,
            'answer' => $answer
        ]);
    }
}
