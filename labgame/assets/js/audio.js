const AudioFX = {
    sounds: {},
    enabled: true,

    init() {
        this.enabled = localStorage.getItem('labgame_sound') !== 'off';
    },

    toggle() {
        this.enabled = !this.enabled;
        localStorage.setItem('labgame_sound', this.enabled ? 'on' : 'off');
        return this.enabled;
    },

    play(name) {
        if (!this.enabled) return;
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            gain.gain.value = 0.15;

            switch(name) {
                case 'countdown':
                    osc.frequency.value = 880;
                    osc.type = 'square';
                    gain.gain.value = 0.2;
                    osc.start(); osc.stop(ctx.currentTime + 0.15);
                    break;
                case 'go':
                    osc.frequency.value = 1200;
                    osc.type = 'sine';
                    gain.gain.value = 0.25;
                    osc.start(); osc.stop(ctx.currentTime + 0.4);
                    setTimeout(() => {
                        const o2 = ctx.createOscillator();
                        const g2 = ctx.createGain();
                        o2.connect(g2); g2.connect(ctx.destination);
                        o2.frequency.value = 1500; o2.type = 'sine';
                        g2.gain.value = 0.25;
                        o2.start(); o2.stop(ctx.currentTime + 0.3);
                    }, 150);
                    break;
                case 'correct':
                    osc.frequency.value = 523;
                    osc.type = 'sine';
                    gain.gain.value = 0.2;
                    osc.start();
                    osc.frequency.linearRampToValueAtTime(1047, ctx.currentTime + 0.15);
                    osc.stop(ctx.currentTime + 0.2);
                    break;
                case 'wrong':
                    osc.frequency.value = 300;
                    osc.type = 'sawtooth';
                    gain.gain.value = 0.15;
                    osc.start();
                    osc.frequency.linearRampToValueAtTime(150, ctx.currentTime + 0.3);
                    osc.stop(ctx.currentTime + 0.35);
                    break;
                case 'powerup':
                    [600, 800, 1000, 1200].forEach((f, i) => {
                        setTimeout(() => {
                            const o = ctx.createOscillator();
                            const g = ctx.createGain();
                            o.connect(g); g.connect(ctx.destination);
                            o.frequency.value = f; o.type = 'sine';
                            g.gain.value = 0.15;
                            o.start(); o.stop(ctx.currentTime + 0.1);
                        }, i * 80);
                    });
                    break;
                case 'bubble':
                    osc.frequency.value = 400;
                    osc.type = 'sine';
                    gain.gain.value = 0.2;
                    osc.start();
                    osc.frequency.linearRampToValueAtTime(200, ctx.currentTime + 0.5);
                    osc.stop(ctx.currentTime + 0.6);
                    break;
                case 'finish':
                    [523, 659, 784, 1047].forEach((f, i) => {
                        setTimeout(() => {
                            const o = ctx.createOscillator();
                            const g = ctx.createGain();
                            o.connect(g); g.connect(ctx.destination);
                            o.frequency.value = f; o.type = 'sine';
                            g.gain.value = 0.2;
                            o.start(); o.stop(ctx.currentTime + 0.25);
                        }, i * 150);
                    });
                    break;
            }

            setTimeout(() => ctx.close(), 1000);
        } catch(e) {}
    }
};

AudioFX.init();
