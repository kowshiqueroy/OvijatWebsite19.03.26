







<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ovijat Group Draw Registration</title>
    <link rel="icon" href="../images/logo.ico" type="image/x-icon" />
 <link rel="shortcut icon" href="../images/logo.ico" type="image/x-icon" />


<style>
  * {
    box-sizing: border-box;
    font-family: Arial, sans-serif;
  }

  h1 {
    text-align: center;
    font-size: 30px;
    margin-bottom: 20px;
    color: #4CAF50;
  }
  p {
    text-align: center;
    font-size: 15px;
    margin-bottom: 20px;
    color:rgb(226, 194, 12);
  }

  form {
    max-width: 400px;
    margin: 0 auto;
    text-align: left;
    padding: 20px;
    border: 1px solid #ccc;
    border-radius: 10px;
    box-shadow: 0 0 10px #ccc;
  }

  input {
    display: block;
    width: 100%;
    padding: 10px;
    margin-bottom: 15px;
    border: 1px solid #ccc;
    border-radius: 5px;
  }

  button {
    background-color: #4CAF50;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    display: block;
    margin: 0 auto;
  }

  button:hover {
    background-color: #45a049;
  }
</style>
</head>

<body>
   
<h1>Ovijat Group Raffle Draw</h1>
<p>Ovijat Food - Samsul Haque Auto Rice Mills - বক মার্কা চাল</p>
<p><a href="tel:+8809647000025" style="text-decoration: none; color: red;">Call: 09647000025 (Ovijat IT Call Center)</a></p>


<form name="submit-to-google-sheet" id="form">
    <p>সব গুলো ঘর অবশ্যই পূরণ করতে হবে </p>
  <input name="email" type="text" placeholder="Email ইমেইল" value="your@gmail.com" required>
  <input name="name" type="text" placeholder="Name নাম" required>
  <input name="address" type="text" placeholder="Address ঠিকানা" required>
  <input name="phone" type="text" placeholder="Phone ফোন" required>
  <input name="qr" type="text" placeholder="Product Code প্রোডাক্ট কোড" required>
  <input name="timedate" type="hidden" value="<?php echo date('YmdHis'); ?>">
  <button type="submit">Submit সাবমিট</button>
   <p>১ দিনে ১টির বেশি সাবমিট করবেন না</p>
   <p>প্রতি মাসের শেষ তারিখে লাকি ড্র অনুষ্ঠিত হবে এবং Ovijat Food এর ফেসবুক পেজে বিজয়ীদের লিস্ট প্রকাশ করা হবে। বিজয়ীদের কল করে জানিয়ে কুরিয়ারের মাধ্যম্যে পুরষ্কার পাঠানো হবে।</p>

  


</form>

<br>
<a style="text-decoration:none;" href="https://www.facebook.com/ovijatfood" target="_blank">
  <button>Visit Facebook Group</button>
</a>


  <div id="msg" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background-color: #fff; padding: 10px; border-radius: 10px; box-shadow: 0 0 10px #ccc;"></div>

  <script>
    document.querySelector('form').addEventListener('submit', e => {
      const inputs = document.querySelectorAll('input[required]');
      const allFilled = Array.from(inputs).every(input => input.value != '');
      if (!allFilled) {
        e.preventDefault();
        alert('Please fill out all fields');
      }
    });
    

    document.querySelector('button[type=submit]').addEventListener('click', e => {
      e.target.style.display = 'none';

      const form = document.querySelector('form[name=submit-to-google-sheet]');
      form.style.display = 'none';

      msg.innerHTML = 'Please Wait...';
      msg.style.display = 'block';
      msg.style.color = 'red';
      msg.style.marginTop = '10px';
      msg.style.animation = 'blinker 3s linear infinite';

      const style = document.createElement('style');
      style.innerHTML = `
        @keyframes blinker { 
          50% { opacity: 0; }
        }
      `;
      document.head.appendChild(style);
    });
    const scriptURL = 'https://script.google.com/macros/s/AKfycbyW9NyJrWDMbt9ANoVD-ssYrlhcCA69ebP-dQX3pgQmN_DNumAl4VkeAdexf_zNlTa5Bw/exec'
    const form = document.forms['submit-to-google-sheet']
    const msg = document.getElementById('msg');

    form.addEventListener('submit', e => {
      e.preventDefault()
      fetch(scriptURL, { method: 'POST', body: new FormData(form)})
        .then(response => {
          if (response.ok) {
       msg.style.display = 'block';
msg.style.fontSize = '3em';
msg.style.textAlign = 'center';
msg.innerHTML = 'সফল ! Success ! ';
msg.style.backgroundColor = '#4CAF50';
msg.style.color = 'white';
form.style.display = 'none';

window.addEventListener('beforeunload', e => e.preventDefault());

const button1 = document.createElement('button');
button1.style.display = 'inline-block';
button1.style.margin = '10px';
button1.style.width = '45%';
button1.innerHTML = 'Visit our Facebook Page';
button1.onclick = () => window.open('https://www.facebook.com/ovijatfood', '_self');

const button2 = document.createElement('button');
button2.style.display = 'inline-block';
button2.style.margin = '10px';
button2.style.width = '45%';
button2.innerHTML = 'আরও পূরণ করুন';
button2.onclick = () => location.reload();

const buttonContainer = document.createElement('div');
buttonContainer.style.textAlign = 'center';
buttonContainer.appendChild(button1);
buttonContainer.appendChild(button2);

document.body.appendChild(buttonContainer);

          } else {
            msg.style.display = 'block';
            msg.style.backgroundColor = '#f44336';
            msg.innerHTML = 'Error!';
          }
        })
        .catch(error => {
          msg.style.display = 'block';
          msg.style.backgroundColor = '#f44336';
          msg.innerHTML = 'Error!' + error.message;
        })
    })
  </script>
</body>