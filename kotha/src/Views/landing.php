<?php $baseUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), "/\\"); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kotha — Speak Freely. Leave No Trace.</title>
<meta name="description" content="PIN-protected, self-destructing corporate messaging with P2P encrypted calls. Zero traces. Zero compromises.">
<link rel="icon" type="image/svg+xml" href="<?= $baseUrl ?>/public/img/icon.svg">
<link rel="shortcut icon" href="<?= $baseUrl ?>/public/img/icon.svg">
<meta name="theme-color" content="#00f2fe">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@200;300;400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* ── Reset & base ───────────────────────────────────────────── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth;overflow-x:hidden}
body{font-family:'Outfit',sans-serif;background:#070d14;color:#e9edef;-webkit-font-smoothing:antialiased;overflow-x:hidden}
a{text-decoration:none;color:inherit}
img{max-width:100%;display:block}

/* ── CSS Variables ──────────────────────────────────────────── */
:root{
    --c1:#00f2fe;
    --c2:#4facfe;
    --c3:#7c3aed;
    --bg:#070d14;
    --bg2:#0d1822;
    --bg3:#101e2c;
    --glass:rgba(255,255,255,.04);
    --glass2:rgba(255,255,255,.08);
    --border:rgba(255,255,255,.07);
    --tx1:#e9edef;
    --tx2:#8696a0;
    --tx3:#4a5e70;
    --r:16px;
    --grad:linear-gradient(135deg,var(--c1),var(--c2));
}

/* ── Utility ────────────────────────────────────────────────── */
.container{width:100%;max-width:1180px;margin:0 auto;padding:0 20px}
@media(min-width:768px){.container{padding:0 40px}}
.grad-text{background:var(--grad);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)}

/* ── Animation keyframes ────────────────────────────────────── */
@keyframes fadeUp{from{opacity:0;transform:translateY(32px)}to{opacity:1;transform:translateY(0)}}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
@keyframes slideRight{from{opacity:0;transform:translateX(-24px)}to{opacity:1;transform:translateX(0)}}
@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-14px)}}
@keyframes floatSlow{0%,100%{transform:translateY(0) rotate(0deg)}50%{transform:translateY(-22px) rotate(6deg)}}
@keyframes pulse{0%,100%{box-shadow:0 0 0 0 rgba(0,242,254,.4)}60%{box-shadow:0 0 0 20px rgba(0,242,254,0)}}
@keyframes pulseRing{0%{transform:scale(1);opacity:.7}100%{transform:scale(2.4);opacity:0}}
@keyframes spin{to{transform:rotate(360deg)}}
@keyframes blink{0%,100%{opacity:1}50%{opacity:0}}
@keyframes shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}
@keyframes scanline{0%,100%{top:0}50%{top:100%}}
@keyframes vanishBar{from{width:100%}to{width:0%}}
@keyframes msgIn{from{opacity:0;transform:scale(.88) translateY(8px)}to{opacity:1;transform:scale(1) translateY(0)}}
@keyframes pinFill{from{width:0}to{width:100%}}
@keyframes glow{0%,100%{text-shadow:0 0 20px rgba(0,242,254,.5)}50%{text-shadow:0 0 40px rgba(0,242,254,.9),0 0 80px rgba(79,172,254,.3)}}
@keyframes borderGlow{0%,100%{border-color:rgba(0,242,254,.2);box-shadow:0 0 20px rgba(0,242,254,.05)}50%{border-color:rgba(0,242,254,.5);box-shadow:0 0 40px rgba(0,242,254,.15)}}
@keyframes waveRipple{0%{transform:scale(1);opacity:.6}100%{transform:scale(3);opacity:0}}
@keyframes particleFloat{0%,100%{transform:translateY(0) translateX(0);opacity:.4}33%{transform:translateY(-30px) translateX(10px);opacity:.7}66%{transform:translateY(-15px) translateX(-8px);opacity:.5}}
@keyframes gradientShift{0%,100%{background-position:0% 50%}50%{background-position:100% 50%}}
@keyframes dot{0%,20%{opacity:0}40%{opacity:1}80%,100%{opacity:0}}
@keyframes typeSlide{from{max-width:0;opacity:0}to{max-width:400px;opacity:1}}
@keyframes countDown{from{stroke-dashoffset:0}to{stroke-dashoffset:126}}
@keyframes msgFadeOut{0%{opacity:1;transform:scale(1)}100%{opacity:0;transform:scale(.9) translateY(-6px)}}
@keyframes shake{0%,100%{transform:translateX(0)}20%,60%{transform:translateX(-4px)}40%,80%{transform:translateX(4px)}}
@keyframes tickPop{0%{transform:scale(0);opacity:0}60%{transform:scale(1.3)}100%{transform:scale(1);opacity:1}}

/* ── Scroll animations ──────────────────────────────────────── */
.lp-anim{opacity:0;transform:translateY(28px);transition:opacity .7s cubic-bezier(.22,.6,.36,1),transform .7s cubic-bezier(.22,.6,.36,1)}
.lp-anim.visible{opacity:1;transform:translateY(0)}
.lp-anim-delay-1{transition-delay:.1s}
.lp-anim-delay-2{transition-delay:.2s}
.lp-anim-delay-3{transition-delay:.3s}
.lp-anim-delay-4{transition-delay:.4s}
.lp-anim-delay-5{transition-delay:.5s}

