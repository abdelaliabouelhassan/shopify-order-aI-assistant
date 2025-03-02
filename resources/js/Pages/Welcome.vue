<template>
  <Head :title="title" />
  <div class="flex flex-col h-screen bg-gray-50">
    <!-- Header -->
    <header class="bg-white border-b border-gray-200 px-4 py-3 shadow-sm">
      <div class="max-w-4xl mx-auto flex items-center justify-between">
        <div class="flex items-center space-x-2">
          <div
            class="h-8 w-8 rounded-full bg-indigo-600 flex items-center justify-center"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="h-5 w-5 text-white"
              viewBox="0 0 20 20"
              fill="currentColor"
            >
              <path
                d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"
              />
            </svg>
          </div>
          <h1 class="font-semibold text-xl text-gray-800">AI Assistant</h1>
        </div>
        <div class="flex items-center space-x-2">
          <button
            @click="showHistory = !showHistory"
            class="text-gray-500 hover:text-gray-700 p-2 rounded-full hover:bg-gray-100"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="h-6 w-6"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
          </button>
        </div>
      </div>
    </header>

    <div class="flex flex-1 overflow-hidden">
      <!-- Chat History Sidebar -->
      <div
        v-if="showHistory"
        class="w-64 bg-white border-r border-gray-200 flex flex-col"
      >
        <div class="p-4 border-b border-gray-200">
          <h2 class="font-medium text-gray-700">Chat History</h2>
        </div>
        <div class="flex-1 overflow-y-auto p-2">
          <button
            @click="startNewChat"
            class="w-full flex items-center space-x-3 p-3 text-left rounded-lg hover:bg-gray-100 group mb-2"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="h-5 w-5 text-gray-500 group-hover:text-gray-700"
              viewBox="0 0 20 20"
              fill="currentColor"
            >
              <path
                fill-rule="evenodd"
                d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                clip-rule="evenodd"
              />
            </svg>
            <span class="text-gray-700">New Chat</span>
          </button>

          <button
            v-for="(conversation, index) in conversations"
            :key="conversation.id"
            @click="loadConversation(conversation.id)"
            class="w-full flex items-center space-x-3 p-3 text-left rounded-lg hover:bg-gray-100 mb-1"
            :class="
              currentConversationId === conversation.id ? 'bg-gray-100' : ''
            "
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="h-5 w-5 text-gray-500"
              viewBox="0 0 20 20"
              fill="currentColor"
            >
              <path
                fill-rule="evenodd"
                d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z"
                clip-rule="evenodd"
              />
            </svg>
            <div class="overflow-hidden">
              <div class="text-gray-700 text-sm truncate">
                {{ conversation.title || getConversationTitle(conversation) }}
              </div>
              <div class="text-gray-500 text-xs">
                {{ formatDate(conversation.updated_at) }}
              </div>
            </div>
          </button>
        </div>
      </div>

      <!-- Main Chat Area -->
      <div class="flex-1 flex flex-col overflow-hidden">
        <!-- Chat Messages -->
        <div class="flex-1 overflow-y-auto px-4 py-6" ref="chatContainer">
          <div class="max-w-3xl mx-auto space-y-6">
            <!-- Welcome Message (only shown in a new empty chat) -->
            <div class="flex items-start space-x-4">
              <div
                class="h-10 w-10 rounded-full bg-indigo-600 flex items-center justify-center flex-shrink-0"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  class="h-6 w-6 text-white"
                  viewBox="0 0 20 20"
                  fill="currentColor"
                >
                  <path
                    d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"
                  />
                </svg>
              </div>
              <div class="bg-white rounded-lg shadow-sm px-6 py-4 max-w-xl">
                <p class="text-gray-700">
                  Hello! I'm your AI assistant. How can I help you today?
                </p>
              </div>
            </div>

            <!-- Messages -->
            <div
              v-for="(message, index) in currentMessages"
              :key="message.id"
              class="flex items-start"
              :class="
                message.sender_type === 'user' ? 'justify-end' : 'space-x-4'
              "
            >
              <!-- AI Avatar (only for AI messages) -->
              <div
                v-if="message.sender_type === 'ai'"
                class="h-10 w-10 rounded-full bg-indigo-600 flex items-center justify-center flex-shrink-0"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  class="h-6 w-6 text-white"
                  viewBox="0 0 20 20"
                  fill="currentColor"
                >
                  <path
                    d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"
                  />
                </svg>
              </div>

              <!-- Message Bubble -->
              <div
                class="px-6 py-4 rounded-lg shadow-sm max-w-xl overflow-x-auto"
                :class="
                  message.sender_type === 'user'
                    ? 'bg-indigo-600 text-white'
                    : 'bg-white text-gray-700'
                "
              >
                <MarkdownRenderer :content="message.content" />
                <!-- <p>{{ message.content }}</p> -->
              </div>
            </div>

            <div class="flex items-start justify-end" v-if="isTyping">
              <div
                class="px-6 py-4 rounded-lg shadow-sm max-w-xl bg-indigo-600 text-white"
              >
                <p>{{ newMessageContainer }}</p>
              </div>
            </div>

            <!-- Typing indicator -->
            <div v-if="isTyping" class="flex items-start space-x-4">
              <div
                class="h-10 w-10 rounded-full bg-indigo-600 flex items-center justify-center flex-shrink-0"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  class="h-6 w-6 text-white"
                  viewBox="0 0 20 20"
                  fill="currentColor"
                >
                  <path
                    d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"
                  />
                </svg>
              </div>
              <div class="bg-white rounded-lg shadow-sm px-6 py-4">
                <div class="flex space-x-2">
                  <div
                    class="h-2 w-2 bg-gray-300 rounded-full animate-bounce"
                  ></div>
                  <div
                    class="h-2 w-2 bg-gray-300 rounded-full animate-bounce"
                    style="animation-delay: 0.2s"
                  ></div>
                  <div
                    class="h-2 w-2 bg-gray-300 rounded-full animate-bounce"
                    style="animation-delay: 0.4s"
                  ></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Input Area -->
        <div class="bg-white border-t border-gray-200 p-4">
          <div class="max-w-3xl mx-auto">
            <form
              @submit.prevent="sendMessage"
              class="flex items-center space-x-2"
            >
              <button
                type="button"
                @click="clearChat"
                class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-full"
                title="Clear chat"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  class="h-5 w-5"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                  />
                </svg>
              </button>
              <input
                v-model="newMessage"
                type="text"
                placeholder="Type your message..."
                class="flex-1 border border-gray-300 rounded-full px-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
              />
              <button
                type="submit"
                class="bg-indigo-600 text-white p-2 rounded-full hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                :disabled="!newMessage.trim()"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  class="h-5 w-5"
                  viewBox="0 0 20 20"
                  fill="currentColor"
                >
                  <path
                    d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"
                  />
                </svg>
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- Clear Chat Confirmation Modal -->
    <div
      v-if="showClearConfirm"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
    >
      <div class="bg-white rounded-lg shadow-lg max-w-md mx-4 p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">
          Clear current chat?
        </h3>
        <p class="text-gray-600 mb-6">
          This will delete all messages in the current conversation. This action
          cannot be undone.
        </p>
        <div class="flex justify-end space-x-3">
          <button
            @click="showClearConfirm = false"
            class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200"
          >
            Cancel
          </button>
          <button
            @click="confirmClearChat"
            class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700"
          >
            Clear
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, nextTick, onMounted } from "vue";
import axios from "axios";
import { Head } from "@inertiajs/vue3";
import MarkdownRenderer from "@/Components/MarkdownRenderer.vue";

