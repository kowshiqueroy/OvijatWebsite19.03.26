const SSEClient = {
    eventSource: null,
    lastTick: 0,
    onRaceUpdate: null,
    connected: false,
    reconnectTimer: null,
    lastRaceData: null,

    connect(roomCode) {
        if (this.eventSource) this.disconnect();

        const url = `api/sse.php?room=${encodeURIComponent(roomCode)}&last_tick=${this.lastTick}`;
        this.eventSource = new EventSource(url);

        this.eventSource.addEventListener('race', (e) => {
            try {
                const data = JSON.parse(e.data);
                this.lastTick = data.tick;
                this.lastRaceData = data;
                if (this.onRaceUpdate) this.onRaceUpdate(data);
            } catch(err) {}
        });

        this.eventSource.addEventListener('error', () => {
            this.connected = false;
            if (this.reconnectTimer) clearTimeout(this.reconnectTimer);
            this.reconnectTimer = setTimeout(() => {
                this.connect(roomCode);
            }, 3000);
        });

        this.eventSource.onopen = () => {
            this.connected = true;
        };
    },

    disconnect() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
        if (this.reconnectTimer) {
            clearTimeout(this.reconnectTimer);
            this.reconnectTimer = null;
        }
        this.connected = false;
    }
};