/* ── Navigation ─────────────────────────────────────────────── */
.lp-nav{
    position:fixed;top:0;left:0;right:0;z-index:1000;
    padding:14px 20px;
    display:flex;align-items:center;justify-content:space-between;
    background:rgba(7,13,20,.8);
    backdrop-filter:blur(20px) saturate(180%);
    -webkit-backdrop-filter:blur(20px) saturate(180%);
    border-bottom:1px solid var(--border);
    transition:background .3s;
}
.lp-nav.scrolled{background:rgba(7,13,20,.95)}
.lp-brand{display:flex;align-items:center;gap:10px;font-weight:800;font-size:.95rem;letter-spacing:1.5px;color:var(--c1)}
.lp-brand img{width:30px;height:30px;filter:drop-shadow(0 0 6px rgba(0,242,254,.5))}
.lp-brand span{background:var(--grad);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.lp-nav-links{display:flex;align-items:center;gap:10px}
.lp-btn{padding:8px 18px;border-radius:10px;font-family:'Outfit',sans-serif;font-weight:600;font-size:.82rem;cursor:pointer;transition:all .2s;border:none;letter-spacing:.3px}
.lp-btn-ghost{background:transparent;border:1px solid var(--border);color:var(--tx2)}
.lp-btn-ghost:hover{border-color:rgba(0,242,254,.3);color:var(--c1)}
.lp-btn-primary{background:var(--grad);color:#000;font-weight:700;box-shadow:0 4px 20px rgba(0,242,254,.25)}
.lp-btn-primary:hover{box-shadow:0 6px 30px rgba(0,242,254,.45);transform:translateY(-1px)}

/* ── Hero ───────────────────────────────────────────────────── */
.lp-hero{
    min-height:100dvh;
    display:flex;align-items:center;
    padding:100px 0 60px;
    position:relative;overflow:hidden;
}
/* Animated gradient mesh background */
.hero-bg{
    position:absolute;inset:0;z-index:0;
    background:
        radial-gradient(ellipse 60% 50% at 20% 30%, rgba(0,242,254,.12) 0%,transparent 70%),
        radial-gradient(ellipse 50% 60% at 80% 70%, rgba(79,172,254,.10) 0%,transparent 70%),
        radial-gradient(ellipse 40% 40% at 60% 20%, rgba(124,58,237,.07) 0%,transparent 70%),
        #070d14;
    background-size:200% 200%;
    animation:gradientShift 18s ease infinite;
}
/* Grid overlay */
.hero-bg::after{
    content:'';
    position:absolute;inset:0;
    background-image:
        linear-gradient(rgba(0,242,254,.03) 1px,transparent 1px),
        linear-gradient(90deg,rgba(0,242,254,.03) 1px,transparent 1px);
    background-size:60px 60px;
    mask-image:radial-gradient(ellipse 80% 80% at center,black 40%,transparent 100%);
}
.lp-hero .container{position:relative;z-index:1;display:grid;grid-template-columns:1fr;gap:48px;align-items:center}
@media(min-width:900px){.lp-hero .container{grid-template-columns:1fr 1fr;gap:80px}}
.hero-content{animation:fadeUp .8s .1s both}
.hero-badge{
    display:inline-flex;align-items:center;gap:8px;
    padding:6px 14px;border-radius:30px;
    border:1px solid rgba(0,242,254,.25);
    background:rgba(0,242,254,.06);
    color:var(--c1);font-size:.75rem;font-weight:600;letter-spacing:.8px;text-transform:uppercase;
    margin-bottom:24px;
}
.hero-badge::before{content:'';width:7px;height:7px;border-radius:50%;background:var(--c1);animation:blink 1.5s infinite}
.hero-h1{font-size:clamp(2.4rem,6vw,4rem);font-weight:900;line-height:1.08;letter-spacing:-1.5px;color:#fff;margin-bottom:20px}
.hero-h1 .accent{display:block;background:var(--grad);background-size:200%;-webkit-background-clip:text;-webkit-text-fill-color:transparent;animation:shimmer 4s linear infinite}
.hero-sub{font-size:clamp(.9rem,2vw,1.05rem);color:var(--tx2);line-height:1.7;max-width:480px;margin-bottom:36px;font-weight:300}
.hero-sub strong{color:var(--tx1);font-weight:600}
.hero-ctas{display:flex;gap:12px;flex-wrap:wrap}
.hero-ctas .lp-btn{padding:13px 28px;font-size:.88rem;border-radius:12px}
.hero-ctas .lp-btn-primary{box-shadow:0 8px 32px rgba(0,242,254,.3)}
.hero-security-note{margin-top:20px;display:flex;align-items:center;gap:8px;font-size:.73rem;color:var(--tx3);font-weight:500}
.hero-security-note i{color:var(--c1);font-size:.8rem}

/* ── Floating particles ─────────────────────────────────────── */
.lp-particles{position:absolute;inset:0;pointer-events:none;overflow:hidden}
.lp-particle{
    position:absolute;border-radius:50%;
    background:radial-gradient(circle,var(--c1),transparent);
    opacity:.25;
    animation:particleFloat var(--dur,8s) var(--delay,0s) ease-in-out infinite;
}

/* ── Phone Mockup ───────────────────────────────────────────── */
.hero-phone-wrap{display:flex;justify-content:center;animation:fadeUp .9s .3s both}
@media(min-width:900px){.hero-phone-wrap{animation:fadeIn .9s .3s both}}
.phone-frame{
    width:240px;height:470px;
    background:#0a1520;
    border-radius:38px;
    border:1.5px solid rgba(0,242,254,.2);
    box-shadow:
        0 0 0 1px rgba(0,242,254,.06),
        0 40px 100px rgba(0,0,0,.8),
        0 0 60px rgba(0,242,254,.06);
    overflow:hidden;
    position:relative;
    animation:float 6s 1s ease-in-out infinite, borderGlow 4s 2s ease-in-out infinite;
    flex-shrink:0;
}
/* Camera notch */
.phone-notch{height:28px;background:#070d14;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.phone-notch-pill{width:70px;height:10px;background:#0a1520;border-radius:5px}
/* Chat header inside phone */
.phone-chat-head{
    padding:8px 14px;
    background:rgba(0,0,0,.3);
    border-bottom:1px solid rgba(255,255,255,.05);
    display:flex;align-items:center;gap:8px;flex-shrink:0;
}
.phone-chat-head-av{width:28px;height:28px;border-radius:50%;background:var(--grad);display:flex;align-items:center;justify-content:center;font-size:.7rem;color:#000;font-weight:700;flex-shrink:0}
.phone-chat-head-info{flex:1;min-width:0}
.phone-chat-head-name{font-size:.72rem;font-weight:700;color:#fff}
.phone-chat-head-status{font-size:.58rem;color:var(--c1)}
.phone-lock-icon{color:var(--c1);font-size:.8rem;opacity:.7}
/* Messages area */
.phone-msgs{
    flex:1;padding:10px 10px 6px;
    display:flex;flex-direction:column;gap:7px;
    overflow:hidden;position:relative;
    min-height:0;
}
/* PIN prompt bar */
.phone-pin-bar{
    padding:8px 12px;
    background:rgba(0,242,254,.05);
    border-top:1px solid rgba(0,242,254,.1);
    display:flex;align-items:center;gap:6px;
    flex-shrink:0;
}
.phone-pin-dots{display:flex;gap:5px}
.phone-pin-dot{width:8px;height:8px;border-radius:50%;border:1.5px solid rgba(0,242,254,.5);background:transparent;transition:background .2s}
.phone-pin-dot.filled{background:var(--c1);border-color:var(--c1)}
.phone-pin-label{font-size:.62rem;color:var(--tx2);flex:1}
.phone-pin-btn{font-size:.62rem;color:var(--c1);font-weight:600;cursor:pointer}
/* Phone inner layout */
.phone-inner{display:flex;flex-direction:column;height:100%}
/* Message bubbles */
.demo-msg{
    max-width:82%;padding:7px 10px;border-radius:12px;
    font-size:.65rem;line-height:1.5;position:relative;
    animation:msgIn .35s ease both;
}
.demo-msg.recv{
    align-self:flex-start;background:#1a2942;color:var(--tx1);
    border-bottom-left-radius:4px;
}
.demo-msg.sent{
    align-self:flex-end;background:linear-gradient(135deg,#005c6b,#1a3a5c);color:var(--tx1);
    border-bottom-right-radius:4px;
}
.demo-msg .camouflage-text{color:rgba(0,242,254,.5);font-family:monospace;font-size:.58rem;line-height:1.6;filter:blur(0);transition:filter .4s,opacity .4s}
.demo-msg .real-text{opacity:0;max-height:0;overflow:hidden;transition:opacity .4s,max-height .4s}
.demo-msg.unlocked .camouflage-text{filter:blur(8px);opacity:0;position:absolute;pointer-events:none}
.demo-msg.unlocked .real-text{opacity:1;max-height:60px}
/* Vanish bar */
.demo-vanish-bar{height:2px;background:rgba(0,242,254,.2);border-radius:1px;margin-top:5px;overflow:hidden;display:none}
.demo-vanish-bar.active{display:block}
.demo-vanish-fill{height:100%;background:var(--grad);border-radius:1px;animation:vanishBar 3.5s linear forwards}
/* Unlock overlay */
.phone-unlock-flash{
    position:absolute;inset:0;z-index:10;
    background:rgba(0,242,254,.08);
    display:flex;align-items:center;justify-content:center;
    opacity:0;pointer-events:none;transition:opacity .3s;
    font-size:.75rem;color:var(--c1);font-weight:700;gap:6px;
}
.phone-unlock-flash.show{opacity:1}
/* No-trace screen */
.phone-no-trace{
    position:absolute;inset:0;z-index:5;
    background:#070d14;
    display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;
    opacity:0;pointer-events:none;transition:opacity .5s;
}
.phone-no-trace.show{opacity:1}
.phone-no-trace i{font-size:1.8rem;color:var(--c1);opacity:.6}
.phone-no-trace p{font-size:.65rem;color:var(--tx3);text-align:center;line-height:1.6;max-width:160px}

/* ── Trust bar ──────────────────────────────────────────────── */
.lp-trust{
    padding:16px 0;
    background:rgba(0,242,254,.03);
    border-top:1px solid rgba(0,242,254,.07);
    border-bottom:1px solid rgba(0,242,254,.07);
    overflow:hidden;
}
.trust-track{
    display:flex;gap:0;
    animation:trustScroll 20s linear infinite;
    width:max-content;
}
@keyframes trustScroll{from{transform:translateX(0)}to{transform:translateX(-50%)}}
.trust-item{
    display:flex;align-items:center;gap:8px;
    padding:0 36px;
    font-size:.78rem;font-weight:600;color:var(--tx2);
    white-space:nowrap;border-right:1px solid var(--border);
}
.trust-item:last-child{border-right:none}
.trust-item i{color:var(--c1);font-size:.9rem}

/* ── Section headers ────────────────────────────────────────── */
.lp-section{padding:80px 0}
@media(min-width:768px){.lp-section{padding:100px 0}}
.lp-section-tag{
    display:inline-flex;align-items:center;gap:6px;
    font-size:.7rem;font-weight:700;letter-spacing:2px;text-transform:uppercase;
    color:var(--c1);margin-bottom:16px;
}
.lp-section-tag::before{content:'';width:20px;height:1.5px;background:var(--c1)}
.lp-section-title{
    font-size:clamp(1.8rem,4vw,2.8rem);
    font-weight:900;letter-spacing:-1px;color:#fff;
    line-height:1.12;margin-bottom:14px;
}
.lp-section-sub{font-size:clamp(.88rem,2vw,.98rem);color:var(--tx2);line-height:1.7;max-width:560px;font-weight:300}

/* ── Features grid ──────────────────────────────────────────── */
.feat-grid{
    display:grid;gap:16px;
    grid-template-columns:1fr;
    margin-top:52px;
}
@media(min-width:540px){.feat-grid{grid-template-columns:1fr 1fr}}
@media(min-width:900px){.feat-grid{grid-template-columns:repeat(3,1fr)}}
.feat-card{
    background:var(--glass);
    border:1px solid var(--border);
    border-radius:var(--r);
    padding:28px 24px;
    position:relative;overflow:hidden;
    transition:border-color .3s,transform .3s,box-shadow .3s;
    cursor:default;
}
.feat-card::before{
    content:'';
    position:absolute;top:0;left:0;right:0;height:2px;
    background:var(--grad);
    transform:scaleX(0);transform-origin:left;
    transition:transform .4s cubic-bezier(.22,.6,.36,1);
}
.feat-card:hover{
    border-color:rgba(0,242,254,.2);
    transform:translateY(-4px);
    box-shadow:0 20px 50px rgba(0,0,0,.4),0 0 30px rgba(0,242,254,.06);
}
.feat-card:hover::before{transform:scaleX(1)}
.feat-icon-wrap{
    width:52px;height:52px;border-radius:14px;
    background:rgba(0,242,254,.08);
    display:flex;align-items:center;justify-content:center;
    margin-bottom:18px;font-size:1.3rem;color:var(--c1);
    transition:background .3s,transform .3s;
    position:relative;overflow:hidden;
}
.feat-card:hover .feat-icon-wrap{background:rgba(0,242,254,.15);transform:scale(1.08)}
.feat-card-title{font-size:1rem;font-weight:700;color:#fff;margin-bottom:8px}
.feat-card-desc{font-size:.82rem;color:var(--tx2);line-height:1.65;font-weight:300}
/* Featured (large) card */
.feat-card.large{grid-column:1/-1}
@media(min-width:540px){.feat-card.large{grid-column:span 2}}
@media(min-width:900px){.feat-card.large:first-child{grid-column:span 1}}

/* ── Vanish Demo section ────────────────────────────────────── */
.vanish-section{
    padding:80px 0;
    background:linear-gradient(180deg,var(--bg) 0%,var(--bg2) 50%,var(--bg) 100%);
    overflow:hidden;
}
.vanish-demo-wrap{
    display:grid;grid-template-columns:1fr;gap:48px;align-items:center;margin-top:48px;
}
@media(min-width:860px){.vanish-demo-wrap{grid-template-columns:1fr 1fr;gap:80px}}
/* Big animated message demo */
.big-msg-demo{position:relative;padding:40px 0}
.big-msg-bubble{
    background:#1a2942;
    border:1px solid rgba(0,242,254,.12);
    border-radius:18px;border-bottom-left-radius:6px;
    padding:20px 24px;
    position:relative;overflow:hidden;
    max-width:380px;
}
.big-msg-bubble.sent{margin-left:auto;background:linear-gradient(135deg,#005c6b,#1a3a5c);border-bottom-right-radius:6px;border-bottom-left-radius:18px}
.big-msg-label{font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--tx3);margin-bottom:6px}
.big-msg-content{font-size:.9rem;color:var(--tx1);line-height:1.6;transition:filter .5s,opacity .5s}
.big-msg-content.locked{filter:blur(4px);color:rgba(0,242,254,.5);font-family:monospace;font-size:.78rem}
.big-msg-timer{margin-top:12px;height:3px;background:rgba(0,242,254,.15);border-radius:2px;overflow:hidden;display:none}
.big-msg-timer-fill{height:100%;background:var(--grad);width:100%}
.big-msg-timer.running{display:block}
.big-msg-timer.running .big-msg-timer-fill{animation:vanishBar 4s linear forwards}
.vanish-floater{
    position:absolute;
    width:48px;height:48px;border-radius:50%;
    background:rgba(0,242,254,.05);
    border:1px solid rgba(0,242,254,.1);
    display:flex;align-items:center;justify-content:center;
    color:var(--c1);font-size:1rem;
    animation:float 4s ease-in-out infinite;
}
/* Vanish features list */
.vanish-points{display:flex;flex-direction:column;gap:20px}
.vanish-point{display:flex;align-items:flex-start;gap:14px}
.vanish-point-icon{
    width:40px;height:40px;border-radius:12px;
    background:var(--glass);border:1px solid var(--border);
    display:flex;align-items:center;justify-content:center;
    color:var(--c1);font-size:.95rem;flex-shrink:0;
    transition:all .3s;
}
.vanish-point:hover .vanish-point-icon{background:rgba(0,242,254,.1);transform:scale(1.05)}
.vanish-point-title{font-weight:700;color:#fff;font-size:.9rem;margin-bottom:4px}
.vanish-point-desc{font-size:.8rem;color:var(--tx2);line-height:1.6;font-weight:300}

/* ── Calls section ──────────────────────────────────────────── */
.calls-section{background:var(--bg2);padding:80px 0}
@media(min-width:768px){.calls-section{padding:100px 0}}
.calls-wrap{display:grid;grid-template-columns:1fr;gap:48px;align-items:center;margin-top:48px}
@media(min-width:860px){.calls-wrap{grid-template-columns:1fr 1fr}}
/* Animated call visual */
.call-visual{
    position:relative;display:flex;align-items:center;justify-content:center;
    height:280px;
}
.call-ring{
    position:absolute;border-radius:50%;border:1px solid rgba(0,242,254,.15);
    animation:waveRipple var(--dur,3s) var(--delay,0s) ease-out infinite;
}
.call-center{
    width:90px;height:90px;border-radius:50%;
    background:var(--grad);
    display:flex;align-items:center;justify-content:center;
    font-size:2rem;color:#000;
    box-shadow:0 0 0 0 rgba(0,242,254,.4);
    animation:pulse 2.5s infinite;
    position:relative;z-index:1;
}
.call-user{
    position:absolute;width:54px;height:54px;border-radius:50%;
    background:var(--bg3);border:2px solid var(--border);
    display:flex;align-items:center;justify-content:center;
    font-size:1.1rem;color:var(--tx2);z-index:1;
}
.call-user.u1{top:20px;left:50%;transform:translateX(-80px)}
.call-user.u2{bottom:20px;right:50%;transform:translateX(80px)}
.call-connecting{
    position:absolute;bottom:30px;left:50%;transform:translateX(-50%);
    font-size:.65rem;color:var(--c1);font-weight:600;letter-spacing:1px;text-transform:uppercase;
    display:flex;align-items:center;gap:5px;
}
.call-connecting-dots span{animation:dot 1.5s infinite}
.call-connecting-dots span:nth-child(2){animation-delay:.3s}
.call-connecting-dots span:nth-child(3){animation-delay:.6s}
/* Call features list */
.calls-points{display:flex;flex-direction:column;gap:18px}
.calls-point{display:flex;align-items:flex-start;gap:12px}
.calls-point-check{
    width:24px;height:24px;border-radius:50%;
    background:rgba(0,242,254,.1);border:1px solid rgba(0,242,254,.2);
    display:flex;align-items:center;justify-content:center;
    flex-shrink:0;color:var(--c1);font-size:.6rem;margin-top:2px;
}
.calls-point-text{font-size:.85rem;color:var(--tx2);line-height:1.5;font-weight:300}
.calls-point-text strong{color:var(--tx1);font-weight:600}

/* ── Final CTA ──────────────────────────────────────────────── */
.lp-cta{
    padding:80px 0 100px;
    text-align:center;position:relative;overflow:hidden;
}
.lp-cta::before{
    content:'';position:absolute;inset:0;z-index:0;
    background:radial-gradient(ellipse 70% 60% at 50% 50%, rgba(0,242,254,.07) 0%,transparent 70%);
}
.lp-cta .container{position:relative;z-index:1}
.cta-icon{
    width:80px;height:80px;border-radius:50%;
    background:rgba(0,242,254,.08);border:1px solid rgba(0,242,254,.2);
    display:flex;align-items:center;justify-content:center;
    margin:0 auto 28px;font-size:2rem;color:var(--c1);
    animation:float 4s ease-in-out infinite,pulse 3s 1s infinite;
}
.lp-cta h2{
    font-size:clamp(1.8rem,4vw,2.6rem);font-weight:900;
    letter-spacing:-1px;color:#fff;margin-bottom:14px;
}
.lp-cta p{font-size:.95rem;color:var(--tx2);max-width:440px;margin:0 auto 36px;line-height:1.7;font-weight:300}
.cta-btns{display:flex;gap:14px;justify-content:center;flex-wrap:wrap}
.cta-btns .lp-btn{padding:14px 32px;font-size:.9rem;border-radius:14px}
.cta-btns .lp-btn-primary{box-shadow:0 10px 40px rgba(0,242,254,.35)}
.cta-btns .lp-btn-ghost{border-color:rgba(255,255,255,.15);color:var(--tx1);padding:14px 28px}
.cta-btns .lp-btn-ghost:hover{border-color:rgba(0,242,254,.3);color:var(--c1)}

/* ── Footer ─────────────────────────────────────────────────── */
.lp-footer{
    padding:24px 20px;
    border-top:1px solid var(--border);
    display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;
}
.lp-footer-brand{display:flex;align-items:center;gap:8px;font-size:.8rem;font-weight:700;color:var(--c1)}
.lp-footer-brand img{width:20px;height:20px}
.lp-footer-copy{font-size:.72rem;color:var(--tx3)}
.lp-footer-by{font-size:.72rem;color:var(--tx3)}

/* ── Scrollbar (subtle) ─────────────────────────────────────── */
::-webkit-scrollbar{width:5px}
::-webkit-scrollbar-track{background:var(--bg)}
::-webkit-scrollbar-thumb{background:rgba(0,242,254,.2);border-radius:3px}
::-webkit-scrollbar-thumb:hover{background:rgba(0,242,254,.4)}
</style>
</head>
<body>

<!-- ─── Navigation ─────────────────────────────────────────── -->
<nav class="lp-nav" id="lpNav">
<div class="container" style="display:flex;align-items:center;justify-content:space-between;width:100%;padding:0">
    <a href="<?= $baseUrl ?>/" class="lp-brand" aria-label="Kotha Home">
        <img src="<?= $baseUrl ?>/public/img/icon.svg" alt="Kotha icon">
        <span>KOTHA</span>
    </a>
    <div class="lp-nav-links">
        <a href="<?= $baseUrl ?>/login" class="lp-btn lp-btn-ghost">Sign In</a>
        <a href="<?= $baseUrl ?>/registration" class="lp-btn lp-btn-primary">Get Started</a>
    </div>
</div>
</nav>

<!-- ─── Hero ───────────────────────────────────────────────── -->
<section class="lp-hero">
    <!-- Animated gradient mesh -->
    <div class="hero-bg"></div>
    <!-- Floating particles -->
    <div class="lp-particles" aria-hidden="true">
        <?php
        $particles = [
            ['4px','12%','18%','9s','0s'],['6px','78%','22%','12s','2s'],
            ['3px','25%','65%','8s','1s'],['5px','60%','75%','11s','3s'],
            ['4px','88%','48%','10s','0.5s'],['3px','42%','30%','7s','2.5s'],
            ['6px','8%','55%','13s','1.5s'],['4px','70%','12%','9s','4s'],
            ['3px','52%','88%','8s','0s'],['5px','33%','42%','11s','3.5s'],
            ['4px','90%','70%','10s','2s'],['3px','15%','85%','9s','1s'],
        ];
        foreach ($particles as $p):
        ?>
        <div class="lp-particle" style="width:<?= $p[0] ?>;height:<?= $p[0] ?>;left:<?= $p[1] ?>;top:<?= $p[2] ?>;--dur:<?= $p[3] ?>;--delay:<?= $p[4] ?>"></div>
        <?php endforeach; ?>
    </div>

    <div class="container">
        <!-- Left: Text content -->
        <div class="hero-content">
            <div class="hero-badge" aria-label="Security badge">
                Corporate-Grade Security
            </div>

            <h1 class="hero-h1">
                Speak Freely.
                <span class="accent">Leave No Trace.</span>
            </h1>

            <p class="hero-sub">
                PIN-protected messages that <strong>vanish after reading</strong>,
                end-to-end encrypted voice & video calls, and
                <strong>zero permanent records</strong> — built for teams that demand absolute privacy.
            </p>

            <div class="hero-ctas">
                <a href="<?= $baseUrl ?>/login" class="lp-btn lp-btn-primary">
                    <i class="fa-solid fa-right-to-bracket" style="margin-right:6px"></i>Sign In
                </a>
                <a href="<?= $baseUrl ?>/registration" class="lp-btn lp-btn-ghost" style="padding:13px 24px;font-size:.88rem">
                    Request Access
                </a>
            </div>

            <div class="hero-security-note">
                <i class="fa-solid fa-shield-halved"></i>
                <span>No permanent logs &nbsp;·&nbsp; No third-party servers &nbsp;·&nbsp; No data retention</span>
            </div>
        </div>

        <!-- Right: Animated phone demo -->
        <div class="hero-phone-wrap">
            <div class="phone-frame" id="phoneMockup">
                <div class="phone-inner">
                    <!-- Camera notch -->
                    <div class="phone-notch"><div class="phone-notch-pill"></div></div>
                    <!-- Chat header -->
                    <div class="phone-chat-head">
                        <div class="phone-chat-head-av">A</div>
                        <div class="phone-chat-head-info">
                            <div class="phone-chat-head-name">Alex M.</div>
                            <div class="phone-chat-head-status" id="phoneStatus">🔒 Locked</div>
                        </div>
                        <i class="fa-solid fa-shield-halved phone-lock-icon"></i>
                    </div>
                    <!-- Messages -->
                    <div class="phone-msgs" id="phoneMsgs">
                        <!-- Messages injected by JS -->
                    </div>
                    <!-- Unlock flash overlay -->
                    <div class="phone-unlock-flash" id="phoneFlash">
                        <i class="fa-solid fa-unlock-keyhole"></i>
                        <span>PIN VERIFIED</span>
                    </div>
                    <!-- No trace overlay -->
                    <div class="phone-no-trace" id="phoneNoTrace">
                        <i class="fa-solid fa-lock"></i>
                        <p>Conversation cleared.<br>No trace remains.</p>
                    </div>
                    <!-- PIN bar -->
                    <div class="phone-pin-bar">
                        <div class="phone-pin-dots" id="phonePinDots">
                            <div class="phone-pin-dot" id="pd1"></div>
                            <div class="phone-pin-dot" id="pd2"></div>
                            <div class="phone-pin-dot" id="pd3"></div>
                            <div class="phone-pin-dot" id="pd4"></div>
                        </div>
                        <span class="phone-pin-label" id="phonePinLabel">Enter PIN to unlock</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ─── Trust Bar ───────────────────────────────────────────── -->
<div class="lp-trust" aria-label="Trust features">
    <div class="trust-track" id="trustTrack">
        <?php
        $items = [
            ['fa-lock',           'PIN-Protected'],
            ['fa-ghost',          'Self-Destructing'],
            ['fa-satellite-dish', 'P2P Encrypted Calls'],
            ['fa-eye-slash',      'Camouflage Mode'],
            ['fa-database',       'Zero Permanent Logs'],
            ['fa-users-gear',     'Secure Groups'],
            ['fa-bolt-lightning', 'Real-Time Messaging'],
            ['fa-shield-halved',  'Corporate-Grade Security'],
            // duplicate for seamless loop
            ['fa-lock',           'PIN-Protected'],
            ['fa-ghost',          'Self-Destructing'],
            ['fa-satellite-dish', 'P2P Encrypted Calls'],
            ['fa-eye-slash',      'Camouflage Mode'],
            ['fa-database',       'Zero Permanent Logs'],
            ['fa-users-gear',     'Secure Groups'],
            ['fa-bolt-lightning', 'Real-Time Messaging'],
            ['fa-shield-halved',  'Corporate-Grade Security'],
        ];
        foreach ($items as $it):
        ?>
        <div class="trust-item">
            <i class="fa-solid <?= $it[0] ?>"></i>
            <?= $it[1] ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ─── Features ───────────────────────────────────────────── -->
<section class="lp-section" id="features">
<div class="container">
    <div class="lp-anim">
        <div class="lp-section-tag"><i class="fa-solid fa-sparkles" style="font-size:.65rem"></i> Core Features</div>
        <h2 class="lp-section-title">Built for Conversations<br>That Must Stay Private</h2>
        <p class="lp-section-sub">Every feature is engineered around one principle: what you say stays between you and the other person. Period.</p>
    </div>

    <div class="feat-grid">
        <!-- Camouflage Mode -->
        <div class="feat-card lp-anim lp-anim-delay-1">
            <div class="feat-icon-wrap">
                <i class="fa-solid fa-eye-slash"></i>
            </div>
            <div class="feat-card-title">Camouflage Mode</div>
            <div class="feat-card-desc">All messages appear as random code to anyone who glances your screen. Only you — with your PIN — can reveal the real content.</div>
        </div>

        <!-- Vanishing Messages -->
        <div class="feat-card lp-anim lp-anim-delay-2">
            <div class="feat-icon-wrap" style="background:rgba(124,58,237,.1);color:var(--c3);">
                <i class="fa-solid fa-hourglass-half"></i>
            </div>
            <div class="feat-card-title">Vanishing Messages</div>
            <div class="feat-card-desc">After you read a message, a countdown begins. Seconds later — it's gone forever. No history. No screenshots. No evidence.</div>
        </div>

        <!-- PIN Shield -->
        <div class="feat-card lp-anim lp-anim-delay-3">
            <div class="feat-icon-wrap" style="background:rgba(34,197,94,.08);color:#22c55e;">
                <i class="fa-solid fa-lock"></i>
            </div>
            <div class="feat-card-title">PIN Shield</div>
            <div class="feat-card-desc">A 4-digit corporate PIN locks every conversation. Even if your device is unlocked, your chats remain completely hidden.</div>
        </div>

        <!-- P2P Calls -->
        <div class="feat-card lp-anim lp-anim-delay-1">
            <div class="feat-icon-wrap" style="background:rgba(79,172,254,.1);color:var(--c2);">
                <i class="fa-solid fa-phone-flip"></i>
            </div>
            <div class="feat-card-title">P2P Encrypted Calls</div>
            <div class="feat-card-desc">Voice and video calls travel peer-to-peer — no server relay, no middleman, no call logs stored anywhere. Pure direct connection.</div>
        </div>

        <!-- Group Vaults -->
        <div class="feat-card lp-anim lp-anim-delay-2">
            <div class="feat-icon-wrap" style="background:rgba(245,158,11,.08);color:#f59e0b;">
                <i class="fa-solid fa-users-gear"></i>
            </div>
            <div class="feat-card-title">Secure Group Vaults</div>
            <div class="feat-card-desc">Create PIN-locked group channels for your team. Control membership, assign local nicknames, and keep every discussion confidential.</div>
        </div>

        <!-- Zero Archive -->
        <div class="feat-card lp-anim lp-anim-delay-3">
            <div class="feat-icon-wrap" style="background:rgba(239,68,68,.08);color:#ef4444;">
                <i class="fa-solid fa-eraser"></i>
            </div>
            <div class="feat-card-title">Zero Archive</div>
            <div class="feat-card-desc">No message history is preserved on any server after vanish. Your conversation literally ceases to exist once it's been read.</div>
        </div>
    </div>
</div>
</section>

<!-- ─── Vanish Demo ─────────────────────────────────────────── -->
<section class="vanish-section">
<div class="container">
    <div class="lp-anim">
        <div class="lp-section-tag"><i class="fa-solid fa-ghost" style="font-size:.65rem"></i> The Vanish System</div>
        <h2 class="lp-section-title">Read It. Then It's Gone.</h2>
        <p class="lp-section-sub">Every message has a built-in countdown. The moment you read it, the clock starts — and when it hits zero, the message is erased from every server permanently.</p>
    </div>

    <div class="vanish-demo-wrap">
        <!-- Live animated demo -->
        <div class="lp-anim">
            <div class="big-msg-demo">
                <!-- Floating icons -->
                <div class="vanish-floater" style="top:-10px;left:-10px;--dur:5s;animation-duration:5s;"><i class="fa-solid fa-lock"></i></div>
                <div class="vanish-floater" style="bottom:0;right:-10px;width:38px;height:38px;font-size:.8rem;animation:float 6s 1.5s ease-in-out infinite;"><i class="fa-solid fa-shield-halved"></i></div>

                <div style="margin-bottom:12px;">
                    <div id="bigMsgRecv" class="big-msg-bubble">
                        <div class="big-msg-label">Alex · Confidential</div>
                        <div class="big-msg-content locked" id="bigMsgRecvTxt">SELECT c.id FROM contracts c WHERE c.status = 'signed'</div>
                        <div class="big-msg-timer" id="bigMsgRecvTimer"><div class="big-msg-timer-fill"></div></div>
                    </div>
                </div>
                <div>
                    <div id="bigMsgSent" class="big-msg-bubble sent">
                        <div class="big-msg-label">You · Encrypted</div>
                        <div class="big-msg-content locked" id="bigMsgSentTxt">const hash = crypto.createHash('sha256');</div>
                        <div class="big-msg-timer" id="bigMsgSentTimer"><div class="big-msg-timer-fill"></div></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Points -->
        <div class="vanish-points lp-anim lp-anim-delay-2">
            <div class="vanish-point">
                <div class="vanish-point-icon"><i class="fa-solid fa-eye-slash"></i></div>
                <div>
                    <div class="vanish-point-title">Hidden by default</div>
                    <div class="vanish-point-desc">Messages display as random code text until you enter your PIN. Even if someone looks over your shoulder, they see nothing meaningful.</div>
                </div>
            </div>
            <div class="vanish-point">
                <div class="vanish-point-icon"><i class="fa-solid fa-timer"></i></div>
                <div>
                    <div class="vanish-point-title">Automatic countdown</div>
                    <div class="vanish-point-desc">The moment you unlock and read a message, a visual timer begins. When it reaches zero, the message is deleted from the server — not archived, deleted.</div>
                </div>
            </div>
            <div class="vanish-point">
                <div class="vanish-point-icon"><i class="fa-solid fa-trash-can"></i></div>
                <div>
                    <div class="vanish-point-title">Hard delete — no recovery</div>
                    <div class="vanish-point-desc">This is not "move to trash." The message record is wiped from the database entirely. No backup. No recovery. No subpoena response.</div>
                </div>
            </div>
            <div class="vanish-point">
                <div class="vanish-point-icon"><i class="fa-solid fa-user-secret"></i></div>
                <div>
                    <div class="vanish-point-title">Per-participant vanish</div>
                    <div class="vanish-point-desc">Each person in the conversation must read before deletion completes, ensuring both sides agree the conversation happened — then it's gone.</div>
                </div>
            </div>
        </div>
    </div>
</div>
</section>

<!-- ─── Calls Section ───────────────────────────────────────── -->
<section class="calls-section">
<div class="container">
    <div class="calls-wrap">
        <!-- Visual -->
        <div class="lp-anim">
            <div class="call-visual">
                <!-- Ripple rings -->
                <div class="call-ring" style="width:240px;height:240px;--dur:3s;--delay:0s"></div>
                <div class="call-ring" style="width:180px;height:180px;--dur:3s;--delay:1s"></div>
                <div class="call-ring" style="width:130px;height:130px;--dur:3s;--delay:2s"></div>
                <!-- Center (mic/call icon) -->
                <div class="call-center"><i class="fa-solid fa-phone"></i></div>
                <!-- Users -->
                <div class="call-user u1"><i class="fa-solid fa-user"></i></div>
                <div class="call-user u2"><i class="fa-solid fa-user"></i></div>
                <!-- Status -->
                <div class="call-connecting">
                    P2P Connected
                    <span class="call-connecting-dots"><span>.</span><span>.</span><span>.</span></span>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="lp-anim lp-anim-delay-2">
            <div class="lp-section-tag"><i class="fa-solid fa-satellite-dish" style="font-size:.65rem"></i> Direct Calls</div>
            <h2 class="lp-section-title" style="font-size:clamp(1.6rem,3vw,2.4rem);margin-bottom:18px;">Calls That Go<br>Nowhere Else</h2>
            <p class="lp-section-sub" style="margin-bottom:28px;">Voice and video calls connect you directly — peer to peer — with no server in the middle recording or routing your conversation.</p>
            <div class="calls-points">
                <div class="calls-point">
                    <div class="calls-point-check"><i class="fa-solid fa-check"></i></div>
                    <div class="calls-point-text"><strong>WebRTC peer-to-peer</strong> — your voice travels directly to the other person, not through our servers</div>
                </div>
                <div class="calls-point">
                    <div class="calls-point-check"><i class="fa-solid fa-check"></i></div>
                    <div class="calls-point-text"><strong>No call logs</strong> — call duration, timestamp, and participants are never stored permanently</div>
                </div>
                <div class="calls-point">
                    <div class="calls-point-check"><i class="fa-solid fa-check"></i></div>
                    <div class="calls-point-text"><strong>PIN gated</strong> — you must verify your PIN before a call connects, preventing accidental answer</div>
                </div>
                <div class="calls-point">
                    <div class="calls-point-check"><i class="fa-solid fa-check"></i></div>
                    <div class="calls-point-text"><strong>Video + audio</strong> — full HD video, camera switch, screen share, mute — all controls in your hands</div>
                </div>
                <div class="calls-point">
                    <div class="calls-point-check"><i class="fa-solid fa-check"></i></div>
                    <div class="calls-point-text"><strong>Offline protection</strong> — if you're away, callers get a missed-call note that also vanishes after reading</div>
                </div>
            </div>
        </div>
    </div>
</div>
</section>

<!-- ─── Final CTA ───────────────────────────────────────────── -->
<section class="lp-cta">
<div class="container">
    <div class="cta-icon"><i class="fa-solid fa-shield-halved"></i></div>
    <h2 class="lp-anim">Ready to Communicate<br>Without Compromise?</h2>
    <p class="lp-anim lp-anim-delay-1">Join the professionals who refuse to leave a digital trail. Your conversations belong to you and no one else.</p>
    <div class="cta-btns lp-anim lp-anim-delay-2">
        <a href="<?= $baseUrl ?>/login" class="lp-btn lp-btn-primary">
            <i class="fa-solid fa-right-to-bracket" style="margin-right:7px"></i>Sign In
        </a>
        <a href="<?= $baseUrl ?>/registration" class="lp-btn lp-btn-ghost">
            <i class="fa-solid fa-user-plus" style="margin-right:7px"></i>Request Access
        </a>
    </div>
</div>
</section>

<!-- ─── Footer ──────────────────────────────────────────────── -->
<footer class="lp-footer">
    <div class="lp-footer-brand">
        <img src="<?= $baseUrl ?>/public/img/icon.svg" alt="Kotha">
        KOTHA
    </div>
    <div class="lp-footer-copy">&copy; <?= date('Y') ?> Kotha Secure Messenger. All conversations are ephemeral.</div>
    <div class="lp-footer-by">By <strong>sohojweb.com</strong></div>
</footer>

<!-- ─── JavaScript ─────────────────────────────────────────── -->
<script>
/* ── Nav scroll effect ───────────────────────────────────── */
const nav = document.getElementById('lpNav');
window.addEventListener('scroll', () => {
    nav.classList.toggle('scrolled', window.scrollY > 40);
}, { passive: true });

/* ── Scroll-triggered animations ─────────────────────────── */
const observer = new IntersectionObserver((entries) => {
    entries.forEach(e => {
        if (e.isIntersecting) { e.target.classList.add('visible'); observer.unobserve(e.target); }
    });
}, { threshold: 0.12 });
document.querySelectorAll('.lp-anim').forEach(el => observer.observe(el));

/* ── Phone Chat Demo ──────────────────────────────────────── */
const CAMOUFLAGE = [
    'const db = new sqlite3.Database()',
    'SELECT * FROM chats WHERE id = ?',
    'npm run build --workspace=client'
];
const REAL = [
    'Deal confirmed — Q3 targets met. 🎯',
    'Keep this channel only. No emails.',
    'Understood. Zero trail from now on.'
];
const FROM = ['recv', 'sent', 'recv'];

const msgsEl    = document.getElementById('phoneMsgs');
const flashEl   = document.getElementById('phoneFlash');
const noTraceEl = document.getElementById('phoneNoTrace');
const statusEl  = document.getElementById('phoneStatus');
const pinLabel  = document.getElementById('phonePinLabel');
const pinDots   = [1,2,3,4].map(i => document.getElementById('pd' + i));

const sleep = ms => new Promise(r => setTimeout(r, ms));

async function runDemo() {
    while (true) {
        /* Phase 1: clear → show locked messages */
        msgsEl.innerHTML = '';
        noTraceEl.classList.remove('show');
        flashEl.classList.remove('show');
        statusEl.textContent = '🔒 Locked';
        statusEl.style.color = '';
        pinLabel.textContent = 'Enter PIN to unlock';
        pinDots.forEach(d => d.classList.remove('filled'));

        await sleep(600);

        for (let i = 0; i < 3; i++) {
            const div = document.createElement('div');
            div.className = `demo-msg ${FROM[i]}`;
            div.id = `dm${i}`;
            div.innerHTML = `
                <div class="camouflage-text">${CAMOUFLAGE[i]}</div>
                <div class="real-text">${REAL[i]}</div>
                <div class="demo-vanish-bar" id="dvb${i}"><div class="demo-vanish-fill" id="dvf${i}"></div></div>`;
            msgsEl.appendChild(div);
            await sleep(500);
        }

        await sleep(900);

        /* Phase 2: PIN entry animation */
        for (let i = 0; i < 4; i++) {
            await sleep(260);
            pinDots[i].classList.add('filled');
        }
        await sleep(320);

        /* Phase 3: Unlock flash */
        flashEl.classList.add('show');
        statusEl.textContent = '🔓 Unlocked';
        statusEl.style.color = '#22c55e';
        pinLabel.textContent = '✓ Access granted';
        await sleep(700);
        flashEl.classList.remove('show');

        /* Phase 4: Reveal real content */
        for (let i = 0; i < 3; i++) {
            document.getElementById(`dm${i}`)?.classList.add('unlocked');
            await sleep(250);
        }

        await sleep(1400);

        /* Phase 5: Vanish messages one by one */
        for (let i = 0; i < 3; i++) {
            const bar = document.getElementById(`dvb${i}`);
            if (bar) bar.classList.add('active');
            await sleep(3600); // countdown duration
            const el = document.getElementById(`dm${i}`);
            if (el) {
                el.style.transition = 'opacity .4s, transform .4s';
                el.style.opacity = '0';
                el.style.transform = 'scale(.92) translateY(-5px)';
                await sleep(420);
                el.remove();
            }
        }

        await sleep(400);

        /* Phase 6: No-trace overlay */
        noTraceEl.classList.add('show');
        statusEl.textContent = '🔒 Cleared';
        pinLabel.textContent = 'Enter PIN to unlock';
        pinDots.forEach(d => d.classList.remove('filled'));
        await sleep(3000);
        noTraceEl.classList.remove('show');
        await sleep(800);
    }
}

/* Start demo after a brief delay */
setTimeout(runDemo, 1200);

/* ── Vanish section big-message demo ─────────────────────── */
const bigRecvEl   = document.getElementById('bigMsgRecvTxt');
const bigSentEl   = document.getElementById('bigMsgSentTxt');
const bigRecvTimer = document.getElementById('bigMsgRecvTimer');
const bigSentTimer = document.getElementById('bigMsgSentTimer');

const BIG_CAMOUF = [
    'SELECT c.id FROM contracts c WHERE status=\'signed\'',
    'const hash = crypto.createHash(\'sha256\');'
];
const BIG_REAL = [
    'Acquisition finalised. Shares secured. 🏦',
    'Confirmed. This thread ends here. 🔐'
];

async function runBigDemo() {
    while (true) {
        // Reset
        [bigRecvEl, bigSentEl].forEach((el, i) => {
            el.textContent = BIG_CAMOUF[i];
            el.className   = 'big-msg-content locked';
        });
        [bigRecvTimer, bigSentTimer].forEach(t => {
            t.className = 'big-msg-timer';
            t.querySelectorAll('.big-msg-timer-fill').forEach(f => {
                f.style.animation = 'none'; f.offsetHeight; // reset animation
                f.style.animation = '';
            });
        });

        await sleep(2500);

        // Unlock recv
        bigRecvEl.className = 'big-msg-content';
        bigRecvEl.textContent = BIG_REAL[0];
        await sleep(600);

        // Unlock sent
        bigSentEl.className = 'big-msg-content';
        bigSentEl.textContent = BIG_REAL[1];
        await sleep(1000);

        // Start countdown on recv
        bigRecvTimer.className = 'big-msg-timer running';
        await sleep(800);

        // Start countdown on sent
        bigSentTimer.className = 'big-msg-timer running';
        await sleep(4200);

        // Fade out
        [bigRecvEl, bigSentEl].forEach(el => {
            el.style.transition = 'opacity .5s';
            el.style.opacity = '0';
        });
        await sleep(600);
        [bigRecvEl, bigSentEl].forEach(el => { el.style.opacity = '1'; });

        await sleep(1200);
    }
}

setTimeout(runBigDemo, 2000);
</script>
</body>
</html>
