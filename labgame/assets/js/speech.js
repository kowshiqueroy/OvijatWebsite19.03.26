const SpeechHelper = {
    enabled: true,
    voice: null,

    init() {
        this.enabled = localStorage.getItem('labgame_speech') !== 'off';
        if (window.speechSynthesis) {
            const voices = window.speechSynthesis.getVoices();
            this.voice = voices.find(v => v.lang.startsWith('en')) || voices[0];
            window.speechSynthesis.onvoiceschanged = () => {
                const v = window.speechSynthesis.getVoices();
                this.voice = v.find(v => v.lang.startsWith('en')) || v[0];
            };
        }
    },

    toggle() {
        this.enabled = !this.enabled;
        localStorage.setItem('labgame_speech', this.enabled ? 'on' : 'off');
        return this.enabled;
    },

    speak(text, callback) {
        if (!this.enabled || !window.speechSynthesis) {
            if (callback) callback();
            return;
        }
        window.speechSynthesis.cancel();
        const utter = new SpeechSynthesisUtterance(text);
        utter.rate = 0.9;
        utter.pitch = 1.1;
        utter.volume = 0.8;
        if (this.voice) utter.voice = this.voice;
        if (callback) utter.onend = callback;
        window.speechSynthesis.speak(utter);
    },

    speakQuestion(question, answers) {
        let text = question;
        if (answers && answers.length > 0) {
            text += '. Choices: ' + answers.map((a, i) => `option ${String.fromCharCode(65 + i)}: ${a}`).join('. ') + '.';
        }
        this.speak(text);
    }
};

SpeechHelper.init();
