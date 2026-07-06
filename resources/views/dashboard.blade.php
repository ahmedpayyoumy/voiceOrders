<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Voice Orders') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div x-data="voiceCapture({
                    daftraDomain: @js(config('daftra.domain')),
                    daftraApiKey: @js(config('daftra.api_key')),
                })">
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
                    <div x-show="successMessage" x-text="successMessage" class="text-green-600 text-center mb-4 text-sm"></div>

                    <div class="bg-gray-50 rounded-lg p-4 mb-6 min-h-[100px]">
                        <p class="text-gray-400 text-sm mb-2">Transcript:</p>
                        <textarea x-show="!isRecording"
                                  x-model="transcript"
                                  class="w-full border border-gray-300 rounded-lg p-2 text-gray-800 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                  rows="3"
                                  placeholder="Type your order or speak with the microphone..."></textarea>
                        <p x-show="isRecording" x-text="transcript || 'Listening...'" class="text-gray-800 min-h-[72px]"></p>
                    </div>

                    <div x-show="transcript" class="space-y-4 mb-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Daftra Domain</label>
                                <input x-model="daftraDomain" type="text" placeholder="your-domain (e.g. mycompany)"
                                       class="w-full border border-gray-300 rounded-lg p-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Daftra API Key</label>
                                <input x-model="daftraApiKey" type="password" placeholder="Enter your Daftra API key"
                                       class="w-full border border-gray-300 rounded-lg p-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                        <div class="flex items-center gap-4 text-sm">
                            <label class="flex items-center gap-2 cursor-pointer text-gray-600 hover:text-gray-800">
                                <input x-model="saveCredentials" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                Save credentials
                            </label>
                            <button x-show="daftraDomain"
                                    @click="deleteDaftraCredentials"
                                    class="text-red-500 hover:text-red-700 underline">
                                Clear saved
                            </button>
                        </div>
                    </div>

                    <template x-if="daftraResult && daftraResult.intent">
                        <div class="bg-indigo-50 rounded-lg p-4 mb-6 text-sm">
                            <p class="font-medium text-indigo-800 mb-1">Interpreted:</p>
                            <p class="text-indigo-700">
                                <span class="font-medium" x-text="daftraResult.intent.type"></span>
                                <span x-text="daftraResult.intent.module"></span>
                                <span x-show="Object.keys(daftraResult.intent.filters || {}).length">filtered by </span>
                                <template x-for="(value, key) in (daftraResult.intent.filters || {})">
                                    <span class="font-medium" x-text="key + ': ' + value"></span>
                                </template>
                            </p>
                        </div>
                    </template>

                    <!-- Step Summary (always visible when customer is resolved) -->
                    <div x-show="selectedCustomer" class="space-y-2 mb-4">
                        <div class="flex items-center gap-2 text-sm" :class="selectedCustomer ? 'text-green-700' : 'text-gray-400'">
                            <span class="w-6 h-6 rounded-full bg-green-100 text-green-600 flex items-center justify-center text-xs font-bold">1</span>
                            <span class="font-medium">Customer:</span>
                            <span x-text="selectedCustomer?.Client?.business_name || selectedCustomer?.Client?.name || '—'"></span>
                        </div>
                        <div class="flex items-center gap-2 text-sm" :class="confirmationItems.length || selectedProducts.length ? 'text-green-700' : 'text-gray-400'">
                            <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold"
                                  :class="confirmationItems.length || selectedProducts.length ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-400'">2</span>
                            <span class="font-medium">Products:</span>
                            <span x-text="confirmationItems.map(i => i.product_name).join(', ') || matchedItems.map(i => i.product_name).join(', ') || selectedProducts.map(i => i.query).join(', ') || '—'"></span>
                        </div>
                    </div>

                    <!-- NEW: Product/Customer Clarification UI -->
                    <div x-show="clarificationNeeded || alternatives.length || clarificationQuestion" class="bg-yellow-50 border-2 border-yellow-300 rounded-lg p-6 mb-6">
                        <div class="flex items-start gap-3 mb-4">
                            <svg class="w-6 h-6 text-yellow-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <div class="flex-1">
                                <h3 class="font-semibold text-yellow-900 text-lg mb-2">Which one did you mean?</h3>

                                <!-- Customer badge when auto-matched -->
                                <div x-show="selectedCustomer && clarificationType === 'needs_product'" class="mb-4 p-3 bg-white rounded-lg border border-yellow-200">
                                    <span class="text-xs font-medium text-yellow-700">Selected Customer:</span>
                                    <span class="text-sm font-semibold text-gray-900 ml-1" x-text="selectedCustomer?.Client?.business_name || selectedCustomer?.Client?.name"></span>
                                </div>

                                <p class="text-yellow-800 mb-4" x-text="clarificationQuestion"></p>
                                
                                <!-- Product alternatives -->
                                <div x-show="clarificationType === 'needs_product'" class="space-y-2">
                                    <template x-for="alt in alternatives" :key="alt.Product.id">
                                        <button @click="selectAlternative(alt)" 
                                                class="w-full text-left p-3 bg-white hover:bg-yellow-100 border border-yellow-200 rounded-lg transition-colors group">
                                            <div class="flex justify-between items-start">
                                                <div>
                                                    <p class="font-medium text-gray-900" x-text="alt.Product.name"></p>
                                                    <p class="text-sm text-gray-600" x-show="alt.Product.unit_price">
                                                        <span x-text="alt.Product.unit_price"></span> SAR
                                                        <span x-show="alt.Product.stock_quantity" class="ml-2">
                                                            • Stock: <span x-text="alt.Product.stock_quantity"></span>
                                                        </span>
                                                    </p>
                                                </div>
                                                <svg class="w-5 h-5 text-yellow-600 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                                </svg>
                                            </div>
                                        </button>
                                    </template>
                                </div>

                                <!-- Customer alternatives -->
                                <div x-show="clarificationType === 'needs_customer'" class="space-y-2">
                                    <template x-for="alt in alternatives" :key="alt.Client?.id">
                                        <button @click="selectAlternative(alt)" 
                                                class="w-full text-left p-3 bg-white hover:bg-yellow-100 border border-yellow-200 rounded-lg transition-colors group">
                                            <p class="font-medium text-gray-900" x-text="alt.Client?.business_name || alt.Client?.name || '—'"></p>
                                            <p class="text-sm text-gray-600">
                                                <span x-show="alt.Client?.phone1" x-text="alt.Client?.phone1 || ''"></span>
                                                <span x-show="alt.Client?.email" class="ml-2" x-text="alt.Client?.email || ''"></span>
                                            </p>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Confirmation Step -->
                    <div x-show="needsConfirmation" class="bg-blue-50 border-2 border-blue-300 rounded-lg p-6 mb-6">
                        <div class="flex items-start gap-3">
                            <svg class="w-6 h-6 text-blue-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <div class="flex-1">
                                <h3 class="font-semibold text-blue-900 text-lg mb-2">Step 3: Set Quantities & Confirm</h3>
                                <div class="bg-white rounded-lg p-4 border border-blue-200 space-y-3">
                                    <template x-for="(item, index) in confirmationItems" :key="item.product_id">
                                        <div class="flex items-center gap-3">
                                            <span class="text-gray-700 min-w-0 flex-1" x-text="item.product_name"></span>
                                            <input type="number" x-model="confirmationItems[index].quantity" min="0.01" step="1"
                                                   class="w-20 border border-gray-300 rounded px-2 py-1 text-sm text-center focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                            <span class="text-gray-500 text-sm w-16 text-right" x-text="(item.price * confirmationItems[index].quantity).toFixed(2) + ' SAR'"></span>
                                        </div>
                                    </template>
                                    <div class="border-t border-gray-200 pt-2 flex justify-between font-semibold text-gray-900">
                                        <span>Total:</span>
                                        <span x-text="confirmationItems.reduce((sum, item) => sum + (item.price * item.quantity), 0).toFixed(2) + ' SAR'"></span>
                                    </div>
                                </div>
                                <div class="flex gap-3 mt-4">
                                    <button @click="confirmOrder" :disabled="isConfirming"
                                            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-sm">
                                        <span x-show="!isConfirming">Confirm & Create Invoice</span>
                                        <span x-show="isConfirming">Creating...</span>
                                    </button>
                                    <button @click="needsConfirmation = false; confirmationCustomer = null; confirmationItems = []; confirmationTotal = 0"
                                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors text-sm">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Invoice Preview -->
                    <div x-show="invoicePreview" class="bg-green-50 border-2 border-green-300 rounded-lg p-6 mb-6">
                        <div class="flex items-start gap-3">
                            <svg class="w-6 h-6 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <div class="flex-1">
                                <h3 class="font-semibold text-green-900 text-lg mb-2">Invoice Preview</h3>
                                <div class="bg-white rounded-lg p-4 border border-green-200">
                                    <p class="font-medium text-gray-900 mb-2">
                                        Customer: <span x-text="invoicePreview?.customer"></span>
                                    </p>
                                    <div class="space-y-1 mb-3">
                                        <template x-for="item in (invoicePreview?.items || [])" :key="item.product_id">
                                            <div class="flex justify-between text-sm">
                                                <span class="text-gray-700">
                                                    <span x-text="item.product_name"></span> × <span x-text="item.quantity"></span>
                                                </span>
                                                <span class="font-medium text-gray-900" x-text="item.total + ' SAR'"></span>
                                            </div>
                                        </template>
                                    </div>
                                    <div class="border-t border-gray-200 pt-2">
                                        <div class="flex justify-between font-semibold text-gray-900">
                                            <span>Total:</span>
                                            <span x-text="invoicePreview?.total + ' SAR'"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap justify-center gap-3">
                        <button x-show="!isRecording && isEditing && transcript"
                                @click="suggestCorrection"
                                :disabled="isFixing"
                                class="px-4 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-sm">
                            <span x-show="!isFixing">Suggest fix</span>
                            <span x-show="isFixing">Fixing...</span>
                        </button>

                        <button x-show="!isRecording && transcript"
                                @click="interpretQuery"
                                class="px-4 py-2 bg-indigo-500 text-white rounded-lg hover:bg-indigo-600 transition-colors text-sm">
                            Interpret
                        </button>

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
                                    <div class="flex items-center gap-2 mt-1">
                                        <p class="text-xs text-gray-400" x-text="order.created_at"></p>
                                        <template x-if="order.response_log?.[0]?.daftra?.invoice_number || order.parsed_data?.invoice_number">
                                            <span class="text-xs font-medium text-indigo-600" x-text="'Invoice: ' + (order.response_log?.[0]?.daftra?.invoice_number || order.parsed_data?.invoice_number)"></span>
                                        </template>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <template x-if="order.response_log?.[0]?.daftra?.result === 'successful'">
                                        <span class="px-2 py-1 rounded text-xs font-medium bg-indigo-100 text-indigo-700">Daftra Invoice</span>
                                    </template>
                                    <span :class="{
                                        'bg-yellow-100 text-yellow-800': order.status === 'pending',
                                        'bg-green-100 text-green-800': order.status === 'sent',
                                        'bg-red-100 text-red-800': order.status === 'failed'
                                    }" class="px-2 py-1 rounded text-xs font-medium" x-text="order.status"></span>
                                </div>
                            </div>
                        </div>
                    </template>
                    <div x-show="loading" class="text-center text-gray-400 text-sm py-4">Loading...</div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
