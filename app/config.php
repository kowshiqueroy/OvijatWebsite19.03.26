<?php
/* DB Config */
define('DB_HOST', 'localhost');
if ($_SERVER['SERVER_NAME'] != "localhost" && strpos($_SERVER['SERVER_NAME'], "free.app") === false)
{
    define('DB_NAME', 'u312077073_app');
    define('DB_USER', 'u312077073_app');
    define('DB_PASS', 'KR5877kush');
}
else
{
    define('DB_NAME', 'oeis');
    define('DB_USER', 'root');
    define('DB_PASS', '');
}
define('DB_CHARSET', 'utf8mb4');
/* App Info */
define('APP_NAME', 'Ovijat EIS');
define('DEVELOPER_NAME', 'Kowshique Roy');
define('VERSION_NAME', '2.3.1');
session_start();
date_default_timezone_set('Asia/Dhaka');

$live_td = strtotime('2025-12-20 15:00:00');
if ($live_td > time())
    
    
    
    {
    echo '
<style>
body {
    margin: 0;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    background: #f0f4f8;
}

.msg-box {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;

    width: 90%;
    max-width: 600px;
    padding: 20px;
    border-radius: 12px;

    background: rgba(255, 255, 255, 0.25);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);

    color: #22543d;
    font-weight: 600;
    font-size: 3.2rem;
    text-align: center;
    line-height: 1.4;

    box-shadow: 0 8px 20px rgba(0,0,0,0.2);
}

/* Rocket animation */
.rocket {
    font-size: 10rem;
    display: inline-block;
    animation: fly 2s infinite ease-in-out;
}

@keyframes fly {
    0%   { transform: translateY(0) rotate(0deg); }
    25%  { transform: translateY(-10px) rotate(-10deg); }
    50%  { transform: translateY(-20px) rotate(0deg); }
    75%  { transform: translateY(-10px) rotate(10deg); }
    100% { transform: translateY(0) rotate(0deg); }
}

.msg-box small {
    font-weight: 400;
    font-size: 1.5rem;
    opacity: 0.8;
    animation: pulse 2s infinite;
    color: #db1c1cff;
}
@keyframes pulse {
    0%, 100% { opacity: 0.8; }
    50%      { opacity: 0.5; }
}

@media (max-width: 400px) {
    .msg-box {
        font-size: 1rem;
        padding: 15px;
    }
}
</style>
';

echo '<div class="msg-box">
        <span class="rocket">🚀</span> Working on Server Side <br>  Under Development Mode<br><br>
        <small>App: ' . APP_NAME . ' | Developer: ' . DEVELOPER_NAME . ' | Version: ' . VERSION_NAME . '<br>Please Wait for a while</small>
      
        <small id="countdown">'. date("F j, Y, g:i a", $live_td).'</small>
       
      </div>';

exit;
}


$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    echo '<div class="msg-box error">
            ❌ Error: Could not connect to database <br>
            <small>' . htmlspecialchars($conn->connect_error) . '</small>
          </div>';
    exit;
}


?>