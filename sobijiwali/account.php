<?php
/**
 * User Account Dashboard
 */
require_once 'includes/Database.php';
require_once 'includes/AuthManager.php';
require_once 'includes/WalletManager.php';

AuthManager::requireRole(['retail', 'wholesale', 'admin', 'editor', 'warehouse', 'reports']);

$db = Database::getInstance();
$walletManager = new WalletManager();

$userId = $_SESSION['user_id'];
$userEmail = $_SESSION['user_email'];
$message = '';
$error = '';

// Fetch Profile (and create a dummy one if missing to prevent crashes)
$profile = $db->query("SELECT * FROM user_profiles WHERE user_id = ?", [$userId])->fetch();
if (!$profile) {
    $profile = ['first_name' => 'User', 'last_name' => '', 'phone' => ''];
}
$balance = $walletManager->getBalance($userId);
$tab = $_GET['tab'] ?? 'dashboard';

$pageTitle = 'My Harvest Dashboard';
include 'templates/header.php';
?>

<div class="section-container">
    <div style="display: grid; grid-template-columns: 280px 1fr; gap: 4rem; align-items: start;">
        
        <!-- Sidebar -->
        <aside>
            <div style="background: white; padding: 2.5rem; border-radius: 30px; box-shadow: var(--card-shadow); border: 1px solid var(--border);">
                <div style="text-align: center; margin-bottom: 2.5rem;">
                    <div style="width: 100px; height: 100px; background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: 800; margin: 0 auto 1.5rem; box-shadow: 0 10px 20px rgba(45, 90, 39, 0.2);">
                        <?php echo strtoupper(substr($profile['first_name'], 0, 1)); ?>
                    </div>
                    <h2 style="font-weight: 800; color: var(--text); margin-bottom: 0.2rem;"><?php echo htmlspecialchars($profile['first_name']); ?></h2>
                    <p style="font-size: 0.8rem; opacity: 0.5; font-weight: 700;"><?php echo htmlspecialchars($userEmail); ?></p>
                </div>

                <nav style="display: flex; flex-direction: column; gap: 0.6rem;">
                    <a href="account?tab=dashboard" style="padding: 1rem 1.5rem; border-radius: 15px; text-decoration: none; font-weight: 800; transition: var(--transition); <?php echo ($tab === 'dashboard') ? 'background: var(--primary); color: white;' : 'color: var(--text); background: var(--bg);'; ?>">📊 Dashboard</a>
                    <a href="account?tab=orders" style="padding: 1rem 1.5rem; border-radius: 15px; text-decoration: none; font-weight: 800; transition: var(--transition); <?php echo ($tab === 'orders') ? 'background: var(--primary); color: white;' : 'color: var(--text); background: var(--bg);'; ?>">📦 My Orders</a>
                    <a href="account?tab=ship_details" style="padding: 1rem 1.5rem; border-radius: 15px; text-decoration: none; font-weight: 800; transition: var(--transition); <?php echo ($tab === 'ship_details') ? 'background: var(--primary); color: white;' : 'color: var(--text); background: var(--bg);'; ?>">🚚 Shipping Details</a>
                    <a href="account?tab=bill_details" style="padding: 1rem 1.5rem; border-radius: 15px; text-decoration: none; font-weight: 800; transition: var(--transition); <?php echo ($tab === 'bill_details') ? 'background: var(--primary); color: white;' : 'color: var(--text); background: var(--bg);'; ?>">💳 Billing Details</a>
                </nav>

                <div style="margin-top: 3rem; padding-top: 2rem; border-top: 1px dashed var(--border);">
                    <a href="logout" style="color: var(--error); text-decoration: none; font-weight: 800; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem; justify-content: center;">🚪 Sign Out</a>
                </div>
            </div>
        </aside>

        <!-- Content Area -->
        <div>
            <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>

            <?php if ($tab === 'dashboard'): ?>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 3rem;">
                    <div style="background: white; padding: 2rem; border-radius: 24px; border: 1px solid var(--border); border-bottom: 5px solid var(--primary); box-shadow: var(--card-shadow);">
                        <div style="font-size: 0.7rem; font-weight: 800; opacity: 0.4; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 1rem;">Available Credit</div>
                        <div style="font-size: 2rem; font-weight: 800; color: var(--primary);">$<?php echo number_format($balance, 2); ?></div>
                    </div>
                    <div style="background: white; padding: 2rem; border-radius: 24px; border: 1px solid var(--border); border-bottom: 5px solid var(--accent); box-shadow: var(--card-shadow);">
                        <div style="font-size: 0.7rem; font-weight: 800; opacity: 0.4; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 1rem;">Total Savings</div>
                        <div style="font-size: 2rem; font-weight: 800; color: var(--accent);">$0.00</div>
                    </div>
                    <div style="background: white; padding: 2rem; border-radius: 24px; border: 1px solid var(--border); border-bottom: 5px solid #3498db; box-shadow: var(--card-shadow);">
                        <div style="font-size: 0.7rem; font-weight: 800; opacity: 0.4; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 1rem;">Orders</div>
                        <div style="font-size: 2rem; font-weight: 800; color: #3498db;"><?php echo $db->query("SELECT COUNT(*) FROM orders WHERE user_id = ?", [$userId])->fetchColumn(); ?></div>
                    </div>
                </div>

                <div style="background: white; padding: 3rem; border-radius: 30px; border: 1px solid var(--border); box-shadow: var(--card-shadow);">
                    <h3 style="font-weight: 800; color: var(--primary); margin-bottom: 1.5rem;">Harvest Update</h3>
                    <p style="opacity: 0.7; line-height: 1.8; font-weight: 500;">Welcome to your personal dashboard. From here, you can track your farm-to-table journey, manage your delivery locations, and chat directly with our harvesting team.</p>
                </div>

            <?php elseif ($tab === 'orders'): ?>
                <?php if (isset($_GET['order_id'])): ?>
                    <?php 
                    $orderId = (int)$_GET['order_id'];
                    $order = $db->query("SELECT * FROM orders WHERE id = ? AND user_id = ?", [$orderId, $userId])->fetch();
                    if (!$order) die("Order access denied.");
                    $msgs = $db->query("SELECT * FROM order_messages WHERE order_id = ? ORDER BY created_at ASC", [$orderId])->fetchAll();
                    ?>
                    <div style="background: white; padding: 3rem; border-radius: 30px; border: 1px solid var(--border); box-shadow: var(--card-shadow);">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
                            <h2 style="font-weight:800; color:var(--text);">Order #<?php echo $orderId; ?> Chat</h2>
                            <a href="account?tab=orders" style="font-weight:800; color:var(--primary); text-decoration:none;">&larr; Back</a>
                        </div>
                        <div style="height:350px; overflow-y:auto; background:var(--bg); border-radius:20px; padding:2rem; display:flex; flex-direction:column; gap:1rem; margin-bottom:2rem;" id="chat-scroller">
                            <?php foreach ($msgs as $m): ?>
                                <div style="padding:1rem 1.5rem; border-radius:18px; max-width:80%; font-size:0.9rem; font-weight:600; <?php echo $m['sender_type'] === 'customer' ? 'align-self:flex-end; background:var(--primary); color:white;' : 'align-self:flex-start; background:white; border:1px solid var(--border); color:var(--text);'; ?>">
                                    <?php echo htmlspecialchars($m['message']); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo AuthManager::generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="send_message">
                            <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
                            <div style="display:flex; gap:1rem;">
                                <input type="text" name="message" placeholder="Type your inquiry here..." required style="flex:1; padding:1.2rem; border-radius:15px; border:2px solid var(--bg);">
                                <button type="submit" class="btn-harvest" style="width:auto; padding:0 2rem;">Send</button>
                            </div>
                        </form>
                    </div>
                    <script>const cs = document.getElementById('chat-scroller'); cs.scrollTop = cs.scrollHeight;</script>
                <?php else: ?>
                    <div style="background: white; padding: 3rem; border-radius: 30px; border: 1px solid var(--border); box-shadow: var(--card-shadow);">
                        <h3 style="font-weight:800; color:var(--primary); margin-bottom:2.5rem;">Purchase History</h3>
                        <?php $hist = $db->query("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC", [$userId])->fetchAll(); ?>
                        <?php if (empty($hist)): ?>
                            <div style="text-align:center; padding:3rem; opacity:0.4;">No orders found yet.</div>
                        <?php else: ?>
                            <table style="width:100%; border-collapse:collapse;">
                                <thead>
                                    <tr style="text-align:left; border-bottom:2px solid var(--bg);">
                                        <th style="padding:1rem 0; font-size:0.7rem; text-transform:uppercase; opacity:0.5;">Order</th>
                                        <th style="padding:1rem 0; font-size:0.7rem; text-transform:uppercase; opacity:0.5;">Status</th>
                                        <th style="padding:1rem 0; font-size:0.7rem; text-transform:uppercase; opacity:0.5; text-align:right;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($hist as $h): ?>
                                        <tr style="border-bottom:1px solid #f9f9f9;">
                                            <td style="padding:1.5rem 0;">
                                                <div style="font-weight:800; color:var(--primary);">#<?php echo $h['id']; ?></div>
                                                <div style="font-size:0.75rem; opacity:0.5;"><?php echo date('M d, Y', strtotime($h['created_at'])); ?></div>
                                            </td>
                                            <td style="padding:1.5rem 0;"><span style="padding:0.4rem 1rem; border-radius:50px; font-size:0.65rem; font-weight:800; background:var(--bg); color:var(--primary); text-transform:uppercase;"><?php echo $h['status']; ?></span></td>
                                            <td style="padding:1.5rem 0; text-align:right; display:flex; gap:0.5rem; justify-content:flex-end; align-items:center;">
                                                <a href="account?tab=orders&action=reorder&order_id=<?php echo $h['id']; ?>" class="btn-harvest" style="padding:5px 10px; font-size:0.7rem; text-decoration:none;">Reorder</a>
                                                <a href="account?tab=orders&order_id=<?php echo $h['id']; ?>" style="font-size:1.2rem; text-decoration:none;">💬</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php elseif ($tab === 'ship_details' || $tab === 'bill_details'): ?>
                <?php $type = ($tab === 'ship_details') ? 'shipping' : 'billing'; ?>
                <div style="display: grid; grid-template-columns: 1fr 380px; gap: 4rem; align-items: start;">
                    <div style="background: white; padding: 3rem; border-radius: 30px; border: 1px solid var(--border); box-shadow: var(--card-shadow);">
                        <h3 style="font-weight: 800; color: var(--primary); margin-bottom: 2rem;">Saved <?php echo ucfirst($type); ?> Locations</h3>
                        <?php $addrs = $db->query("SELECT * FROM user_addresses WHERE user_id = ? AND address_type = ? ORDER BY is_default DESC", [$userId, $type])->fetchAll(); ?>
                        <div style="display: grid; gap: 1.5rem;">
                            <?php if (empty($addrs)): ?>
                                <p style="opacity: 0.5;">No <?php echo $type; ?> addresses saved yet.</p>
                            <?php else: ?>
                                <?php foreach ($addrs as $a): ?>
                                    <div style="padding: 1.5rem; border-radius: 20px; border: 2px solid <?php echo $a['is_default'] ? 'var(--primary)' : 'var(--bg)'; ?>; position: relative;">
                                        <?php if ($a['is_default']): ?><span style="position:absolute; top:-10px; right:20px; background:var(--primary); color:white; font-size:0.6rem; font-weight:800; padding:4px 10px; border-radius:10px;">PRIMARY</span><?php endif; ?>
                                        <div style="font-weight: 800; margin-bottom: 0.3rem;"><?php echo htmlspecialchars($a['full_name']); ?></div>
                                        <div style="font-size: 0.8rem; opacity: 0.6; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($a['email']); ?> | <?php echo htmlspecialchars($a['phone']); ?></div>
                                        <div style="font-size: 0.9rem; opacity: 0.7; line-height: 1.6;"><?php echo nl2br(htmlspecialchars($a['address_line1'])); ?></div>
                                        <?php if ($a['notes']): ?>
                                            <div style="margin-top:0.8rem; font-size:0.75rem; background:var(--bg); padding:0.8rem; border-radius:10px; font-style:italic;">Note: <?php echo htmlspecialchars($a['notes']); ?></div>
                                        <?php endif; ?>
                                        <form method="POST" style="margin-top:1.5rem;">
                                            <input type="hidden" name="csrf_token" value="<?php echo AuthManager::generateCSRFToken(); ?>">
                                            <input type="hidden" name="action" value="delete_address">
                                            <input type="hidden" name="address_id" value="<?php echo $a['id']; ?>">
                                            <input type="hidden" name="address_type" value="<?php echo $type; ?>">
                                            <button type="submit" style="background:none; border:none; color:var(--error); font-weight:800; font-size:0.75rem; cursor:pointer;">🗑️ Remove</button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div style="background: white; padding: 2.5rem; border-radius: 30px; border: 1px solid var(--border); box-shadow: var(--card-shadow);">
                        <h3 style="font-weight: 800; color: var(--primary); margin-bottom: 2rem;">New <?php echo ucfirst($type); ?></h3>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo AuthManager::generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="add_address">
                            <input type="hidden" name="address_type" value="<?php echo $type; ?>">
                            <div class="form-group"><label>Recipient Name *</label><input type="text" name="full_name" required placeholder="e.g. John Doe"></div>
                            <div class="form-group"><label>Email *</label><input type="email" name="email" required placeholder="john@example.com"></div>
                            <div class="form-group"><label>Phone *</label><input type="tel" name="phone" required placeholder="+1 234 567 890"></div>
                            <div class="form-group"><label>Address Details *</label><textarea name="address_line1" rows="3" required placeholder="Street, City, Postcode"></textarea></div>
                            <div class="form-group"><label>Special Note</label><textarea name="notes" rows="2" placeholder="e.g. Leave at back door"></textarea></div>
                            <label style="display:flex; align-items:center; gap:0.5rem; font-weight:700; font-size:0.8rem; cursor:pointer;"><input type="checkbox" name="is_default" style="width:auto;"> Use as Primary</label>
                            <button type="submit" class="btn-harvest" style="width:100%; margin-top:2rem;">Save Details</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>

<?php if (isset($reorderJSON)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const newItems = <?php echo $reorderJSON; ?>;
            let current = Cart.get();
            newItems.forEach(newItem => {
                let existing = current.find(i => i.variation_id === newItem.variation_id);
                if (existing) existing.quantity += newItem.quantity;
                else current.push(newItem);
            });
            Cart.save(current);
            Toast.show("Previous order items have been added to your basket!", "success");
        });
    </script>
<?php endif; ?>
