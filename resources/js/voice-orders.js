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
                });

                this.connection = connection;
                this.isRecording = true;

            } catch (err) {
                this.error = 'Microphone access denied or connection failed';
            }
        },

        stopRecording() {
            if (this.connection) {
                this.connection.close();
                this.connection = null;
            }
            this.isRecording = false;
        },

        async sendOrder() {
            if (!this.transcript) return;

            this.isSending = true;
            this.error = '';
            this.successMessage = '';

            try {
                const res = await apiFetch('/api/orders', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ transcript: this.transcript }),
                });

                if (res.ok) {
                    this.successMessage = 'Order sent successfully!';
                    this.transcript = '';
                    setTimeout(() => this.successMessage = '', 3000);
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

        clearTranscript() {
            this.transcript = '';
            this.error = '';
            this.successMessage = '';
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