defineProps({
  title: {
    type: String,
  },
});
// State
const conversations = ref([]);
const currentConversationId = ref(null);
const currentMessages = ref([]);
const newMessage = ref("");
const isTyping = ref(false);
const showHistory = ref(true);
const showClearConfirm = ref(false);
const chatContainer = ref(null);
const newMessageContainer = ref("");
// API base URL - adjust if needed

// Fetch all conversations on component mount
const fetchConversations = async () => {
  try {
    const response = await axios.get(`/conversations`);
    conversations.value = response.data;
    scrollToBottom();
    // If there are conversations, load the first one
    if (conversations.value.length > 0) {
      currentConversationId.value = conversations.value[0].id;
      currentMessages.value = conversations.value[0].messages || [];
    } else {
      // If no conversations exist, create a new one
      startNewChat();
    }
  } catch (error) {
    console.error("Error fetching conversations:", error);
  }
};

// Load a specific conversation
const loadConversation = async (id) => {
  try {
    currentConversationId.value = id;
    const response = await axios.get(`/conversations/${id}`);
    currentMessages.value = response.data.messages || [];

    // Scroll to bottom after messages are loaded
    nextTick(() => {
      scrollToBottom();
    });
  } catch (error) {
    console.error("Error loading conversation:", error);
  }
};

