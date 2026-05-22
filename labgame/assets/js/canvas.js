const RaceCanvas = {
    canvas: null,
    ctx: null,
    width: 0,
    height: 0,
    scrollOffset: 0,
    cars: {},
    animFrame: null,
    sceneryItems: [],
    particles: [],

    init() {
        this.canvas = document.getElementById('raceCanvas');
        this.ctx = this.canvas.getContext('2d');
        this.resize();
        window.addEventListener('resize', () => this.resize());
        this.generateScenery();
        this.loop();
    },

    resize() {
        const panel = document.getElementById('trackPanel');
        this.width = panel.clientWidth;
        this.height = panel.clientHeight;
        this.canvas.width = this.width;
        this.canvas.height = this.height;
    },

    generateScenery() {
        this.sceneryItems = [];
        const s = ZONE_CONFIG.scenery || {};
        const leftItems = s.left || [];
        const rightItems = s.right || [];
        const bgTrees = s.bgTrees || ['🌳'];

        // Static scenery on both sides
        const roadCenter = this.width * 0.5;
        const roadHalf = this.width * 0.22;

        leftItems.forEach((item, i) => {
            this.sceneryItems.push({
                emoji: item.emoji,
                x: roadCenter - roadHalf - 30 - Math.random() * 20,
                y: item.yOff * this.height,
                size: item.size || 20,
                side: 'left',
                scrollSpeed: 0.2 + Math.random() * 0.3
            });
        });

        rightItems.forEach((item, i) => {
            this.sceneryItems.push({
                emoji: item.emoji,
                x: roadCenter + roadHalf + 10 + Math.random() * 20,
                y: item.yOff * this.height,
                size: item.size || 20,
                side: 'right',
                scrollSpeed: 0.2 + Math.random() * 0.3
            });
        });

        // Background trees that scroll
        for (let i = 0; i < 12; i++) {
            const side = i % 2 === 0 ? 'left' : 'right';
            const roadHalf2 = this.width * 0.22;
            const cx = this.width * 0.5;
            const xOff = side === 'left' ? -(30 + Math.random() * 50) : (30 + Math.random() * 50);
            this.sceneryItems.push({
                emoji: bgTrees[i % bgTrees.length],
                x: cx + (side === 'left' ? -roadHalf2 : roadHalf2) + xOff,
                y: Math.random() * this.height * 2,
                size: 14 + Math.random() * 12,
                side: side,
                scrollSpeed: 0.08 + Math.random() * 0.15,
                bg: true
            });
        }
    },

    updateCars(players) {
        this.cars = {};
        players.forEach(p => {
            this.cars[p.student_id] = p;
        });
    },

    addParticles(x, y, color, count) {
        for (let i = 0; i < count; i++) {
            this.particles.push({
                x, y,
                vx: (Math.random() - 0.5) * 6,
                vy: (Math.random() - 0.5) * 4 - 2,
                life: 1,
                color: color || '#ffff00',
                size: 2 + Math.random() * 4
            });
        }
    },

    loop() {
        this.ctx.clearRect(0, 0, this.width, this.height);
        this.drawBackground();
        this.drawScenery();
        this.drawRoad();
        this.drawCars();
        this.drawParticles();
        this.drawFinishLine();

        this.scrollOffset += 0.3;
        if (this.scrollOffset > this.height) this.scrollOffset = 0;

        this.animFrame = requestAnimationFrame(() => this.loop());
    },

    drawBackground() {
        const colors = ZONE_CONFIG.bgColors || ['#333'];
        const grad = this.ctx.createLinearGradient(0, 0, 0, this.height);
        colors.forEach((c, i) => {
            grad.addColorStop(i / (colors.length - 1 || 1), c);
        });
        this.ctx.fillStyle = grad;
        this.ctx.fillRect(0, 0, this.width, this.height);

        // Stars for space
        if (ZONE_CONFIG.name === 'Space Orbit') {
            for (let i = 0; i < 30; i++) {
                const sx = (i * 137 + this.scrollOffset * 0.3) % this.width;
                const sy = (i * 251 + this.scrollOffset) % this.height;
                const ss = 1 + (i % 3);
                this.ctx.fillStyle = '#fff';
                this.ctx.globalAlpha = 0.2 + (i % 4) * 0.15;
                this.ctx.fillRect(sx, sy, ss, ss);
            }
            this.ctx.globalAlpha = 1;
        }
    },

    drawRoad() {
        const cx = this.width * 0.5;
        const half = this.width * 0.22;

        // Road surface
        this.ctx.fillStyle = ZONE_CONFIG.roadColor || '#555';
        this.ctx.fillRect(cx - half, 0, half * 2, this.height);

        // Grass on sides
        this.ctx.fillStyle = ZONE_CONFIG.name === 'Candy Land' ? '#ffd1dc' : 
                             ZONE_CONFIG.name === 'Space Orbit' ? '#1a1a4e' : '#4a7c3f';
        this.ctx.fillRect(0, 0, cx - half, this.height);
        this.ctx.fillRect(cx + half, 0, this.width - cx - half, this.height);

        // Road edge lines
        this.ctx.fillStyle = ZONE_CONFIG.trackColor || '#fff';
        this.ctx.fillRect(cx - half, 0, 3, this.height);
        this.ctx.fillRect(cx + half - 3, 0, 3, this.height);

        // Dashed center line (scrolling downward)
        this.ctx.strokeStyle = ZONE_CONFIG.roadLines || '#fff';
        this.ctx.lineWidth = 2;
        this.ctx.setLineDash([15, 20]);
        this.ctx.lineDashOffset = -this.scrollOffset * 2;
        this.ctx.beginPath();
        this.ctx.moveTo(cx, 0);
        this.ctx.lineTo(cx, this.height);
        this.ctx.stroke();
        this.ctx.setLineDash([]);
    },

    drawScenery() {
        this.sceneryItems.forEach(item => {
            let y = item.y;
            if (item.bg) {
                y = (item.y + this.scrollOffset * item.scrollSpeed) % (this.height * 2);
                if (y > this.height) y -= this.height * 2;
            }

            if (y > -30 && y < this.height + 30) {
                this.ctx.font = `${item.size}px serif`;
                this.ctx.textAlign = 'center';
                this.ctx.textBaseline = 'middle';
                this.ctx.globalAlpha = item.bg ? 0.25 : 0.5;
                this.ctx.fillText(item.emoji, item.x, y);
                this.ctx.globalAlpha = 1;
            }
        });
    },

    drawFinishLine() {
        // Finish at the top of the track
        const cx = this.width * 0.5;
        const half = this.width * 0.22;
        const fColors = ZONE_CONFIG.finishLine || ['#fff', '#000'];

        for (let i = 0; i < 8; i++) {
            this.ctx.fillStyle = fColors[i % 2];
            this.ctx.fillRect(cx - half + (i * (half * 2 / 8)), 0, half * 2 / 8, 8);
        }
        this.ctx.fillStyle = '#fff';
        this.ctx.font = 'bold 14px Arial';
        this.ctx.textAlign = 'center';
        this.ctx.fillText('🏁 FINISH', cx, 20);
    },

    drawCars() {
        const cx = this.width * 0.5;
        const half = this.width * 0.22;
        const sorted = Object.values(this.cars).sort((a, b) => parseFloat(b.position) - parseFloat(a.position));
        const count = sorted.length;

        const carSize = Math.max(14, Math.min(24, (half * 2) / Math.max(count + 1, 3) * 0.65));

        sorted.forEach((p, idx) => {
            const progress = parseFloat(p.position) / RACE_LENGTH;
            const carY = (this.height - 50) * (1 - progress) + 25;
            // Distribute across road width for up to 8 cars
            const totalLanes = Math.min(count, 8);
            const laneSpacing = (half * 1.6) / Math.max(totalLanes, 2);
            const laneOffset = (idx - (totalLanes - 1) / 2) * laneSpacing;
            const carX = cx + laneOffset;

            this.drawCar(carX, carY, carSize, p, idx);
        });
    },

    drawCar(x, y, size, playerData, idx) {
        const ctx = this.ctx;
        const isMe = parseInt(playerData.student_id) === STUDENT_ID;
        const isBoosted = playerData.boost_until && new Date(playerData.boost_until + 'Z') > new Date();
        const isSlime = playerData.slime_until && new Date(playerData.slime_until + 'Z') > new Date();
        const isFired = playerData.fire_until && new Date(playerData.fire_until + 'Z') > new Date();
        const isBubbled = playerData.status === 'bubbled';

        ctx.save();

        // Boost effect
        if (isBoosted) {
            const hue = (Date.now() / 8) % 360;
            ctx.shadowColor = `hsl(${hue}, 100%, 60%)`;
            ctx.shadowBlur = 20;
            // Flame trail at bottom
            for (let i = 0; i < 5; i++) {
                const fy = y + size * 0.6 + i * 5;
                ctx.fillStyle = `hsla(${hue + i * 40}, 100%, 60%, ${0.4 - i * 0.07})`;
                ctx.beginPath();
                ctx.moveTo(x - 4, fy);
                ctx.lineTo(x, fy + 8 + i * 2);
                ctx.lineTo(x + 4, fy);
                ctx.fill();
            }
        }

        // Fire effect (from power-up)
        if (isFired) {
            const flicker = Math.sin(Date.now() / 80) * 3;
            ctx.shadowColor = '#ff4500';
            ctx.shadowBlur = 25;
            // Fire around car
            for (let i = 0; i < 6; i++) {
                const angle = (Date.now() / 200 + i * 1.05);
                const fx = x + Math.cos(angle) * (size * 0.7 + Math.sin(Date.now() / 100 + i) * 3);
                const fy = y + Math.sin(angle) * (size * 0.7 + Math.cos(Date.now() / 120 + i) * 3);
                ctx.fillStyle = `rgba(255, ${100 + i * 20}, 0, ${0.5 + Math.sin(Date.now() / 150 + i) * 0.2})`;
                ctx.beginPath();
                ctx.arc(fx + flicker * Math.sin(i), fy + flicker * Math.cos(i), 4 + Math.sin(Date.now() / 100 + i) * 2, 0, Math.PI * 2);
                ctx.fill();
            }
        }

        // Slime effect
        if (isSlime) {
            ctx.fillStyle = 'rgba(50, 200, 50, 0.3)';
            ctx.beginPath();
            ctx.ellipse(x, y + size * 0.6, size + 6, size / 3, 0, 0, Math.PI * 2);
            ctx.fill();
            for (let i = 0; i < 3; i++) {
                const dx = x - 6 + i * 6 + Math.sin(Date.now() / 300 + i) * 2;
                ctx.beginPath();
                ctx.arc(dx, y + size * 0.6 + 5 + i * 2, 3, 0, Math.PI * 2);
                ctx.fill();
            }
        }

        // Bubble effect
        if (isBubbled) {
            ctx.globalAlpha = 0.4;
            ctx.strokeStyle = 'rgba(100, 200, 255, 0.6)';
            ctx.lineWidth = 2;
            const r = size + 10 + Math.sin(Date.now() / 200) * 3;
            ctx.beginPath();
            ctx.arc(x, y, r, 0, Math.PI * 2);
            ctx.stroke();
        }

        // Car body
        const carColor = isBoosted
            ? `hsl(${(Date.now() / 8) % 360}, 100%, 55%)`
            : isFired ? '#ff4400'
            : playerData.color || ZONE_CONFIG.carColors[idx % ZONE_CONFIG.carColors.length] || '#ff4444';

        ctx.fillStyle = carColor;
        ctx.shadowColor = isBoosted || isFired ? carColor : 'rgba(0,0,0,0.3)';
        ctx.shadowBlur = isBoosted || isFired ? 15 : 4;

        // Draw car (pointing upward since track is bottom-to-top)
        ctx.beginPath();
        ctx.moveTo(x, y - size * 0.7);
        ctx.lineTo(x + size * 0.7, y + size * 0.1);
        ctx.lineTo(x + size * 0.5, y + size * 0.6);
        ctx.lineTo(x - size * 0.5, y + size * 0.6);
        ctx.lineTo(x - size * 0.7, y + size * 0.1);
        ctx.closePath();
        ctx.fill();

        // Windshield
        ctx.fillStyle = isBoosted ? '#fff' : 'rgba(100,200,255,0.4)';
        ctx.beginPath();
        ctx.moveTo(x, y - size * 0.4);
        ctx.lineTo(x + size * 0.35, y + size * 0.05);
        ctx.lineTo(x - size * 0.35, y + size * 0.05);
        ctx.closePath();
        ctx.fill();

        // Wheels
        ctx.shadowBlur = 0;
        ctx.fillStyle = '#333';
        ctx.beginPath();
        ctx.arc(x - size * 0.45, y + size * 0.5, 3, 0, Math.PI * 2);
        ctx.fill();
        ctx.beginPath();
        ctx.arc(x + size * 0.45, y + size * 0.5, 3, 0, Math.PI * 2);
        ctx.fill();

        // Name label
        ctx.fillStyle = '#fff';
        ctx.font = `bold ${Math.max(8, size * 0.4)}px Arial`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'bottom';
        ctx.strokeStyle = 'rgba(0,0,0,0.7)';
        ctx.lineWidth = 2;
        const labelY = y - size * 0.75;
        ctx.strokeText(playerData.username || '', x, labelY);
        ctx.fillText(playerData.username || '', x, labelY);

        // Highlight ME
        if (isMe) {
            ctx.strokeStyle = '#ffd700';
            ctx.lineWidth = 2;
            ctx.shadowBlur = 0;
            ctx.strokeRect(x - size * 0.75, y - size * 0.75, size * 1.5, size * 1.5);
        }

        ctx.restore();
    },

    drawParticles() {
        this.particles = this.particles.filter(p => p.life > 0);
        this.particles.forEach(p => {
            p.x += p.vx;
            p.y += p.vy;
            p.vy += 0.1;
            p.life -= 0.02;
            this.ctx.globalAlpha = p.life;
            this.ctx.fillStyle = p.color;
            this.ctx.fillRect(p.x, p.y, p.size, p.size);
        });
        this.ctx.globalAlpha = 1;
    },

    triggerFinish() {
        document.getElementById('finishOverlay').classList.remove('hidden');
        AudioFX.play('finish');
    },

    destroy() {
        if (this.animFrame) cancelAnimationFrame(this.animFrame);
    }
};
