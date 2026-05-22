<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sobjiwali | Freshness is Rooting</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2D5A27;
            --accent: #F28C28;
            --bg: #FAFAF5;
            --text: #1A3015;
            --white: #ffffff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg);
            color: var(--text);
            overflow: hidden;
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
        }

        /* --- Background Animations --- */
        .bg-elements {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }

        .veg-icon {
            position: absolute;
            opacity: 0.15;
            filter: grayscale(20%);
            animation: float 20s infinite linear;
        }

        @keyframes float {
            0% { transform: translateY(100vh) rotate(0deg); opacity: 0; }
            10% { opacity: 0.15; }
            90% { opacity: 0.15; }
            100% { transform: translateY(-20vh) rotate(360deg); opacity: 0; }
        }

        /* Randomizing positions for floating icons */
        .v1 { left: 10%; animation-duration: 25s; animation-delay: 0s; }
        .v2 { left: 25%; animation-duration: 18s; animation-delay: -5s; }
        .v3 { left: 45%; animation-duration: 30s; animation-delay: -2s; }
        .v4 { left: 65%; animation-duration: 22s; animation-delay: -10s; }
        .v5 { left: 85%; animation-duration: 28s; animation-delay: -7s; }

        /* --- Layout --- */
        header {
            padding: 2rem;
            width: 100%;
            text-align: center;
            animation: fadeInDown 1s ease-out;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: -1px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .logo svg {
            width: 30px;
            height: 30px;
            fill: var(--primary);
        }

        main {
            text-align: center;
            padding: 2rem;
            max-width: 800px;
            z-index: 1;
        }

        .hero-title {
            font-size: clamp(2.5rem, 8vw, 4.5rem);
            font-weight: 800;
            line-height: 1.1;
            color: var(--primary);
            margin-bottom: 1.5rem;
            animation: fadeInUp 1s ease-out 0.2s both;
        }

        .hero-title span {
            color: var(--accent);
        }

        .hero-text {
            font-size: clamp(1rem, 4vw, 1.2rem);
            line-height: 1.6;
            color: var(--text);
            opacity: 0.8;
            margin-bottom: 2.5rem;
            animation: fadeInUp 1s ease-out 0.4s both;
        }

        .badge {
            display: inline-block;
            background: var(--primary);
            color: var(--white);
            padding: 0.5rem 1.2rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 1rem;
            animation: fadeInUp 1s ease-out both;
        }

        footer {
            padding: 2rem;
            width: 100%;
            text-align: center;
            font-size: 0.9rem;
            opacity: 0.7;
            animation: fadeIn 1.5s ease-out 0.6s both;
        }

        .dev-credit {
            margin-top: 0.5rem;
            font-weight: 600;
        }

        .dev-credit a {
            color: var(--primary);
            text-decoration: none;
            border-bottom: 1px solid transparent;
            transition: border 0.3s;
        }

        .dev-credit a:hover {
            border-bottom: 1px solid var(--primary);
        }

        /* --- Animations --- */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* --- SVG Background Elements --- */
        .icon-svg {
            width: 60px;
            height: 60px;
        }
    </style>
</head>
<body>

    <div class="bg-elements">
        <!-- Carrot -->
        <svg class="veg-icon v1 icon-svg" viewBox="0 0 24 24" fill="#F28C28">
            <path d="M12.5,2C12.5,2 13.5,4 14.5,4C15.5,4 16.5,2 16.5,2M14.5,4C14.5,4 15,6 14,7C13,8 11,8 10,7C9,6 9.5,4 9.5,4M18,9.5C18,9.5 16,10.5 15,12.5C14,14.5 15,16.5 15,16.5L12,22L9,16.5C9,16.5 10,14.5 9,12.5C8,10.5 6,9.5 6,9.5L12,7L18,9.5Z" />
        </svg>
        <!-- Tomato -->
        <svg class="veg-icon v2 icon-svg" viewBox="0 0 24 24" fill="#E74C3C">
            <path d="M12,2C11.1,2 10.3,2.2 9.5,2.4C10.5,2.8 11,3.4 11,4C11,5.1 10.1,6 9,6C8.4,6 7.8,5.5 7.4,4.5C7.2,5.3 7,6.1 7,7C7,9.8 9.2,12 12,12C14.8,12 17,9.8 17,7C17,4.2 14.8,2 12,2M12,13C8.1,13 5,16.1 5,20C5,20.6 5.4,21 6,21H18C18.6,21 19,20.6 19,20C19,16.1 15.9,13 12,13Z" />
        </svg>
        <!-- Leafy Green -->
        <svg class="veg-icon v3 icon-svg" viewBox="0 0 24 24" fill="#4CAF50">
            <path d="M17,8C17,11.3 14.3,14 11,14C7.7,14 5,11.3 5,8C5,4.7 7.7,2 11,2C14.3,2 17,4.7 17,8M11,16C6.6,16 3,19.6 3,24H19C19,19.6 15.4,16 11,16Z" />
        </svg>
        <!-- Eggplant -->
        <svg class="veg-icon v4 icon-svg" viewBox="0 0 24 24" fill="#673AB7">
            <path d="M12,2C11.5,2 11,2.5 11,3V4.2C8.7,4.7 7,6.7 7,9C7,11.3 8.7,13.3 11,13.8V22H13V13.8C15.3,13.3 17,11.3 17,9C17,6.7 15.3,4.7 13,4.2V3C13,2.5 12.5,2 12,2Z" />
        </svg>
        <!-- Broccoli -->
        <svg class="veg-icon v5 icon-svg" viewBox="0 0 24 24" fill="#2E7D32">
            <path d="M12,2C9.8,2 8,3.8 8,6C8,6.8 8.2,7.6 8.7,8.3C7.1,9.1 6,10.7 6,12.5C6,14.6 7.4,16.3 9.3,16.8C9.1,17.2 9,17.6 9,18C9,19.7 10.3,21 12,21C13.7,21 15,19.7 15,18C15,17.6 14.9,17.2 14.7,16.8C16.6,16.3 18,14.6 18,12.5C18,10.7 16.9,9.1 15.3,8.3C15.8,7.6 16,6.8 16,6C16,3.8 14.2,2 12,2Z" />
        </svg>
    </div>

    <header>
        <div class="logo">
            <svg viewBox="0 0 24 24"><path d="M12,2L4.5,20.29L5.21,21L12,18L18.79,21L19.5,20.29L12,2Z"/></svg>
            SOBJIWALI
        </div>
    </header>

    <main>
        <div class="badge">COMING SOON</div>
        <h1 class="hero-title">Freshness is <span>Rooting...</span></h1>
        <p class="hero-text">
            We're busy hand-picking the finest, crispest vegetables from the local farms to bring the harvest's best directly to your doorstep. The wait for quality is almost over.
        </p>
    </main>

    <footer>
        <div>&copy; 2026 sobjiwali.com. All rights reserved.</div>
        <div class="dev-credit">Developed by <a href="https://sohojweb.com" target="_blank">sohojweb.com</a></div>
    </footer>

</body>
</html>
