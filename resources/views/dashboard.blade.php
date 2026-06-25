<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Voice Orders') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div x-data="voiceCapture">
                    <div class="text-center mb-8">
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Speak your order</h3>
                        <p class="text-sm text-gray-500">Click the microphone and speak clearly</p>
                    </div>

                    <div class="flex justify-center mb-6">
                        <button @click="toggleRecording"
                                :class="{'bg-red-500 hover:bg-red-600': isRecording, 'bg-blue-500 hover:bg-blue-600': !isRecording}"
                                class="w-20 h-20 rounded-full text-white flex items-center justify-center transition-colors duration-200 shadow-lg">
                            <svg x-show="!isRecording" class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/>
                            </svg>
                            <svg x-show="isRecording" class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <rect x="6" y="4" width="4" height="16" rx="1" fill="currentColor" stroke="none"/>
                                <rect x="14" y="4" width="4" height="16" rx="1" fill="currentColor" stroke="none"/>
                            </svg>
                        </button>
                    </div>

                    <div x-show="error" x-text="error" class="text-red-500 text-center mb-4 text-sm"></div>

                    <div class="bg-gray-50 rounded-lg p-4 mb-6 min-h-[100px]">
                        <p class="text-gray-400 text-sm mb-2">Live transcript:</p>
                        <p x-text="transcript || 'Waiting for speech...'" class="text-gray-800"></p>
                    </div>

                    <div class="flex justify-center gap-4">
                        <button @click="sendOrder"
                                :disabled="!transcript || isSending"
                                class="px-6 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                            <span x-show="!isSending">Send Order</span>
                            <span x-show="isSending">Sending...</span>
                        </button>
                        <button @click="clearTranscript"
                                :disabled="!transcript"
                                class="px-6 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                            Clear
                        </button>
                    </div>

                    <div x-show="successMessage" x-text="successMessage" class="text-green-600 text-center mt-4 text-sm"></div>
                </div>
            </div>

            <div class="mt-8 bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Order History</h3>
                <div x-data="orderHistory">
                    <template x-if="orders.length === 0">
                        <p class="text-gray-500 text-sm">No orders yet.</p>
                    </template>
                    <template x-for="order in orders" :key="order.id">
                        <div class="border-b border-gray-200 py-3 last:border-0">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <p class="text-gray-800" x-text="order.transcript"></p>
                                    <p class="text-xs text-gray-400 mt-1" x-text="order.created_at"></p>
                                </div>
                                <span :class="{
                                    'bg-yellow-100 text-yellow-800': order.status === 'pending',
                                    'bg-green-100 text-green-800': order.status === 'sent',
                                    'bg-red-100 text-red-800': order.status === 'failed'
                                }" class="px-2 py-1 rounded text-xs font-medium" x-text="order.status"></span>
                            </div>
                        </div>
                    </template>
                    <div x-show="loading" class="text-center text-gray-400 text-sm py-4">Loading...</div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    @endpush
</x-app-layout>
