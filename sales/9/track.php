<?php
include 'header.php';
?>

<div class="print-header">
    <h1><?php echo APP_NAME; ?> Location Tracking</h1>
    <p>Generated Date: <?php echo date("Y-m-d"); ?></p>
</div>

<div class="container">
    <div class="form-section glass-panel" style="margin-bottom: 20px;">
        <form method="GET">
            <div class="grid-layout desktop-4">
                <div><label>Date From</label>
                    <input type="date" name="date_from" value="<?php echo $_GET['date_from'] ?? date('Y-m-d'); ?>">
                </div>
                <div><label>Date To</label>
                    <input type="date" name="date_to" value="<?php echo $_GET['date_to'] ?? date('Y-m-d'); ?>">
                </div>
                
                <div><label>Company</label>
                    <select name="search_company_id">
                        <option value="">All Companies</option>
                        <?php
                        // FIXED: Removed 'WHERE status = 1'
                        $c_query = "SELECT id, name FROM companies ORDER BY name ASC";
                        $c_res = mysqli_query($conn, $c_query);
                        if ($c_res) {
                            while ($row = mysqli_fetch_assoc($c_res)) {
                                $sel = ($_GET['search_company_id'] ?? '') == $row['id'] ? 'selected' : '';
                                echo "<option value='{$row['id']}' $sel>{$row['name']}</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                
                <div><label>Route</label>
                    <select name="search_route_id">
                        <option value="">All Routes</option>
                        <?php
                        $r_query = "SELECT id, route_name FROM routes WHERE status = 1";
                        $r_res = mysqli_query($conn, $r_query);
                        if ($r_res) {
                            while ($row = mysqli_fetch_assoc($r_res)) {
                                $sel = ($_GET['search_route_id'] ?? '') == $row['id'] ? 'selected' : '';
                                echo "<option value='{$row['id']}' $sel>{$row['route_name']}</option>";
                            }
                        }
                        ?>
                    </select>
                </div>

                <div><label>User</label>
                    <select name="search_id">
                        <option value="">All Users</option>
                        <?php
                        $u_query = "SELECT id, username FROM users WHERE status = 1";
                        $u_res = mysqli_query($conn, $u_query);
                        if ($u_res) {
                            while ($row = mysqli_fetch_assoc($u_res)) {
                                $sel = ($_GET['search_id'] ?? '') == $row['id'] ? 'selected' : '';
                                echo "<option value='{$row['id']}' $sel>{$row['username']}</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                
                <div><label>Status</label>
                    <select name="search_status">
                        <option value="">All</option>
                        <option value="1" <?php echo ($_GET['search_status'] ?? '') == '1' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="0" <?php echo ($_GET['search_status'] ?? '') == '0' ? 'selected' : ''; ?>>Pending</option>
                    </select>
                </div>
            </div>
            <div class="form-actions" style="margin-top: 20px;">
               <button type="submit" name="search_order" class="btn btn-green"><i class="fa-solid fa-search"></i> Search</button>
            </div>
        </form>
    </div>

    <div class="glass-panel printable">
        <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
            <span class="section-title" style="margin:0;">Location Data</span>
            <button onclick="window.print()" class="btn btn-dark"><i class="fa-solid fa-print"></i></button>
        </div>
        
        <div class="table-responsive">
            <table class="table-simple" id="dataTable">
                <thead>
                    <tr>
                        <th>ID & Time</th>
                        <th style='display: none'>lat</th>
                        <th style='display: none'>long</th>
                        <th>User & Company</th>
                        <th>Location Link</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $query = "SELECT o.*, u.username, c.name as company_name 
                              FROM orders o 
                              LEFT JOIN users u ON o.created_by = u.id 
                              LEFT JOIN companies c ON o.company_id = c.id 
                              WHERE 1=1";
                              
                    if (isset($_GET['search_order'])) {
                        if (!empty($_GET['search_company_id'])) $query .= " AND o.company_id='" . mysqli_real_escape_string($conn, $_GET['search_company_id']) . "'";
                        if (!empty($_GET['search_id'])) $query .= " AND o.created_by='" . mysqli_real_escape_string($conn, $_GET['search_id']) . "'";
                        if (!empty($_GET['search_route_id'])) $query .= " AND o.route_id='" . mysqli_real_escape_string($conn, $_GET['search_route_id']) . "'";
                        if ($_GET['search_status'] != '') $query .= " AND o.order_status='" . mysqli_real_escape_string($conn, $_GET['search_status']) . "'";
                        if (!empty($_GET['date_from']) && !empty($_GET['date_to'])) {
                            $query .= " AND o.created_at BETWEEN '" . mysqli_real_escape_string($conn, $_GET['date_from']) . " 00:00:00' AND '" . mysqli_real_escape_string($conn, $_GET['date_to']) . " 23:59:59'";
                        }
                    } else {
                        $query .= " ORDER BY o.id DESC LIMIT 10"; // Default view
                    }
                    
                    $result = mysqli_query($conn, $query);
                    if ($result && mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $lat = $row['latitude'] ? $row['latitude'] : 0;
                            $lng = $row['longitude'] ? $row['longitude'] : 0;
                            $status = $row['order_status'] ? "Confirmed" : "Pending";
                            
                            echo "<tr>
                                    <td>#{$row['id']}<br>{$row['created_at']}</td>
                                    <td style='display: none'>{$lat}</td>
                                    <td style='display: none'>{$lng}</td>
                                    <td>{$row['username']}<br><small>{$row['company_name']} [{$status}]</small></td>
                                    <td>
                                        <span id='address_{$row['id']}'></span>
                                        <button class='btn btn-dark btn-sm' id='btn_{$row['id']}' onClick=\"getLocation({$lat}, {$lng}, {$row['id']})\"><i class='fa-solid fa-eye'></i> View Area</button>
                                    </td>
                                  </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' style='text-align:center;'>No locations found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

    <div class="glass-panel printable">
        <div style="text-align:center; margin:20px;">
          <button class="btn btn-green" id="showMapBtn" style="padding:10px 20px;"><i class="fa-solid fa-map"></i> Plot on Map</button>
        </div>
        <div id="map" style="height:500px; width:100%; display:none; border-radius: 10px;"></div>
    </div>
    
    <script>
    // Reverse Geocode
    function getLocation(lat, lng, orderId) {
        if (lat == 0 && lng == 0) return document.getElementById("address_" + orderId).innerText = "No GPS Data";
        document.getElementById("btn_" + orderId).style.display = "none";
        document.getElementById("address_" + orderId).innerText = "Loading...";
        fetch(`https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=${lat}&longitude=${lng}&localityLanguage=en`)
            .then(res => res.json())
            .then(data => {
                document.getElementById("address_" + orderId).innerText = [data.locality, data.city].filter(Boolean).join(', ') || "Unknown";
            }).catch(() => document.getElementById("address_" + orderId).innerText = "Error");
    }

    // Leaflet Map
    document.getElementById("showMapBtn").addEventListener("click", () => {
        document.getElementById("map").style.display = "block";
        document.getElementById("showMapBtn").style.display = "none";
        
        const map = L.map('map').setView([23.685, 90.3563], 7);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

        const rows = document.getElementById("dataTable").getElementsByTagName("tr");
        for (let i = 1; i < rows.length; i++) {
            const cells = rows[i].getElementsByTagName("td");
            if (cells.length >= 3) {
                const lat = parseFloat(cells[1].innerText);
                const lng = parseFloat(cells[2].innerText);
                const info = cells[3].innerText; // User & Company

                if (lat !== 0 && lng !== 0) {
                    L.marker([lat, lng]).addTo(map).bindPopup(`<b>${info}</b>`);
                }
            }
        }
    });
    </script>
</div>
<?php include 'footer.php'; ?>