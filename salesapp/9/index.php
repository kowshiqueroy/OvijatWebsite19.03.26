<?php
include 'header.php';

// Base where clause for the selected company
$selected_company = isset($_GET['company_id']) ? $_GET['company_id'] : '';
$where_clause = !empty($selected_company) ? "AND o.company_id = '" . mysqli_real_escape_string($conn, $selected_company) . "'" : "";
?>

<div class="container">
   <div class="glass-panel printable">
   
   <div class="form-section" style="margin-bottom: 30px; text-align: center; background: #f9f9f9; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
       <form method="GET" action="">
           <label for="company_id" style="font-size: 1.2rem; margin-right: 10px; font-weight: 600; color: #333;"><i class="fa-solid fa-building"></i> Select Dashboard Data:</label>
           <select name="company_id" id="company_id" onchange="this.form.submit()" style="padding: 10px; font-size: 1rem; border-radius: 5px; border: 1px solid #ccc; min-width: 250px;">
               <option value="">-- All Companies Overview --</option>
               <?php
               $comp_query = "SELECT id, name FROM companies ORDER BY name ASC";
               $comp_result = mysqli_query($conn, $comp_query);
               if ($comp_result && mysqli_num_rows($comp_result) > 0) {
                   while ($comp = mysqli_fetch_assoc($comp_result)) {
                       $selected = ($selected_company == $comp['id']) ? 'selected' : '';
                       echo "<option value='{$comp['id']}' {$selected}>{$comp['name']}</option>";
                   }
               }
               ?>
           </select>
       </form>
   </div>

  <style>
    .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px; }
    .kpi-card { background: linear-gradient(135deg, #ffffff, #f1f5f9); border-radius: 12px; padding: 20px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-left: 5px solid var(--primary); }
    .kpi-card h3 { margin: 0; font-size: 1rem; color: #666; font-weight: 500; text-transform: uppercase; }
    .kpi-card .value { font-size: 2rem; font-weight: 700; color: #333; margin: 10px 0 0; }
    
    .dashboard-section { margin-bottom: 50px; background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    .dashboard-section h2 { font-weight: 600; font-size: 1.5rem; margin-top: 0; margin-bottom: 20px; color: #2c3e50; border-bottom: 2px solid #eee; padding-bottom: 10px; }
    
    .grid-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; align-items: center; }
    @media(max-width: 768px) { .grid-2col { grid-template-columns: 1fr; } }
    
    table.data-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
    table.data-table th, table.data-table td { border: 1px solid #eee; padding: 12px; text-align: left; }
    table.data-table th { background-color: #f8f9fa; color: #333; font-weight: 600; }
    table.data-table tr:hover { background-color: #f1f5f9; }
    
    .chart-container { position: relative; height: 300px; width: 100%; }
    .chart-container-large { position: relative; height: 400px; width: 100%; }
  </style>

  <?php
  // ==========================================
  // 1. KPI Queries
  // ==========================================
  $kpi_sales_q = mysqli_query($conn, "SELECT COALESCE(SUM(oi.quantity * oi.price), 0) as total FROM orders o JOIN order_items oi ON o.id = oi.order_id WHERE o.order_status = 1 $where_clause");
  $kpi_sales = mysqli_fetch_assoc($kpi_sales_q)['total'];

  $kpi_orders_q = mysqli_query($conn, "SELECT COUNT(id) as total FROM orders o WHERE o.order_status = 1 $where_clause");
  $kpi_orders = mysqli_fetch_assoc($kpi_orders_q)['total'];

  $kpi_shops_q = mysqli_query($conn, "SELECT COUNT(DISTINCT shop_id) as total FROM orders o WHERE o.order_status = 1 $where_clause");
  $kpi_shops = mysqli_fetch_assoc($kpi_shops_q)['total'];
  ?>

  <div class="kpi-grid">
      <div class="kpi-card" style="border-left-color: #28a745;">
          <h3><i class="fa-solid fa-sack-dollar"></i> Total Revenue</h3>
          <div class="value">$<?php echo number_format($kpi_sales, 2); ?></div>
      </div>
      <div class="kpi-card" style="border-left-color: #007bff;">
          <h3><i class="fa-solid fa-cart-check"></i> Confirmed Orders</h3>
          <div class="value"><?php echo number_format($kpi_orders); ?></div>
      </div>
      <div class="kpi-card" style="border-left-color: #ffc107;">
          <h3><i class="fa-solid fa-store"></i> Active Shops</h3>
          <div class="value"><?php echo number_format($kpi_shops); ?></div>
      </div>
      <div class="kpi-card" style="border-left-color: #17a2b8;">
          <h3><i class="fa-solid fa-chart-line"></i> Avg Order Value</h3>
          <div class="value">$<?php echo $kpi_orders > 0 ? number_format($kpi_sales / $kpi_orders, 2) : '0.00'; ?></div>
      </div>
  </div>

  <div class="dashboard-section">
      <h2>📈 30-Day Sales Trend</h2>
      <div class="chart-container-large">
          <canvas id="trendLineChart"></canvas>
      </div>
      <table class="data-table" id="trendTable" style="display:none;">
          <thead><tr><th>Date</th><th>Revenue</th></tr></thead>
          <tbody>
              <?php
              $trend_q = "SELECT DATE(o.created_at) as order_date, COALESCE(SUM(oi.quantity * oi.price), 0) as daily_rev 
                          FROM orders o JOIN order_items oi ON o.id = oi.order_id 
                          WHERE o.order_status = 1 $where_clause 
                          GROUP BY DATE(o.created_at) ORDER BY order_date DESC LIMIT 30";
              $trend_res = mysqli_query($conn, $trend_q);
              $trend_data = [];
              if($trend_res) { while($row = mysqli_fetch_assoc($trend_res)) { $trend_data[] = $row; } }
              $trend_data = array_reverse($trend_data); // Reverse to show oldest to newest left-to-right
              foreach($trend_data as $row) {
                  echo "<tr><td>" . date('M d', strtotime($row['order_date'])) . "</td><td>{$row['daily_rev']}</td></tr>";
              }
              if(empty($trend_data)) echo "<tr><td>No Data</td><td>0</td></tr>";
              ?>
          </tbody>
      </table>
  </div>

  <div class="dashboard-section grid-2col">
      <div>
          <h2>📦 Top Selling Products</h2>
          <table class="data-table" id="itemTable">
            <thead><tr><th>Product Name</th><th>Qty Sold</th><th>Revenue ($)</th></tr></thead>
            <tbody>
              <?php
              $item_q = "SELECT i.item_name, SUM(oi.quantity) as qty, COALESCE(SUM(oi.quantity * oi.price), 0) as rev 
                         FROM order_items oi JOIN orders o ON oi.order_id = o.id JOIN items i ON oi.item_id = i.id 
                         WHERE o.order_status = 1 $where_clause GROUP BY oi.item_id ORDER BY rev DESC LIMIT 5";
              $item_res = mysqli_query($conn, $item_q);
              if($item_res && mysqli_num_rows($item_res) > 0) {
                  while($row = mysqli_fetch_assoc($item_res)) {
                      echo "<tr><td>{$row['item_name']}</td><td>{$row['qty']}</td><td>" . number_format($row['rev'], 2) . "</td><td class='raw-val' style='display:none;'>{$row['rev']}</td></tr>";
                  }
              } else { echo "<tr><td>No Data</td><td>0</td><td>0.00</td><td class='raw-val' style='display:none;'>0</td></tr>"; }
              ?>
            </tbody>
          </table>
      </div>
      <div class="chart-container"><canvas id="itemDoughnutChart"></canvas></div>
  </div>

  <div class="dashboard-section grid-2col">
      <div class="chart-container"><canvas id="userBarChart"></canvas></div>
      <div>
          <h2>👨‍💼 Top Sales Representatives</h2>
          <table class="data-table" id="userTable">
            <thead><tr><th>Sales Rep</th><th>Orders</th><th>Revenue ($)</th></tr></thead>
            <tbody>
              <?php
              $user_q = "SELECT u.username, COUNT(DISTINCT o.id) as orders, COALESCE(SUM(oi.quantity * oi.price), 0) as rev 
                         FROM orders o LEFT JOIN order_items oi ON o.id = oi.order_id JOIN users u ON o.created_by = u.id 
                         WHERE o.order_status = 1 $where_clause GROUP BY o.created_by ORDER BY rev DESC LIMIT 5";
              $user_res = mysqli_query($conn, $user_q);
              if($user_res && mysqli_num_rows($user_res) > 0) {
                  while($row = mysqli_fetch_assoc($user_res)) {
                      echo "<tr><td>{$row['username']}</td><td>{$row['orders']}</td><td>" . number_format($row['rev'], 2) . "</td><td class='raw-val' style='display:none;'>{$row['rev']}</td></tr>";
                  }
              } else { echo "<tr><td>No Data</td><td>0</td><td>0.00</td><td class='raw-val' style='display:none;'>0</td></tr>"; }
              ?>
            </tbody>
          </table>
      </div>
  </div>

  <div class="dashboard-section grid-2col">
      <div>
          <h2>🗺️ Top Performing Routes</h2>
          <table class="data-table" id="routeTable">
            <thead><tr><th>Route Name</th><th>Revenue ($)</th></tr></thead>
            <tbody>
              <?php
              $route_q = "SELECT r.route_name, COALESCE(SUM(oi.quantity * oi.price), 0) as rev 
                          FROM orders o LEFT JOIN order_items oi ON o.id = oi.order_id JOIN routes r ON o.route_id = r.id 
                          WHERE o.order_status = 1 $where_clause GROUP BY o.route_id ORDER BY rev DESC LIMIT 5";
              $route_res = mysqli_query($conn, $route_q);
              if($route_res && mysqli_num_rows($route_res) > 0) {
                  while($row = mysqli_fetch_assoc($route_res)) {
                      echo "<tr><td>{$row['route_name']}</td><td>" . number_format($row['rev'], 2) . "</td><td class='raw-val' style='display:none;'>{$row['rev']}</td></tr>";
                  }
              } else { echo "<tr><td>No Data</td><td>0.00</td><td class='raw-val' style='display:none;'>0</td></tr>"; }
              ?>
            </tbody>
          </table>
      </div>
      <div class="chart-container"><canvas id="routePieChart"></canvas></div>
  </div>

  <div class="dashboard-section grid-2col">
      <div class="chart-container"><canvas id="shopPolarChart"></canvas></div>
      <div>
          <h2>🏪 Top Shops/Customers</h2>
          <table class="data-table" id="shopTable">
            <thead><tr><th>Shop Name</th><th>Revenue ($)</th></tr></thead>
            <tbody>
              <?php
              $shop_q = "SELECT s.shop_name, COALESCE(SUM(oi.quantity * oi.price), 0) as rev 
                         FROM orders o LEFT JOIN order_items oi ON o.id = oi.order_id JOIN shops s ON o.shop_id = s.id 
                         WHERE o.order_status = 1 $where_clause GROUP BY o.shop_id ORDER BY rev DESC LIMIT 5";
              $shop_res = mysqli_query($conn, $shop_q);
              if($shop_res && mysqli_num_rows($shop_res) > 0) {
                  while($row = mysqli_fetch_assoc($shop_res)) {
                      echo "<tr><td>{$row['shop_name']}</td><td>" . number_format($row['rev'], 2) . "</td><td class='raw-val' style='display:none;'>{$row['rev']}</td></tr>";
                  }
              } else { echo "<tr><td>No Data</td><td>0.00</td><td class='raw-val' style='display:none;'>0</td></tr>"; }
              ?>
            </tbody>
          </table>
      </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Universal color palettes
const bgColors = ['rgba(54, 162, 235, 0.7)', 'rgba(255, 99, 132, 0.7)', 'rgba(255, 206, 86, 0.7)', 'rgba(75, 192, 192, 0.7)', 'rgba(153, 102, 255, 0.7)'];
const borderColors = ['#36a2eb', '#ff6384', '#ffce56', '#4bc0c0', '#9966ff'];

// Utility to parse tables (Looks for a specific hidden column containing unformatted raw numbers)
function parseTableData(tableId, labelColIndex, valueColIndex) {
  const rows = document.querySelectorAll(`#${tableId} tbody tr`);
  const labels = []; const values = [];
  rows.forEach(row => {
    const cells = row.querySelectorAll('td');
    labels.push(cells[labelColIndex].innerText);
    // Parse the hidden raw-val column if it exists, otherwise fallback to parsing text
    const rawValCell = row.querySelector('.raw-val');
    const val = rawValCell ? parseFloat(rawValCell.innerText) : parseFloat(cells[valueColIndex].innerText.replace(/,/g, ''));
    values.push(val || 0);
  });
  return { labels, values };
}

// 1. Trend Line Chart
const trendData = parseTableData('trendTable', 0, 1);
new Chart(document.getElementById('trendLineChart'), {
  type: 'line',
  data: {
    labels: trendData.labels,
    datasets: [{
      label: 'Daily Revenue ($)', data: trendData.values,
      borderColor: '#28a745', backgroundColor: 'rgba(40, 167, 69, 0.1)',
      borderWidth: 2, fill: true, tension: 0.3, pointBackgroundColor: '#28a745'
    }]
  },
  options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
});

// 2. Item Doughnut Chart
const itemData = parseTableData('itemTable', 0, 2);
new Chart(document.getElementById('itemDoughnutChart'), {
  type: 'doughnut',
  data: {
    labels: itemData.labels,
    datasets: [{ data: itemData.values, backgroundColor: bgColors, borderColor: '#fff', borderWidth: 2 }]
  },
  options: { responsive: true, maintainAspectRatio: false }
});

// 3. User Bar Chart
const userData = parseTableData('userTable', 0, 2);
new Chart(document.getElementById('userBarChart'), {
  type: 'bar',
  data: {
    labels: userData.labels,
    datasets: [{ label: 'Revenue Generated ($)', data: userData.values, backgroundColor: bgColors, borderColor: borderColors, borderWidth: 1 }]
  },
  options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
});

// 4. Route Pie Chart
const routeData = parseTableData('routeTable', 0, 1);
new Chart(document.getElementById('routePieChart'), {
  type: 'pie',
  data: {
    labels: routeData.labels,
    datasets: [{ data: routeData.values, backgroundColor: bgColors, borderColor: '#fff', borderWidth: 2 }]
  },
  options: { responsive: true, maintainAspectRatio: false }
});

// 5. Shop Polar Area Chart
const shopData = parseTableData('shopTable', 0, 1);
new Chart(document.getElementById('shopPolarChart'), {
  type: 'polarArea',
  data: {
    labels: shopData.labels,
    datasets: [{ data: shopData.values, backgroundColor: bgColors }]
  },
  options: { responsive: true, maintainAspectRatio: false }
});
</script>

<?php include 'footer.php'; ?>