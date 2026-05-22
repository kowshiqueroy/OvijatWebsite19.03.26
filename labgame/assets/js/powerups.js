const PowerUpFX = {
    triggerFire(targetUsername) {
        const el = document.getElementById('effectFlash');
        el.innerHTML = `<div class="effect-text">🔥 ${targetUsername} is ON FIRE! 🔥</div>`;
        el.className = 'effect-show';
        AudioFX.play('powerup');
        setTimeout(() => { el.className = 'hidden'; }, 2500);
    }
};
