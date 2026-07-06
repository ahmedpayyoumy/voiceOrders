import { Scribe, CommitStrategy, RealtimeEvents } from '@elevenlabs/client';

function apiFetch(url, options = {}) {
    return fetch(url, {
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            ...options.headers,
        },
        ...options,
    });
}

export function voiceCapture(defaults = {}) {
    return {
        isRecording: false,
        transcript: '',
        isSending: false,
        error: '',
        successMessage: '',
        connection: null,
        recordingTimer: null,
        isEditing: false,
        isFixing: false,

        daftraDomain: defaults.daftraDomain || '',
        daftraApiKey: defaults.daftraApiKey || '',
        showDaftraFields: true,
        daftraResult: null,
        saveCredentials: false,

        // New: Product clarification state
        clarificationNeeded: false,
        clarificationType: '', // 'customer' or 'product'
        clarificationQuestion: '',
        alternatives: [],
        matchedItems: [],
        invoicePreview: null,
        productQuery: '',
        selectedCustomerId: null,
        selectedCustomer: null,
        selectedProductName: null,
        selectedProducts: [],

        // Confirmation step
        needsConfirmation: false,
        confirmationCustomer: null,
        confirmationItems: [],
        confirmationTotal: 0,
        isConfirming: false,

        async init() {
            try {
                const res = await apiFetch('/api/daftra/credentials');
                if (res.ok) {
                    const data = await res.json();
                    if (data.daftra_domain) {
                        this.daftraDomain = data.daftra_domain;
                    }
                    if (data.has_api_key && this.daftraDomain) {
                        this.saveCredentials = true;
                    }
                }
            } catch (err) {
                // Credentials fetch is best-effort
            }
        },

        async toggleRecording() {
            if (this.isRecording) {
                this.stopRecording();
            } else {
                await this.startRecording();
            }
        },

        async startRecording() {
            this.error = '';
            this.successMessage = '';
            this.isEditing = false;
            this.daftraResult = null;

            try {
                const res = await apiFetch('/api/elevenlabs/token');
                const data = await res.json();

                if (!res.ok) {
                    this.error = 'Failed to connect: ' + (data.error || 'Unknown error');
                    return;
                }

                const connection = Scribe.connect({
                    token: data.token,
                    modelId: data.model_id,
                    microphone: {
                        echoCancellation: true,
                        noiseSuppression: true,
                    },
                    commitStrategy: CommitStrategy.VAD,
                    vadSilenceThresholdSecs: 1.5,
                });

                connection.on(RealtimeEvents.PARTIAL_TRANSCRIPT, (msg) => {
                    this.transcript = msg.text;
                });

                connection.on(RealtimeEvents.COMMITTED_TRANSCRIPT, (msg) => {
                    this.transcript = msg.text;
                });

                connection.on(RealtimeEvents.ERROR, (err) => {
                    this.error = err.message || 'Transcription error';
                });

                connection.on(RealtimeEvents.CLOSE, () => {
                    this.isRecording = false;
                    this.connection = null;
                    this.recordingTimer = null;
                });

                this.connection = connection;
                this.isRecording = true;

                this.recordingTimer = setTimeout(() => {
                    if (this.isRecording) {
                        this.stopRecording();
                        this.error = 'Recording limit reached (60s max)';
                    }
                }, 60000);

            } catch (err) {
                this.error = 'Microphone access denied or connection failed';
            }
        },

        stopRecording() {
            if (this.recordingTimer) {
                clearTimeout(this.recordingTimer);
                this.recordingTimer = null;
            }
            if (this.connection) {
                this.connection.close();
                this.connection = null;
            }
            this.isRecording = false;
            this.isEditing = true;
        },

        async suggestCorrection() {
            if (!this.transcript || this.isFixing) return;

            this.isFixing = true;
            this.error = '';

            try {
                const res = await apiFetch('/api/transcript/suggest', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ transcript: this.transcript }),
                });

                if (res.ok) {
                    const data = await res.json();
                    if (data.corrected) {
                        this.transcript = data.corrected;
                    }
                } else {
                    const errData = await res.json();
                    this.error = errData.error || 'Failed to get suggestion';
                }
            } catch (err) {
                this.error = 'Network error';
            } finally {
                this.isFixing = false;
            }
        },

        async interpretQuery() {
            if (!this.transcript) return;

            this.error = '';
            this.daftraResult = null;

            try {
                const res = await apiFetch('/api/daftra/interpret', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        transcript: this.transcript,
                        daftra_domain: this.daftraDomain || null,
                        daftra_api_key: this.daftraApiKey || null,
                    }),
                });

                const data = await res.json();
                this.daftraResult = data;

                if (data.needs_clarification) {
                    this.error = data.question || 'Query incomplete';
                } else if (data.formatted) {
                    this.successMessage = data.formatted;
                    setTimeout(() => this.successMessage = '', 8000);
                } else if (data.error) {
                    this.error = data.error;
                }
            } catch (err) {
                this.error = 'Network error';
            }
        },

        async sendOrder() {
            if (!this.transcript) return;

            this.isSending = true;
            this.error = '';
            this.successMessage = '';
            this.clarificationNeeded = false;
            this.clarificationType = '';
            this.clarificationQuestion = '';
            this.alternatives = [];
            this.matchedItems = [];
            this.invoicePreview = null;
            this.productQuery = '';
            this.selectedCustomerId = null;
            this.selectedCustomer = null;
            this.selectedProductName = null;
            this.selectedProducts = [];
            this.needsConfirmation = false;
            this.confirmationCustomer = null;
            this.confirmationItems = [];
            this.confirmationTotal = 0;

            try {
                if (!this.daftraDomain || !this.daftraApiKey) {
                    this.error = 'Please provide Daftra domain and API key';
                    return;
                }

                const res = await apiFetch('/api/orders/daftra', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        transcript: this.transcript,
                        daftra_domain: this.daftraDomain,
                        daftra_api_key: this.daftraApiKey,
                    }),
                });

                const data = await res.json();
                console.debug('[sendOrder] response:', data);

                const needsClarification = Boolean(
                    data.status === 'needs_clarification' ||
                    data.needs_clarification ||
                    (data.question && Array.isArray(data.alternatives))
                );

                // Handle clarification needed
                if (needsClarification) {
                    this.clarificationNeeded = true;
                    this.clarificationType = data.clarification_type || 'product';
                    this.clarificationQuestion = data.question || 'Which one did you mean?';
                    this.alternatives = data.alternatives || [];
                    this.matchedItems = data.matched_items || [];
                    this.productQuery = data.product_query || '';
                    this.daftraResult = data;
                    this.error = '';
                    if (data.customer) {
                        this.selectedCustomerId = data.customer.Client?.id ?? null;
                        this.selectedCustomer = data.customer;
                    }
                    return;
                }

                if (!res.ok) {
                    this.error = data.error || 'Failed to create invoice';
                    return;
                }

                // Success - invoice created
                if (data.status === 'success') {
                    this.invoicePreview = data.preview;
                    this.successMessage = `Invoice created! ${data.preview.customer} - ${data.preview.total} SAR`;
                    
                    if (this.saveCredentials) {
                        this.saveDaftraCredentials();
                    }

                    setTimeout(() => {
                        this.successMessage = '';
                        this.transcript = '';
                        this.isEditing = false;
                        this.invoicePreview = null;
                    }, 5000);
                }

            } catch (err) {
                this.error = 'Network error: ' + err.message;
            } finally {
                this.isSending = false;
            }
        },

        async selectAlternative(alternative) {
            console.debug('[selectAlternative] type:', this.clarificationType, 'alt:', alternative);
            this.clarificationNeeded = false;
            this.error = '';

            if (this.clarificationType === 'needs_customer') {
                const customerId = alternative.Client?.id;
                if (!customerId) return;

                this.selectedCustomerId = customerId;
                this.isSending = true;
                try {
                    const res = await apiFetch('/api/orders/daftra', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            transcript: this.transcript,
                            daftra_domain: this.daftraDomain,
                            daftra_api_key: this.daftraApiKey,
                            selected_customer_id: customerId,
                        }),
                    });

                    const data = await res.json();
                    this.handleOrderResponse(data);
                } catch (err) {
                    this.error = 'Network error: ' + err.message;
                } finally {
                    this.isSending = false;
                }
                return;
            }

            if (this.clarificationType === 'needs_product') {
                const productId = alternative.Product?.id;
                const productQuery = this.productQuery;
                if (!productId || !productQuery) {
                    console.debug('[selectAlternative] missing productId or productQuery', {productId, productQuery});
                    return;
                }

                this.selectedProductName = alternative.Product?.name || productQuery;
                // Accumulate selected product
                this.selectedProducts.push({ product_id: productId, query: productQuery });
                this.isSending = true;
                console.debug('[selectAlternative] sending product selection', {productId, productQuery, customerId: this.selectedCustomerId, allSelected: this.selectedProducts});
                try {
                    const res = await apiFetch('/api/orders/daftra', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            transcript: this.transcript,
                            daftra_domain: this.daftraDomain,
                            daftra_api_key: this.daftraApiKey,
                            selected_customer_id: this.selectedCustomerId,
                            selected_product_id: productId,
                            selected_product_query: productQuery,
                            selected_products: this.selectedProducts,
                        }),
                    });

                    const data = await res.json();
                    this.handleOrderResponse(data);
                } catch (err) {
                    this.error = 'Network error: ' + err.message;
                } finally {
                    this.isSending = false;
                }
                return;
            }
        },

        handleOrderResponse(data) {
            console.debug('[handleOrderResponse]', data);
            this.daftraResult = data;

            if (data.status === 'success') {
                this.invoicePreview = data.preview;
                this.successMessage = `Invoice ${data.preview.invoice_number} created! ${data.preview.customer} - ${data.preview.total} SAR`;
                if (this.saveCredentials) this.saveDaftraCredentials();
                setTimeout(() => {
                    this.successMessage = '';
                    this.transcript = '';
                    this.isEditing = false;
                    this.invoicePreview = null;
                }, 5000);
            } else if (data.status === 'needs_confirmation') {
                this.needsConfirmation = true;
                this.confirmationCustomer = data.customer;
                this.confirmationItems = (data.items || []).map(item => ({ ...item }));
                this.confirmationTotal = data.total || 0;
                if (data.customer) {
                    this.selectedCustomerId = data.customer.Client?.id ?? null;
                    this.selectedCustomer = data.customer;
                }
            } else if (data.needs_clarification || data.status === 'needs_clarification') {
                this.clarificationNeeded = true;
                this.clarificationType = data.clarification_type || 'product';
                this.clarificationQuestion = data.question || 'Which one did you mean?';
                this.alternatives = data.alternatives || [];
                this.matchedItems = data.matched_items || [];
                this.productQuery = data.product_query || '';
                if (data.customer) {
                    this.selectedCustomerId = data.customer.Client?.id ?? null;
                    this.selectedCustomer = data.customer;
                }
            } else if (data.error) {
                this.error = data.error;
            }
        },

        async confirmOrder() {
            if (!this.confirmationItems.length || !this.selectedCustomerId) return;

            this.isConfirming = true;
            this.error = '';
            this.successMessage = '';

            try {
                const res = await apiFetch('/api/orders/daftra', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        transcript: this.transcript,
                        daftra_domain: this.daftraDomain,
                        daftra_api_key: this.daftraApiKey,
                        confirmed: true,
                        selected_customer_id: this.selectedCustomerId,
                        selected_product_id: this.confirmationItems[0]?.product_id || null,
                        items: this.confirmationItems,
                    }),
                });

                const data = await res.json();
                this.needsConfirmation = false;
                this.confirmationCustomer = null;
                this.confirmationItems = [];
                this.confirmationTotal = 0;
                this.handleOrderResponse(data);
            } catch (err) {
                this.error = 'Network error: ' + err.message;
            } finally {
                this.isConfirming = false;
            }
        },

        async saveDaftraCredentials() {
            try {
                await apiFetch('/api/daftra/credentials', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        daftra_domain: this.daftraDomain,
                        daftra_api_key: this.daftraApiKey,
                    }),
                });
            } catch (err) {
                // Silent fail for credential save
            }
        },

        async deleteDaftraCredentials() {
            try {
                await apiFetch('/api/daftra/credentials', { method: 'DELETE' });
                this.daftraDomain = '';
                this.daftraApiKey = '';
                this.saveCredentials = false;
            } catch (err) {
                this.error = 'Failed to remove credentials';
            }
        },

        clearTranscript() {
            this.transcript = '';
            this.error = '';
            this.successMessage = '';
            this.isEditing = false;
            this.daftraResult = null;
            this.selectedCustomerId = null;
            this.selectedCustomer = null;
            this.selectedProductName = null;
            this.selectedProducts = [];
            this.needsConfirmation = false;
            this.confirmationCustomer = null;
            this.confirmationItems = [];
            this.confirmationTotal = 0;
        },
    };
}

export function orderHistory() {
    return {
        orders: [],
        loading: true,

        async init() {
            await this.loadOrders();
        },

        async loadOrders() {
            this.loading = true;
            try {
                const res = await apiFetch('/api/orders');
                if (res.ok) {
                    const data = await res.json();
                    this.orders = data.data || [];
                }
            } catch (err) {
                console.error('Failed to load orders', err);
            } finally {
                this.loading = false;
            }
        },
    };
}