// Create a new conversation
const startNewChat = async () => {
  try {
    const response = await axios.post(`/conversations`, {
      title: "New Conversation",
    });

    const newConversation = response.data;
    conversations.value.unshift(newConversation);
    currentConversationId.value = newConversation.id;
    currentMessages.value = [];
  } catch (error) {
    console.error("Error creating new conversation:", error);
  }
};

// Send a message
const sendMessage = async () => {
  scrollToBottom();
  if (!newMessage.value.trim() || !currentConversationId.value) return;

  try {
    // Show typing indicator
    isTyping.value = true;

    const messageContent = newMessage.value;
    newMessageContainer.value = newMessage.value;
    newMessage.value = ""; // Clear input field

    const response = await axios.post(
      `/conversations/${currentConversationId.value}/messages`,
      {
        content: messageContent,
      }
    );

    // Add user message and AI response to the current messages
    if (response.data.user_message) {
      currentMessages.value.push(response.data.user_message);
    }

    // Scroll after user message
    nextTick(() => {
      scrollToBottom();
    });

    // Hide typing indicator after a brief delay to simulate thinking
    setTimeout(() => {
      isTyping.value = false;

      if (response.data.ai_response) {
        currentMessages.value.push(response.data.ai_response);
      }

      // Update the current conversation in the list
      const index = conversations.value.findIndex(
        (c) => c.id === currentConversationId.value
      );
      if (index !== -1) {
        conversations.value[index].updated_at = new Date().toISOString();

        // If this is the first message, update the title
        if (
          !conversations.value[index].title ||
          conversations.value[index].title === "New Conversation"
        ) {
          updateConversationTitle(currentConversationId.value, messageContent);
        }
      }

      // Scroll to bottom after AI response
      nextTick(() => {
        scrollToBottom();
      });
    }, 1000);
  } catch (error) {
    isTyping.value = false;
    console.error("Error sending message:", error);
  }
};

// Update conversation title
const updateConversationTitle = async (id, content) => {
  try {
    // Use the first ~25 chars of the first message as the title
    const title =
      content.length > 25 ? content.substring(0, 25) + "..." : content;

    const response = await axios.put(`/conversations/${id}`, {
      title: title,
    });

    // Update the title in the local conversations array
    const index = conversations.value.findIndex((c) => c.id === id);
    if (index !== -1) {
      conversations.value[index].title = response.data.title;
    }
  } catch (error) {
    console.error("Error updating conversation title:", error);
  }
};

// Clear current chat
const clearChat = () => {
  if (currentConversationId.value) {
    showClearConfirm.value = true;
  }
};

// Confirm clearing chat
const confirmClearChat = async () => {
  try {
    await axios.delete(
      `/conversations/${currentConversationId.value}/messages`
    );
    currentMessages.value = [];
    showClearConfirm.value = false;
  } catch (error) {
    console.error("Error clearing conversation:", error);
  }
};

// Get default title for a conversation
const getConversationTitle = (conversation) => {
  if (conversation.messages && conversation.messages.length > 0) {
    const firstUserMessage = conversation.messages.find(
      (m) => m.sender_type === "user"
    );
    if (firstUserMessage) {
      return firstUserMessage.content.length > 25
        ? firstUserMessage.content.substring(0, 25) + "..."
        : firstUserMessage.content;
    }
  }
  return "New Conversation";
};

// Format date for display
const formatDate = (dateString) => {
  if (!dateString) return "";

  const date = new Date(dateString);
  const now = new Date();
  const diffDays = Math.floor((now - date) / (1000 * 60 * 60 * 24));

  if (diffDays === 0) {
    return "Today";
  } else if (diffDays === 1) {
    return "Yesterday";
  } else if (diffDays < 7) {
    return `${diffDays} days ago`;
  } else {
    return date.toLocaleDateString();
  }
};

// Scroll chat container to bottom
const scrollToBottom = () => {
  if (chatContainer.value) {
    setTimeout(() => {
      chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
    }, 100);
  }
};

// Lifecycle hooks
onMounted(() => {
  fetchConversations();
  scrollToBottom();
});
</script>