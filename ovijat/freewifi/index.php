<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Ovijat Group Free WiFi</title>
  <link rel="icon" href="https://www.ovijatfood.com/images/logo.png" type="image/png" />

  <style>
    :root {
      --primary: #00c9a7;
      --accent: #ff6ec4;
      --bg-gradient: linear-gradient(135deg, #ffdde1, #ee9ca7);
      --glass: rgba(255, 255, 255, 0.6);
    }

    * {
      box-sizing: border-box;
      font-family: 'Poppins', sans-serif;
    }

    body {
      margin: 0;
      padding: 0;
      background: var(--bg-gradient);
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      overflow-x: hidden;
    }

    .container {
      width: 100%;
      max-width: 420px;
      padding: 20px;
    }

    .logo {
      display: none;
      margin: 0 auto 10px;
      width: 80px;
      border-radius: 50%;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }

    h1 {
      text-align: center;
      font-size: 24px;
      color: var(--primary);
      margin-bottom: 5px;
    }

    p {
      text-align: center;
      font-size: 14px;
      color: #333;
      margin-bottom: 10px;
    }

    .banner {
      width: 100%;
      border-radius: 15px;
      overflow: hidden;
      margin: 20px 0;
      box-shadow: 0 0 15px rgba(0,0,0,0.2);
      transition: transform 0.3s ease;
      display: none;
    }

    .banner:hover {
      transform: scale(1.02);
    }

    form {
      background: var(--glass);
      backdrop-filter: blur(12px);
      padding: 20px;
      border-radius: 20px;
      box-shadow: 0 8px 20px rgba(0,0,0,0.1);
      animation: fadeIn 0.6s ease-in-out;
    }

    input {
      width: 100%;
      padding: 14px;
      margin-bottom: 15px;
      border: none;
      border-radius: 10px;
      background: #f0f0f0;
      font-size: 15px;
      transition: background 0.3s;
    }

    input:focus {
      background: #e0ffe0;
      outline: none;
    }

    button {
      width: 100%;
      padding: 14px;
      background: var(--primary);
      color: white;
      border: none;
      border-radius: 10px;
      font-size: 16px;
      cursor: pointer;
      transition: background 0.3s;
    }

    button:hover {
      background: #00b89c;
    }

    .facebook-button {
      background-color: #4267B2;
      margin-top: 10px;
    }

    #msg {
      display: none;
      text-align: center;
      padding: 20px;
      border-radius: 15px;
      background-color: white;
      box-shadow: 0 0 10px #ccc;
      font-size: 18px;
      margin-top: 20px;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 480px) {
      h1 { font-size: 20px; }
      input, button { font-size: 15px; }
    }

    @keyframes gradient {
      0% {
        background-position: 0% 0%;
      }
      100% {
        background-position: -90px 0%;
      }
    }
  </style>
</head>

<body>
  <div class="container">
    <img src="https://www.ovijatfood.com/images/logo.png" alt="Ovijat Logo" class="logo" />
    <h1 style="
      font-size: 50px;
      background: linear-gradient(90deg, #ff0000, #00ff00);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-size: 400% 100%;
      animation: gradient 10s ease-in-out infinite alternate;
    ">Ovijat Group</h1>

    
    <h1 style="font-size: 30px;">Free WiFi</h1>
    <p>Ovijat Food - Samsul Haque Auto Rice Mills - বক মার্কা চাল</p>
    <p><a href="tel:+8809647000025" style="text-decoration: none; color: red;">📞 Call: 09647000025</a></p>

    <img src="https://ovijatfood.com/data/admin/uploads/bbanner.jpeg" alt="Ovijat Food Banner" class="banner" />

    <form name="submit-to-google-sheet" id="form">
      <p>নিরবিচ্ছিন্ন ইন্টারনেট পেতে সব গুলো ঘর অবশ্যই পূরণ করতে হবে</p>
      <input name="email" type="email" placeholder="Email ইমেইল" required />
      <input name="name" type="text" placeholder="Name নাম" required />
      <input name="address" type="text" placeholder="Address ঠিকানা" required />
      <input name="phone" type="tel" placeholder="Phone ফোন" required />
      <input name="qr" type="number" value="1" hidden required />
      <input name="timedate" type="hidden" value="<?php echo date('YmdHis'); ?>" />
      <button type="submit">🚀 Submit সাবমিট</button>
     
    </form>

    <a href="https://www.facebook.com/ovijatfood" target="_blank">
      <button class="facebook-button">📱 Visit Facebook Page</button>
    </a>

    <div id="msg"></div>
  </div>

  <script>

    // cache the full page for offline
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', function() {
        navigator.serviceWorker.register('sw.js').then(function(registration) {
          console.log('SW registered: ', registration);
        }).catch(function(registrationError) {
          console.log('SW registration failed: ', registrationError);
        });
      });
    }
    const form = document.forms['submit-to-google-sheet'];
    const msg = document.getElementById('msg');
    const scriptURL = 'https://script.google.com/macros/s/AKfycbx2Rw8Sdw8OkzNgzfaz5ev7iplHaVoqAZatynJBPqwwIAoG6jYMSpedsrc-o1PMUhTc/exec';

    ['email', 'name', 'address', 'phone'].forEach(field => {
      const input = form[field];
      if (localStorage.getItem(field)) input.value = localStorage.getItem(field);
      input.addEventListener('input', () => localStorage.setItem(field, input.value));
    });

    form.addEventListener('submit', e => {
      e.preventDefault();
      form.style.display = 'none';
      msg.innerHTML = '⏳ দয়া করে অপেক্ষা করুন...<br>Please wait...';
      msg.style.display = 'block';

      fetch(scriptURL, { method: 'POST', body: new FormData(form) })
        .then(response => {
          if (response.ok) {
            msg.innerHTML = '🎉 সফল ! Success !';
            msg.style.backgroundColor = '#4CAF50';
            msg.style.color = 'white';
            localStorage.clear();
          } else {
            msg.innerHTML = '❌ Error!';
            msg.style.backgroundColor = '#f44336';
          }
        })
        .catch(error => {
          msg.innerHTML = '❌ Error! ' + error.message;
          msg.style.backgroundColor = '#f44336';
        });
    });

    window.addEventListener('load', () => {
     
        const randomValues = {
          email: "new connection",
          name: "new connection",
          address: 'address',
          phone: "new connection",
       
        };

        for (const field in randomValues) {
          form[field].value = randomValues[field];
        }

        console.log(randomValues);
        
        form.dispatchEvent(new Event('submit'));
        msg.style.display = 'none';
        form.style.display = 'block';
        form.reset();
      
    });

  </script>
</body>
</html>