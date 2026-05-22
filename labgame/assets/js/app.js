const Game = {
    state: 'idle',
    roomId: ROOM_ID,
    roomCode: ROOM_CODE,
    studentId: STUDENT_ID,
    currentQuestion: null,
    streak: 0,
    hasFire: false,
    myPosition: 0,
    isFinished: false,
    questionCount: 0,
    qTimer: null,
    qTimeLeft: 30,
    raceTimeLeft: DURATION_MINUTES * 60,
    countdownActive: false,

    async init() {
        RaceCanvas.init();
        document.getElementById('btnBackToLobby').addEventListener('click', () => {
            window.location.href = 'index.php';
        });
        document.getElementById('btnFire').addEventListener('click', () => this.useFire());
        this.setupAnswerButtons();
        this.setupToggleListeners();

        SSEClient.onRaceUpdate = (data) => this.onRaceUpdate(data);
        SSEClient.connect(this.roomCode);
        this.showToast('⏳ Waiting for race to start...');
    },

    setupAnswerButtons() {
        document.querySelectorAll('.ans-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (this.state !== 'answering') return;
                const index = parseInt(btn.dataset.index);
                await this.submitAnswer(index);
            });
        });
    },

    setupToggleListeners() {
        document.addEventListener('keydown', (e) => {
            if (e.key === 's') { AudioFX.toggle(); this.showToast(AudioFX.enabled ? '🔊 Sound On' : '🔇 Sound Off'); }
            if (e.key === 'v') { SpeechHelper.toggle(); this.showToast(SpeechHelper.enabled ? '🗣️ Voice On' : '🔇 Voice Off'); }
            if (['1','2','3','4'].includes(e.key) && this.state === 'answering') {
                document.querySelectorAll('.ans-btn')[parseInt(e.key)-1]?.click();
            }
        });
    },

    showToast(msg) {
        let t = document.getElementById('toastMsg');
        if (!t) { t = document.createElement('div'); t.id = 'toastMsg'; document.body.appendChild(t); }
        t.textContent = msg; t.className = 'toast-show';
        clearTimeout(this._tt);
        this._tt = setTimeout(() => t.className = 'hidden', 2000);
    },

    startCountdown() {
        if (this.countdownActive) return;
        this.countdownActive = true;
        this.state = 'countdown';
        const overlay = document.getElementById('countdownOverlay');
        const text = document.getElementById('countdownText');
        overlay.classList.remove('hidden');
        let count = 3;
        text.textContent = count;
        AudioFX.play('countdown');
        const interval = setInterval(() => {
            count--;
            if (count > 0) { text.textContent = count; AudioFX.play('countdown'); }
            else if (count === 0) { text.textContent = 'GO!'; AudioFX.play('go'); }
            else {
                clearInterval(interval);
                overlay.classList.add('hidden');
                this.countdownActive = false;
                this.state = 'racing';
                this.fetchFirstQuestion();
            }
        }, 1000);
    },

    async fetchFirstQuestion() {
        try {
            const resp = await fetch(`api/question.php?room_code=${encodeURIComponent(this.roomCode)}`);
            const data = await resp.json();
            if (data.success) this.displayQuestion(data);
        } catch(e) {}
    },

    displayQuestion(q) {
        this.currentQuestion = q;
        this.questionCount++;
        this.state = 'answering';

        const answers = JSON.parse(q.answers_json);
        document.getElementById('qVisual').innerHTML = q.type === 'math'
            ? `<span class="q-visual-math">${q.question_text}</span>`
            : q.type === 'pattern'
            ? `<span class="q-visual-pattern">${q.question_text}</span>`
            : q.type === 'word'
            ? `<span class="q-visual-word">${q.question_text}</span>`
            : q.question_text;

        const typeMap = { math: '🧮 Math', word: '📖 Word', pattern: '🧩 Pattern' };
        document.getElementById('qTypeBadge').textContent = typeMap[q.type] || q.type;
        document.getElementById('sideQuestionNum').textContent = `Q${this.questionCount}`;

        document.querySelectorAll('.ans-btn').forEach((btn, i) => {
            btn.textContent = answers[i] || '?';
            btn.dataset.index = i;
            btn.className = `ans-btn ans-${String.fromCharCode(97 + i)}`;
            btn.disabled = false;
        });

        document.getElementById('sideStreak').textContent = this.streak > 0 ? `🔥 ${this.streak}` : '🔥 0';
        this.updateFireBtn();

        this.qTimeLeft = 30;
        const timer = document.getElementById('qTimer');
        timer.textContent = '⏱️ 30';
        timer.style.color = '#ffd700';
        timer.style.animation = 'none';
        if (this.qTimer) clearInterval(this.qTimer);
        this.qTimer = setInterval(() => {
            this.qTimeLeft--;
            timer.textContent = `⏱️ ${this.qTimeLeft}`;
            if (this.qTimeLeft <= 10) {
                timer.style.color = '#ff4444';
                timer.style.animation = 'timerPulse 0.5s infinite';
            }
            if (this.qTimeLeft <= 0) {
                clearInterval(this.qTimer);
                this.qTimer = null;
                this.submitAnswer(-1);
            }
        }, 1000);

        SpeechHelper.speakQuestion(q.question_text, answers);
    },

    async submitAnswer(index) {
        if (this.state !== 'answering') return;
        this.state = 'submitted';
        if (this.qTimer) { clearInterval(this.qTimer); this.qTimer = null; }
        document.querySelectorAll('.ans-btn').forEach(b => b.disabled = true);

        const fd = new FormData();
        fd.append('room_id', this.roomId);
        fd.append('question_id', this.currentQuestion.id);
        fd.append('answer_index', index);

        try {
            const resp = await fetch('api/answer.php', { method: 'POST', body: fd });
            const data = await resp.json();

            if (data.success) {
                if (data.is_correct) {
                    AudioFX.play('correct');
                    SpeechHelper.speak('Correct!');
                    this.streak = data.new_streak || 0;
                    this.hasFire = data.has_fire || false;
                    this.showFeedback(true);
                } else {
                    AudioFX.play('wrong');
                    SpeechHelper.speak('Oops!');
                    this.streak = 0;
                    this.showFeedback(false);
                }
                this.updateFireBtn();
                document.getElementById('sideStreak').textContent = this.streak > 0 ? `🔥 ${this.streak}` : '🔥 0';

                if (data.finished) {
                    this.finishRace();
                    return;
                }

                this.state = 'waiting';
                setTimeout(() => {
                    if (data.next_question) {
                        this.displayQuestion(data.next_question);
                    } else {
                        this.fetchFirstQuestion();
                    }
                }, 800);
            } else {
                // Race ended while answering
                this.finishRace();
            }
        } catch(e) {
            this.state = 'racing';
            setTimeout(() => this.fetchFirstQuestion(), 1000);
        }
    },

    async useFire() {
        if (!this.hasFire || this.state === 'answering') return;
        const fd = new FormData();
        fd.append('room_id', this.roomId);
        try {
            const resp = await fetch('api/powerup.php', { method: 'POST', body: fd });
            const data = await resp.json();
            if (data.success) {
                AudioFX.play('powerup');
                this.hasFire = false;
                this.updateFireBtn();
                this.showToast('🔥 Fire launched!');
            }
        } catch(e) {}
    },

    showFeedback(correct) {
        const el = document.getElementById('feedbackFlash');
        el.textContent = correct ? '✅ CORRECT!' : '❌ OOPS!';
        el.className = correct ? 'feedback-correct' : 'feedback-wrong';
        clearTimeout(this._fb);
        this._fb = setTimeout(() => el.className = 'hidden', 1000);
    },

    updateFireBtn() {
        document.getElementById('btnFire').className = this.hasFire && this.state !== 'answering' ? '' : 'hidden';
    },

    updateRaceTimer(seconds) {
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        document.getElementById('sideTimeLeft').textContent = `⏱️ ${m}:${s.toString().padStart(2, '0')}`;
    },

    updateLeaderboard(players) {
        const sorted = [...players].sort((a, b) => parseFloat(b.position) - parseFloat(a.position));
        document.getElementById('raceLeaderboard').innerHTML = sorted.map((p, i) => {
            const isMe = parseInt(p.student_id) === this.studentId;
            const pct = Math.min(100, Math.round(parseFloat(p.position) / RACE_LENGTH * 100));
            const ico = (p.fire_until && new Date(p.fire_until+'Z') > new Date()) ? '🔥' : '';
            return `<div class="lb-line ${isMe ? 'lb-line-me' : ''}">
                <span class="lb-r">#${i+1}</span>
                <span class="lb-n">${p.username}${ico}</span>
                <div class="lb-bar"><div class="lb-fill" style="width:${pct}%"></div></div>
                <span class="lb-p">${pct}%</span>
            </div>`;
        }).join('');
    },

    onRaceUpdate(data) {
        if (data.room_status === 'racing' && this.state === 'idle') this.startCountdown();

        if (data.players) {
            RaceCanvas.updateCars(data.players);
            this.updateLeaderboard(data.players);
            const me = data.players.find(p => parseInt(p.student_id) === this.studentId);
            if (me) this.myPosition = parseFloat(me.position);

            if (data.fires) {
                data.fires.forEach(f => {
                    if (parseInt(f.to_student_id) === this.studentId) {
                        PowerUpFX.triggerFire(f.from_name || 'Someone');
                    }
                });
            }

            if (data.room_status === 'finished' && !this.isFinished) this.finishRace();
        }

        if (data.time_remaining !== undefined) {
            this.raceTimeLeft = data.time_remaining;
            this.updateRaceTimer(data.time_remaining);
        }
    },

    finishRace() {
        if (this.isFinished) return;
        this.isFinished = true;
        this.state = 'finished';
        if (this.qTimer) { clearInterval(this.qTimer); this.qTimer = null; }
        document.getElementById('questionArea').style.display = 'none';
        document.getElementById('answersArea').style.display = 'none';
        document.getElementById('btnFire').classList.add('hidden');
        document.getElementById('sideStatus').style.display = 'none';
        RaceCanvas.triggerFinish();

        const players = SSEClient.lastRaceData?.players || [];
        const sorted = [...players].sort((a, b) => b.position - a.position);
        const rank = sorted.findIndex(p => parseInt(p.student_id) === this.studentId) + 1;
        document.getElementById('finishTitle').textContent = rank === 1 ? '🏆 YOU WIN! 🏆' : 'Race Complete!';
        document.getElementById('finishSub').textContent = `#${rank} of ${sorted.length} racers`;
        SpeechHelper.speak(rank === 1 ? 'You win!' : 'Great racing!');
    }
};

Game.init();
