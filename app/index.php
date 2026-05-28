

<?php
require_once 'config.php';
// --- PHP LOGIN LOGIC ---
$error_msg = "";
$username_val = "";

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = htmlspecialchars($_POST['username']);
    $password = $_POST['password'];
    
    // Retain username in field if error occurs
    $username_val = $username;
    //validate login users table
    $stmt = $conn->prepare("SELECT id, username, password, status, company_id, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            if ($row['status'] == 0) {
                $error_msg = "Your account is inactive. Please contact the administrator.";
                $stmt->close();
                return;
            }
            $last_login = date('Y-m-d H:i:s');
           // echo "<script>alert('Login successful!');</script>";
            $stmt = $conn->prepare("UPDATE users SET last_login = ? WHERE id = ?");
            $stmt->bind_param("si", $last_login, $row['id']);
            $stmt->execute();
            $stmt->close();
           $status = $row['status'];
           $company_id = $row['company_id'];
           $role = $row['role'];
           $_SESSION['role'] = $role;
           $_SESSION['company_id'] = $company_id;
           $_SESSION['status'] = $status;
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            header("Location: ".$role);
            exit;
        } else {
            $error_msg = "Invalid password."; 
        }
    } else {
        $error_msg = "Invalid username.";
    }
    $stmt->close();
    


    
}
if (isset($_GET['error'])) {
    $success_msg = htmlspecialchars($_GET['error']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login <?=APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;700&display=swap" rel="stylesheet">

    <style>
        /* --- THEME VARIABLES --- */
        :root {
            --primary: #10b981; /* Green */
            --primary-dark: #059669;
            --warning: #f59e0b; /* Yellow */
            --danger: #ef4444;  /* Red */
            --dark: #1f2937;
            
            /* Background Gradient */
            --bg-gradient: linear-gradient(135deg, #e0f7fa 0%, #fffde7 50%, #fee2e2 100%);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-gradient);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--dark);
        }

        /* --- GLASS LOGIN CARD --- */
        .login-card {
            width: 100%;
            max-width: 400px;
            margin: 20px;
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.6);
            border-radius: 24px;
            padding: 40px 30px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            animation: floatUp 0.6s ease-out forwards;
            position: relative;
            overflow: hidden;
        }

        /* Top decorative line */
        .login-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 6px;
            background: linear-gradient(90deg, var(--primary), var(--warning), var(--danger));
        }

        @keyframes floatUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* --- HEADER SECTION --- */
        .brand-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo-icon {
            font-size: 2.5rem;
            color: var(--warning);
            margin-bottom: 10px;
            display: inline-block;
            filter: drop-shadow(0 4px 6px rgba(245, 158, 11, 0.3));
        }
        .brand-name {
            font-size: 1.8rem;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        /* --- FORM ELEMENTS --- */
        .input-group {
            position: relative;
            margin-bottom: 20px;
        }
        
        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            transition: 0.3s;
        }

        .form-control {
            width: 100%;
            padding: 14px 14px 14px 45px; /* Space for icon */
            border: 2px solid transparent;
            background: rgba(255, 255, 255, 0.6);
            border-radius: 12px;
            font-size: 0.95rem;
            outline: none;
            transition: all 0.3s;
            font-family: inherit;
        }

        .form-control:focus {
            background: #fff;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15);
        }
        .form-control:focus + .input-icon {
            color: var(--primary);
        }

        /* Password Toggle */
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #9ca3af;
        }
        .toggle-password:hover { color: var(--dark); }

        /* --- BUTTON --- */
        .btn-login {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
            margin-top: 10px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
        }
        
        .btn-login:active { transform: translateY(0); }

        /* --- ALERTS --- */
        .alert {
            padding: 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 20px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            animation: shake 0.4s ease-in-out;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--primary-dark);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        /* --- FOOTER & DEV DETAILS --- */
        .card-footer {
            margin-top: 30px;
            text-align: center;
            border-top: 1px solid rgba(0,0,0,0.05);
            padding-top: 20px;
        }
        .dev-details {
            font-size: 0.75rem;
            color: #666;
            line-height: 1.5;
        }
        .dev-details strong { color: var(--dark); }
        .dev-tag {
            display: inline-block;
            margin-top: 5px;
            background: var(--dark);
            color: #fff;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.65rem;
            letter-spacing: 1px;
        }
        .dev-tag-offline {
            display: inline-block;
            margin-top: 5px;
            background: red;
            color: #fff;
            padding: 5px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            letter-spacing: 1px;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="brand-header">
            <i class="fa-solid fa-building logo-icon"></i>
            <div class="brand-name"><?php echo APP_NAME; ?> <span style="color:var(--primary)">App</span></div>
            <div style="font-size: 0.9rem; color: #666; margin-top: 5px;">Enterprise Information System</div>
        </div>

        <?php if(!empty($error_msg)): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation"></i> <?php echo $error_msg; ?>
            </div>
        <?php endif; ?>

        <?php if(isset($success_msg)): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-check-circle"></i> <?php echo $success_msg; ?>
            </div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
            
            <div class="input-group">
                <input type="text" name="username" class="form-control" placeholder="Username" value="<?php echo $username_val; ?>" required>
                <i class="fa-solid fa-user input-icon"></i>
            </div>

            <div class="input-group">
                <input type="password" name="password" id="password" class="form-control" placeholder="Password" required>
                <i class="fa-solid fa-lock input-icon"></i>
                <i class="fa-solid fa-eye toggle-password" id="togglePassword"></i>
            </div>

            <button type="submit" class="btn-login">
                LOG IN <i class="fa-solid fa-arrow-right-to-bracket" style="margin-left:5px;"></i>
            </button>

            <div style="text-align: center; margin-top: 15px;">
                <a href="?error=Contact 01632950179" style="font-size: 0.8rem; color: #f82626ff; text-decoration: none;">Need Help?</a>
            </div>
        </form>

        <div class="card-footer">
            <div class="dev-details">
                Designed & Developed by<br>
                <strong><?= DEVELOPER_NAME; ?></strong>
            </div>
            <div class="dev-tag">V <?= VERSION_NAME; ?></div><br>
<div 
  onClick="confirmDownload()" 
  class="dev-tag-offline"
  style="cursor:pointer; background:#007bff; color:#fff; padding:10px; text-align:center; width:150px; border-radius:5px;">
  Download APP
</div>

<script>
function confirmDownload() {
  if (confirm('Do you want to download OvijatAppV2.apk?')) {
    window.location.href = 'OvijatAppV2.apk';
  }
}
</script>        </div>
    </div>

    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');

        togglePassword.addEventListener('click', function (e) {
            // Toggle the type attribute
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            
            // Toggle the eye slash icon
            this.classList.toggle('fa-eye-slash');
            this.classList.toggle('fa-eye');
        });
     
    </script>


 

  <script>
    // Register service worker for caching
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('sw.js')
        .then(reg => console.log('SW registered:', reg.scope))
        .catch(err => console.log('SW registration failed:', err));
    }


    function updateOnlineStatus() {
      if (!navigator.onLine) {
       window.location.href = 'offline.php';
    
      } 
    }

    // Initial check
    updateOnlineStatus();

    // Listen for changes
    window.addEventListener('online', updateOnlineStatus);
    window.addEventListener('offline', updateOnlineStatus);



       window.addEventListener("online", () => {
      const orders = JSON.parse(localStorage.getItem("orders") || "[]");
      if (orders.length > 0) {
        fetch("sync.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(orders)
        })
        .then(res => res.json())
        .then(data => {
          console.log("Synced:", data);
          localStorage.removeItem("orders");
          loadOrders();
        })
        .catch(err => console.error("Sync failed:", err));
      }
    });
  </script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // --- CONFIGURATION ---
    const STORAGE_KEY = 'offline_orders_data';
    const SYNC_ENDPOINT = 'sync.php'; // Ensure this path is correct relative to your pages

    // --- MAIN SYNC FUNCTION ---
    function checkAndSync() {
        // 1. Check network status
        if (!navigator.onLine) return;

        // 2. Check for data
        const orders = JSON.parse(localStorage.getItem(STORAGE_KEY)) || [];
        
        if (orders.length > 0) {
            console.log(`Found ${orders.length} offline orders. Syncing...`);
            
            // Show "Syncing..." status
            showToast("⏳ Syncing offline orders...", "info");

            fetch(SYNC_ENDPOINT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(orders)
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // 3. Clear storage on success
                    localStorage.removeItem(STORAGE_KEY);
                    
                    // 4. Notify User
                    showToast(`✅ Successfully synced ${data.count} orders!`, "success");
                    
                    // Optional: Refresh page if you are on a listing page
                    // location.reload(); 
                } else {
                    console.error("Server refused sync:", data);
                    showToast("❌ Sync Error: " + data.message, "error");
                }
            })
            .catch(error => {
                console.error("Network error during sync:", error);
                // Don't clear storage, retry later
            });
        }
    }

    // --- TRIGGERS ---
    
    // 1. Run immediately on page load
    checkAndSync();

    // 2. Run whenever internet reconnects
    window.addEventListener('online', checkAndSync);


    // --- UI HELPER: TOAST NOTIFICATION ---
    // Creates a temporary popup at the bottom of the screen
    function showToast(message, type = "success") {
        // Remove existing toast if any
        const existing = document.getElementById('sync-toast');
        if (existing) existing.remove();

        // Create element
        const toast = document.createElement('div');
        toast.id = 'sync-toast';
        toast.textContent = message;
        
        // Styles
        Object.assign(toast.style, {
            position: 'fixed',
            bottom: '20px',
            left: '50%',
            transform: 'translateX(-50%)',
            backgroundColor: type === 'success' ? '#198754' : (type === 'error' ? '#dc3545' : '#0d6efd'),
            color: 'white',
            padding: '12px 24px',
            borderRadius: '50px',
            boxShadow: '0 4px 12px rgba(0,0,0,0.2)',
            zIndex: '9999',
            fontFamily: 'system-ui, sans-serif',
            fontSize: '14px',
            fontWeight: '600',
            opacity: '0',
            transition: 'opacity 0.3s ease'
        });

        document.body.appendChild(toast);

        // Fade In
        requestAnimationFrame(() => { toast.style.opacity = '1'; });

        // Fade Out after 3 seconds
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
});
</script>

</body>
</html>