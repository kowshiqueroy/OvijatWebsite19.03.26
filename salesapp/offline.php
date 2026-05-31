<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
<title>Offline Sales App</title>
<style>
    :root {
        --primary: #0d6efd;
        --secondary: #6c757d;
        --success: #198754;
        --danger: #dc3545;
        --light: #f8f9fa;
        --dark: #212529;
        --bg: #eef2f5;
        --card-bg: #ffffff;
    }
    
    * { box-sizing: border_box; outline: none; -webkit-tap-highlight-color: transparent; }
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: var(--bg); margin: 0; padding-bottom: 90px; color: var(--dark); }
    
    /* Header */
    header { background: var(--primary); color: white; padding: 15px; position: sticky; top: 0; z-index: 1000; box-shadow: 0 2px 8px rgba(0,0,0,0.15); display: flex; justify-content: space-between; align-items: center; }
    header h1 { margin: 0; font-size: 1.25rem; font-weight: 600; }
    
    /* Status Badge */
    .status-badge { font-size: 0.75rem; padding: 4px 10px; border-radius: 20px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }
    .status-online { background: rgba(255,255,255,0.25); color: #fff; }
    .status-offline { background: var(--danger); color: white; }

    /* Main Layout */
    main { padding: 15px; max-width: 600px; margin: 0 auto; }

    /* Cards */
    .card { background: var(--card-bg); border-radius: 12px; padding: 16px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 16px; border: 1px solid rgba(0,0,0,0.05); }
    .card h2 { font-size: 1.1rem; margin: 0 0 15px 0; color: var(--primary); border-bottom: 2px solid var(--bg); padding-bottom: 8px; }
    
    /* Forms */
    label { display: block; font-size: 0.85rem; font-weight: 600; color: var(--secondary); margin-bottom: 5px; margin-top: 12px; }
    input, select, textarea { width: 100%; padding: 12px; border: 1px solid #ced4da; border-radius: 8px; font-size: 16px; /* Prevents iOS zoom */ background: #fff; transition: border-color 0.2s; }
    input:focus, select:focus, textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15); }
    
    /* Grid Helpers */
    .row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    
    /* Item Rows */
    .item-row { display: grid; grid-template-columns: 1.5fr 0.7fr 0.8fr auto; gap: 8px; align-items: center; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px dashed #e0e0e0; }
    .item-row:last-child { border-bottom: none; }
    .item-row input { padding: 8px; font-size: 0.9rem; }
    .btn-del { background: #fee2e2; color: var(--danger); border: none; width: 32px; height: 32px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
    
    /* Buttons */
    .btn { display: block; width: 100%; padding: 14px; border: none; border-radius: 10px; font-weight: 600; font-size: 1rem; cursor: pointer; text-align: center; transition: opacity 0.2s; }
    .btn:active { opacity: 0.8; transform: scale(0.99); }
    .btn-primary { background: var(--primary); color: white; margin-top: 15px; box-shadow: 0 4px 6px rgba(13, 110, 253, 0.2); }
    .btn-outline { background: transparent; border: 2px dashed #adb5bd; color: var(--secondary); padding: 10px; margin-top: 5px; }
    .btn-danger-sm { background: var(--danger); color: white; padding: 5px 10px; font-size: 0.8rem; width: auto; display: inline-block; border-radius: 6px; }

    /* Floating Sync Button */
    .fab-sync { position: fixed; bottom: 20px; right: 20px; background: var(--success); color: white; width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 15px rgba(25, 135, 84, 0.4); cursor: pointer; border: none; z-index: 100; transition: transform 0.2s; font-size: 1.8rem; }
    .fab-sync:active { transform: scale(0.95); }
    .fab-badge { position: absolute; top: 0; right: 0; background: var(--danger); color: white; font-size: 0.75rem; min-width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid white; font-weight: bold; }

    /* Orders List */
    .order-list-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #eee; }
    .order-list-item:last-child { border-bottom: none; }
    .order-info h4 { margin: 0 0 4px 0; font-size: 1rem; color: var(--dark); }
    .order-info small { color: var(--secondary); font-size: 0.85rem; }

    /* Total Display */
    .total-display { text-align: right; font-size: 1.25rem; font-weight: 800; color: var(--primary); margin-top: 10px; }

    .hidden { display: none !important; }
</style>
</head>
<body>

<header>
    <h1>📋 Order Entry</h1>
    <div id="connectionStatus" class="status-badge status-offline">Connecting...</div>
</header>

<main>
    <form id="orderForm" autocomplete="off">
        <div class="card">
            <h2>Customer Info</h2>
            
            <label>User Name</label>
            <input type="text" name="user_name" id="user_name" list="history_users" placeholder="e.g. John Doe" required>
            
            <label>Route Name</label>
            <input type="text" name="route_name" list="history_routes" placeholder="e.g. Sector 7" required>
            
            <label>Shop Name/Details</label>
            <input type="text" name="shop_details" list="history_shops" placeholder="e.g. Rahim Store" required>
            
            <div class="row-2">
                <div>
                    <label>Order Date</label>
                    <input type="date" name="order_date" id="order_date" required>
                </div>
                <div>
                    <label>Delivery Date</label>
                    <input type="date" name="delivery_date" id="delivery_date" required>
                </div>
            </div>

            <label>Note / Remarks</label>
            <textarea name="note" rows="2" placeholder="Optional notes..."></textarea>
        </div>

        <div class="card">
            <h2>Order Items</h2>
            <div id="itemsContainer"></div>
            
            <button type="button" class="btn btn-outline" onclick="addItem()">+ Add Item</button>

            <div class="total-display">
                Total: <span id="grandTotal">0.00</span>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Save Order (Offline)</button>
    </form>

    <div id="savedOrdersSection" class="card hidden" style="margin-top: 25px; border: 1px solid var(--primary);">
        <h2 style="margin-bottom: 0;">Unsynced Orders</h2>
        <div id="ordersList"></div>
    </div>
</main>

<button class="fab-sync hidden" id="syncBtn" onclick="syncOrders()">
    ☁️
    <div id="syncBadge" class="fab-badge">0</div>
</button>

<datalist id="history_users"></datalist>
<datalist id="history_routes"></datalist>
<datalist id="history_shops"></datalist>
<datalist id="history_items"></datalist>

<script>
    // --- CONFIGURATION ---
    const SYNC_ENDPOINT = 'sync.php'; // Points to your separate PHP file
    const STORAGE_KEY = 'offline_orders_data';
    const HISTORY_KEY = 'offline_smart_memory';

    // --- 1. INITIALIZATION & STATE ---
    
    // Load History or Default
    let history = JSON.parse(localStorage.getItem(HISTORY_KEY)) || {
        users: [], routes: [], shops: [], items: [] 
    };

    // Set Default Dates
    const today = new Date().toISOString().split('T')[0];
    const tomorrow = new Date(new Date().getTime() + 24 * 60 * 60 * 1000).toISOString().split('T')[0];
    document.getElementById('order_date').value = today;
    document.getElementById('delivery_date').value = tomorrow;

    // Auto-fill last User Name
    if(history.users.length > 0) {
        document.getElementById('user_name').value = history.users[history.users.length - 1];
    }

    // --- 2. SMART MEMORY FUNCTIONS ---

    function renderDatalists() {
        populateList('history_users', history.users);
        populateList('history_routes', history.routes);
        populateList('history_shops', history.shops);
        
        const itemList = document.getElementById('history_items');
        itemList.innerHTML = '';
        history.items.forEach(item => {
            const opt = document.createElement('option');
            opt.value = item.name;
            opt.dataset.price = item.price; // Storing price in dataset
            itemList.appendChild(opt);
        });
    }

    function populateList(elementId, array) {
        const list = document.getElementById(elementId);
        list.innerHTML = '';
        // Deduplicate and populate
        [...new Set(array)].forEach(val => {
            const opt = document.createElement('option');
            opt.value = val;
            list.appendChild(opt);
        });
    }
    
    function updateHistory(key, value) {
        if(value && !history[key].includes(value)) {
            history[key].push(value);
        }
    }

    // Call immediately
    renderDatalists();

    // --- 3. UI LOGIC (Items) ---

    function addItem(name = '', qty = 1, price = '') {
        const container = document.getElementById('itemsContainer');
        const div = document.createElement('div');
        div.className = 'item-row';
        div.innerHTML = `
            <input type="text" class="item-name" placeholder="Item" list="history_items" value="${name}" onchange="autoFillPrice(this)">
            <input type="number" class="item-qty" placeholder="Qty" value="${qty}" min="1" oninput="calculateTotals()">
            <input type="number" class="item-price" placeholder="Price" value="${price}" step="0.01" oninput="calculateTotals()">
            <button type="button" class="btn-del" onclick="removeRow(this)">×</button>
        `;
        container.appendChild(div);
        
        // Focus if it's a new empty row
        if(name === '') div.querySelector('.item-name').focus();
    }

    function removeRow(btn) {
        btn.parentElement.remove();
        calculateTotals();
    }

    // Auto-fill price based on Item Name selection
    function autoFillPrice(input) {
        const val = input.value;
        const found = history.items.find(i => i.name === val);
        if(found) {
            const row = input.parentElement;
            row.querySelector('.item-price').value = found.price;
            calculateTotals();
        }
    }

    function calculateTotals() {
        let total = 0;
        document.querySelectorAll('.item-row').forEach(row => {
            const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
            const price = parseFloat(row.querySelector('.item-price').value) || 0;
            total += (qty * price);
        });
        document.getElementById('grandTotal').textContent = total.toFixed(2);
    }

    // Add one empty item on load
    addItem();

    // --- 4. SAVE ORDER (OFFLINE) ---

    document.getElementById('orderForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const form = e.target;
        const items = [];
        
        // 1. Gather Items
        document.querySelectorAll('.item-row').forEach(row => {
            const name = row.querySelector('.item-name').value.trim();
            const qty = row.querySelector('.item-qty').value;
            const price = row.querySelector('.item-price').value;
            
            if(name) {
                items.push({ name: name, qty: qty, price: price, total: (qty*price).toFixed(2) });
                
                // Update Item History
                const existingIndex = history.items.findIndex(i => i.name === name);
                if(existingIndex > -1) {
                    history.items[existingIndex].price = price; // Update price
                } else {
                    history.items.push({ name: name, price: price });
                }
            }
        });

        if(items.length === 0) {
            alert("Please add at least one item.");
            return;
        }

        // 2. Build Object
        const newOrder = {
            temp_id: Date.now(),
            user_name: form.user_name.value.trim(),
            route_name: form.route_name.value.trim(),
            shop_details: form.shop_details.value.trim(),
            order_date: form.order_date.value,
            delivery_date: form.delivery_date.value,
            note: form.note.value.trim(),
            items: items,
            total: document.getElementById('grandTotal').textContent,
            admin_approval_id: null, // As required by your PHP logic
            admin_approval_timedate: null
        };

        // 3. Save to LocalStorage
        let orders = JSON.parse(localStorage.getItem(STORAGE_KEY)) || [];
        orders.push(newOrder);
        localStorage.setItem(STORAGE_KEY, JSON.stringify(orders));

        // 4. Update Smart Memory
        updateHistory('users', newOrder.user_name);
        updateHistory('routes', newOrder.route_name);
        updateHistory('shops', newOrder.shop_details);
        localStorage.setItem(HISTORY_KEY, JSON.stringify(history));
        renderDatalists(); // Refresh dropdowns

        // 5. Reset UI
        alert("✅ Order Saved Locally!");
        form.shop_details.value = ''; // Clear shop
        form.note.value = '';
        document.getElementById('itemsContainer').innerHTML = '';
        addItem(); // Add fresh row
        calculateTotals();
        refreshSavedList();
    });

    // --- 5. SYNC LOGIC (Connects to sync.php) ---

    function refreshSavedList() {
        const list = document.getElementById('ordersList');
        const section = document.getElementById('savedOrdersSection');
        const syncBtn = document.getElementById('syncBtn');
        const badge = document.getElementById('syncBadge');
        
        const orders = JSON.parse(localStorage.getItem(STORAGE_KEY)) || [];
        
        list.innerHTML = '';
        
        if (orders.length > 0) {
            section.classList.remove('hidden');
            syncBtn.classList.remove('hidden');
            badge.textContent = orders.length;
            
            orders.forEach((order, index) => {
                const div = document.createElement('div');
                div.className = 'order-list-item';
                div.innerHTML = `
                    <div class="order-info">
                        <h4>${order.shop_details}</h4>
                        <small>${order.route_name} • ${order.items.length} Items • <strong>${order.total}</strong></small>
                    </div>
                    <button class="btn-danger-sm" onclick="deleteOrder(${index})">Delete</button>
                `;
                list.appendChild(div);
            });
        } else {
            section.classList.add('hidden');
            syncBtn.classList.add('hidden');
        }
    }

    function deleteOrder(index) {
        if(!confirm("Remove this order from offline storage?")) return;
        let orders = JSON.parse(localStorage.getItem(STORAGE_KEY)) || [];
        orders.splice(index, 1);
        localStorage.setItem(STORAGE_KEY, JSON.stringify(orders));
        refreshSavedList();
    }

    async function syncOrders() {
        const orders = JSON.parse(localStorage.getItem(STORAGE_KEY)) || [];
        if (orders.length === 0) return;

        const btn = document.getElementById('syncBtn');
        const badge = document.getElementById('syncBadge');
        const originalContent = btn.innerHTML;
        
        btn.innerHTML = '⏳'; // Loading spinner
        btn.disabled = true;

        try {
            // Send Data to sync.php
            const response = await fetch(SYNC_ENDPOINT, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(orders)
            });

            const result = await response.json();

            if (result.status === 'success') {
                alert(`🎉 Synced ${result.count} orders successfully!`);
                // Clear storage on success
                localStorage.removeItem(STORAGE_KEY);
                refreshSavedList();
            } else {
                throw new Error(result.message || "Unknown server error");
            }

        } catch (error) {
            console.error(error);
            alert("❌ Sync Failed: " + error.message + "\nCheck your connection or sync.php path.");
        } finally {
            btn.innerHTML = originalContent; // Restore icon
            btn.disabled = false;
        }
    }

    // --- 6. CONNECTION STATUS ---

    function updateStatus() {
        const el = document.getElementById('connectionStatus');
        if (navigator.onLine) {
            el.innerHTML = `<a href="index.php" >Online</a>`;
            el.className = "status-badge status-online";
        } else {
            el.textContent = "Offline";
            el.className = "status-badge status-offline";
        }
    }

    window.addEventListener('online', updateStatus);
    window.addEventListener('offline', updateStatus);
    
    // Start up
    updateStatus();
    refreshSavedList();

</script>
</body>
</html>