<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\OpenAIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    /**
     * Get all conversations for the authenticated user
     */
    public function getConversations()
    {
        $conversations = Conversation::where('user_id', Auth::id())
            ->orderBy('updated_at', 'desc')
            ->with(['messages' => function ($query) {
                $query->orderBy('created_at', 'asc');
            }])
            ->get();

        return response()->json($conversations);
    }

    /**
     * Get a specific conversation with its messages
     */
    public function getConversation($id)
    {
        $conversation = Conversation::where('id', $id)
            ->where('user_id', Auth::id())
            ->with(['messages' => function ($query) {
                $query->orderBy('created_at', 'asc');
            }])
            ->firstOrFail();

        return response()->json($conversation);
    }

    /**
     * Create a new conversation
     */
    public function createConversation(Request $request)
    {
        $conversation = Conversation::create([
            'user_id' => Auth::id(),
            'title' => $request->input('title', 'New Conversation'),
            'ai_model' => $request->input('ai_model', 'openai'),
        ]);

        return response()->json($conversation, 201);
    }

    /**
     * Update a conversation's title
     */
    public function updateConversation(Request $request, $id)
    {
        $conversation = Conversation::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $conversation->update([
            'title' => $request->input('title'),
        ]);

        return response()->json($conversation);
    }

    /**
     * Delete a conversation
     */
    public function deleteConversation($id)
    {
        $conversation = Conversation::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $conversation->delete();

        return response()->json(['message' => 'Conversation deleted']);
    }

    /**
     * Send a message in a conversation
     */
    public function sendMessage(Request $request, $conversationId)
    {
        $request->validate([
            'content' => 'required|string',
        ]);

        $answer = (new OpenAIService())->askAboutOrders($request->content);

        $conversation = Conversation::where('id', $conversationId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        // Create user message
        $message = Message::create([
            'conversation_id' => $conversationId,
            'sender_type' => 'user',
            'content' => $request->input('content'),
        ]);

        // Simulate AI response (replace with actual AI integration later)
        $aiResponse = Message::create([
            'conversation_id' => $conversationId,
            'sender_type' => 'ai',
            'content' => $answer,
        ]);

        // Update conversation timestamp
        $conversation->touch();

        return response()->json([
            'user_message' => $message,
            'ai_response' => $aiResponse
        ], 201);
    }

    /**
     * Clear all messages in a conversation
     */
    public function clearConversation($id)
    {
        $conversation = Conversation::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $conversation->messages()->delete();

        return response()->json(['message' => 'Conversation cleared']);
    }
}
