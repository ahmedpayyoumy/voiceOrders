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

export function voiceCapture() {
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

        daftraDomain: '',
        daftraApiKey: '',
        showDaftraFields: true,
        daftraResult: null,
        saveCredentials: false,

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

            try {
                const credsProvided = this.daftraDomain && this.daftraApiKey;

                const interpretRes = await apiFetch('/api/daftra/interpret', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        transcript: this.transcript,
                        daftra_domain: this.daftraDomain || null,
                        daftra_api_key: this.daftraApiKey || null,
                    }),
                });

                const interpretData = await interpretRes.json();

                if (!interpretRes.ok) {
                    this.error = interpretData.error || 'Failed to interpret transcript';
                    return;
                }

                if (interpretData.needs_clarification) {
                    this.error = interpretData.question || 'Could not understand the request';
                    this.daftraResult = interpretData;
                    return;
                }

                const intentType = interpretData.intent?.type;

                if (intentType !== 'create') {
                    this.daftraResult = interpretData;
                    if (interpretData.formatted) {
                        this.successMessage = interpretData.formatted;
                        setTimeout(() => this.successMessage = '', 10000);
                    }
                    return;
                }

                const body = credsProvided
                    ? {
                        transcript: this.transcript,
                        daftra_domain: this.daftraDomain,
                        daftra_api_key: this.daftraApiKey,
                    }
                    : { transcript: this.transcript };

                const res = await apiFetch(
                    credsProvided ? '/api/orders/daftra' : '/api/orders',
                    {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(body),
                    }
                );

                if (res.ok) {
                    const data = await res.json();

                    if (data.daftra) {
                        this.successMessage = 'Order sent to Daftra! Invoice ID: ' + (data.daftra.id || 'created');
                        this.daftraResult = data.daftra;
                        setTimeout(() => this.successMessage = '', 5000);
                    } else {
                        this.successMessage = 'Order sent successfully!';
                        setTimeout(() => this.successMessage = '', 3000);
                    }

                    if (this.saveCredentials && credsProvided) {
                        this.saveDaftraCredentials();
                    }

                    this.transcript = '';
                    this.isEditing = false;
                } else {
                    const errData = await res.json();
                    this.error = errData.error || 'Failed to send order';
                }
            } catch (err) {
                this.error = 'Network error';
            } finally {
                this.isSending = false;
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
