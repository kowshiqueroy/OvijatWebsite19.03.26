<?php
include 'header.php';

// Filter Variables
$interval = $_GET['interval'] ?? 'month'; // Default to Monthly
$date_from = $_GET['date_from'] ?? date('Y-m-01', strtotime('-6 months')); // Default last 6 months
$date_to = $_GET['date_to'] ?? date('Y-m-t');
$company_id = $_GET['company_id'] ?? '';

// Base WHERE clause
$where = "WHERE o.order_status = 1 ";
if (!empty($company_id)) $where .= " AND o.company_id = '" . mysqli_real_escape_string($conn, $company_id) . "'";
if (!empty($date_from) && !empty($date_to)) {
    $where .= " AND o.created_at BETWEEN '" . mysqli_real_escape_string($conn, $date_from) . " 00:00:00' AND '" . mysqli_real_escape_string($conn, $date_to) . " 23:59:59'";
}

// Determine SQL Date Formatting based on selected interval
$sql_date_format = "'%Y-%m'"; // Default Month (e.g., 2026-02)
$display_format = "F Y";      // e.g., February 2026

if ($interval == 'day') {
    $sql_date_format = "'%Y-%m-%d'";
    $display_format = "d M Y";
} elseif ($interval == 'year') {
    $sql_date_format = "'%Y'";
    $display_format = "Y";
}
?>

<div class="container">
    <div class="form-section glass-panel" style="margin-bottom: 30px;">
        <h2 style="margin-top:0;"><i class="fa-solid fa-chart-area"></i> Trend & Performance Analytics</h2>
        <p style="color: #666; font-size: 0.9rem;">Analyze sales over time and compare top-performing reps, items, and shops.</p>
        
        <form method="GET">
            <div class="grid-layout desktop-4">
                <div>
                    <label>Trend Interval</label>
                    <select name="interval" style="border-color: var(--primary); font-weight: bold;">
                        <option value="day" <?php if($interval == 'day') echo 'selected'; ?>>Daily</option>
                        <option value="month" <?php if($interval == 'month') echo 'selected'; ?>>Monthly</option>
                        <option value="year" <?php if($interval == 'year') echo 'selected'; ?>>Yearly</option>
                    </select>
                </div>
                <div><label>Date From</label><input type="date" name="date_from" value="<?php echo $date_from; ?>"></div>
                <div><label>Date To</label><input type="date" name="date_to" value="<?php echo $date_to; ?>"></div>
                <div>
                    <label>Company</label>
                    <select name="company_id">
                        <option value="">-- All Companies --</option>
                        <?php
                        $comp_q = mysqli_query($conn, "SELECT id, name FROM companies ORDER BY name ASC");
                        if ($comp_q) while ($comp = mysqli_fetch_assoc($comp_q)) {
                            $sel = ($company_id == $comp['id']) ? 'selected' : '';
                            echo "<option value='{$comp['id']}' $sel>{$comp['name']}</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class="form-actions" style="margin-top: 15px;">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-rotate"></i> Update Trends</button>
            </div>
        </form>
    </div>

    <style>
        .analytics-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 40px; align-items: stretch; }
        @media(max-width: 992px) { .analytics-grid { grid-template-columns: 1fr; } }
        .data-card { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .data-card h3 { margin-top: 0; color: #2c3e50; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px; font-size: 1.3rem; }
        .table-scroll { max-height: 350px; overflow-y: auto; border: 1px solid #eee; border-radius: 5px; }
        table.full-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        table.full-table th, table.full-table td { padding: 10px; border-bottom: 1px solid #eee; text-align: left; }
        table.full-table th { background: #f8f9fa; position: sticky; top: 0; z-index: 1; box-shadow: 0 2px 2px -1px rgba(0,0,0,0.1); }
        .chart-box { position: relative; height: 350px; width: 100%; display: flex; align-items: center; justify-content: center; }
    </style>

    <div class="data-card" style="margin-bottom: 40px;">
        <h3><i class="fa-solid fa-arrow-trend-up"></i> Sales Trend (<?php echo ucfirst($interval); ?>)</h3>
        <div class="analytics-grid">
            <div class="chart-box" style="height: 400px;"><canvas id="trendChart"></canvas></div>
            <div class="table-scroll" style="max-height: 400px;">
                <table class="full-table" id="trendTable">
                    <thead><tr><th>Period</th><th>Revenue ($)</th></tr></thead>
                    <tbody>
                        <?php
                        // Group by Date formatting
                        $trend_q = "SELECT DATE_FORMAT(o.created_at, $sql_date_format) as period, 
                                           COALESCE(SUM(oi.quantity * oi.price), 0) as rev 
                                    FROM orders o 
                                    LEFT JOIN order_items oi ON o.id = oi.order_id 
                                    $where 
                                    GROUP BY period 
                                    ORDER BY period ASC"; // ASC so the chart reads left-to-right properly
                        $trend_res = mysqli_query($conn, $trend_q);
                        if ($trend_res && mysqli_num_rows($trend_res) > 0) {
                            while ($row = mysqli_fetch_assoc($trend_res)) {
                                // Format the period nicely for display
                                $display_date = ($interval == 'year') ? $row['period'] : date($display_format, strtotime($row['period'] . ($interval=='month'?'-01':'')));
                                echo "<tr>
                                        <td>{$display_date}</td>
                                        <td>$" . number_format($row['rev'], 2) . "</td>
                                        <td class='raw-val' style='display:none;'>{$row['rev']}</td>
                                      </tr>";
                            }
                        } else { echo "<tr><td colspan='3'>No data available.</td></tr>"; }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="analytics-grid">
        <div class="data-card">
            <h3><i class="fa-solid fa-user-tie"></i> Top 10 Sales Reps</h3>
            <div class="chart-box"><canvas id="srChart"></canvas></div>
        </div>
        <div class="data-card">
            <h3>Full Sales Rep Data</h3>
            <div class="table-scroll">
                <table class="full-table" id="srTable">
                    <thead><tr><th>Rep Name</th><th>Total Sales ($)</th></tr></thead>
                    <tbody>
                        <?php
                        $sr_q = "SELECT u.username, COALESCE(SUM(oi.quantity * oi.price), 0) as rev 
                                 FROM orders o 
                                 JOIN users u ON o.created_by = u.id 
                                 LEFT JOIN order_items oi ON o.id = oi.order_id 
                                 $where GROUP BY u.id ORDER BY rev DESC";
                        $sr_res = mysqli_query($conn, $sr_q);
                        if ($sr_res && mysqli_num_rows($sr_res) > 0) {
                            while ($row = mysqli_fetch_assoc($sr_res)) {
                                echo "<tr><td>{$row['username']}</td><td>$" . number_format($row['rev'], 2) . "</td><td class='raw-val' style='display:none;'>{$row['rev']}</td></tr>";
                            }
                        } else { echo "<tr><td colspan='3'>No data available.</td></tr>"; }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="analytics-grid">
        <div class="data-card">
            <h3>Full Product Data</h3>
            <div class="table-scroll">
                <table class="full-table" id="itemTable">
                    <thead><tr><th>Product Name</th><th>Qty Sold</th><th>Revenue ($)</th></tr></thead>
                    <tbody>
                        <?php
                        $item_q = "SELECT i.item_name, SUM(oi.quantity) as qty, COALESCE(SUM(oi.quantity * oi.price), 0) as rev 
                                   FROM orders o 
                                   JOIN order_items oi ON o.id = oi.order_id 
                                   JOIN items i ON oi.item_id = i.id 
                                   $where GROUP BY i.id ORDER BY rev DESC";
                        $item_res = mysqli_query($conn, $item_q);
                        if ($item_res && mysqli_num_rows($item_res) > 0) {
                            while ($row = mysqli_fetch_assoc($item_res)) {
                                echo "<tr>
                                        <td>{$row['item_name']}</td>
                                        <td>{$row['qty']}</td>
                                        <td>$" . number_format($row['rev'], 2) . "</td>
                                        <td class='raw-val' style='display:none;'>{$row['rev']}</td>
                                      </tr>";
                            }
                        } else { echo "<tr><td colspan='4'>No data available.</td></tr>"; }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="data-card">
            <h3><i class="fa-solid fa-box-open"></i> Top 10 Items by Revenue</h3>
            <div class="chart-box"><canvas id="itemChart"></canvas></div>
        </div>
    </div>

    <div class="analytics-grid">
        <div class="data-card">
            <h3><i class="fa-solid fa-store"></i> Top 10 Shops</h3>
            <div class="chart-box"><canvas id="shopChart"></canvas></div>
        </div>
        <div class="data-card">
            <h3>Full Shop Data</h3>
            <div class="table-scroll">
                <table class="full-table" id="shopTable">
                    <thead><tr><th>Shop Name</th><th>Revenue ($)</th></tr></thead>
                    <tbody>
                        <?php
                        $shop_q = "SELECT s.shop_name, COALESCE(SUM(oi.quantity * oi.price), 0) as rev 
                                   FROM orders o 
                                   JOIN shops s ON o.shop_id = s.id 
                                   LEFT JOIN order_items oi ON o.id = oi.order_id 
                                   $where GROUP BY s.id ORDER BY rev DESC";
                        $shop_res = mysqli_query($conn, $shop_q);
                        if ($shop_res && mysqli_num_rows($shop_res) > 0) {
                            while ($row = mysqli_fetch_assoc($shop_res)) {
                                echo "<tr><td>{$row['shop_name']}</td><td>$" . number_format($row['rev'], 2) . "</td><td class='raw-val' style='display:none;'>{$row['rev']}</td></tr>";
                            }
                        } else { echo "<tr><td colspan='3'>No data available.</td></tr>"; }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Universal color palettes
const bgColors = ['rgba(54, 162, 235, 0.7)', 'rgba(255, 99, 132, 0.7)', 'rgba(255, 206, 86, 0.7)', 'rgba(75, 192, 192, 0.7)', 'rgba(153, 102, 255, 0.7)', 'rgba(255, 159, 64, 0.7)', 'rgba(199, 199, 199, 0.7)', 'rgba(83, 215, 105, 0.7)', 'rgba(235, 54, 162, 0.7)', 'rgba(54, 235, 206, 0.7)'];
const borderColors = ['#36a2eb', '#ff6384', '#ffce56', '#4bc0c0', '#9966ff', '#ff9f40', '#c7c7c7', '#53d769', '#eb36a2', '#36ebce'];

// Enhanced Utility: Read table data but limit array to top N for the charts
function parseTableData(tableId, labelColIndex, valueColIndex, limit = null) {
  const rows = document.querySelectorAll(`#${tableId} tbody tr`);
  const labels = []; const values = [];
  
  let count = 0;
  for(let row of rows) {
    if (limit !== null && count >= limit) break; // Stop if we hit the limit (e.g., Top 10)
    
    const cells = row.querySelectorAll('td');
    if(cells.length > 1 && cells[0].innerText !== "No data available.") {
        labels.push(cells[labelColIndex].innerText);
        const rawValCell = row.querySelector('.raw-val');
        const val = rawValCell ? parseFloat(rawValCell.innerText) : 0;
        values.push(val);
        count++;
    }
  }
  return { labels, values };
}

// 1. Overall Trend Chart (Line) - No limit, show full trend
const trendData = parseTableData('trendTable', 0, 1);
new Chart(document.getElementById('trendChart'), {
  type: 'line',
  data: {
    labels: trendData.labels,
    datasets: [{
      label: 'Revenue ($)', data: trendData.values,
      borderColor: '#007bff', backgroundColor: 'rgba(0, 123, 255, 0.1)',
      borderWidth: 2, fill: true, tension: 0.3, pointBackgroundColor: '#007bff'
    }]
  },
  options: { responsive: true, maintainAspectRatio: false }
});

// 2. Top 10 SR Performance (Bar) - Limit to 10
const srData = parseTableData('srTable', 0, 1, 10);
new Chart(document.getElementById('srChart'), {
  type: 'bar',
  data: {
    labels: srData.labels,
    datasets: [{ label: 'Sales Generated ($)', data: srData.values, backgroundColor: bgColors, borderColor: borderColors, borderWidth: 1 }]
  },
  options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
});

// 3. Top 10 Items (Doughnut) - Limit to 10
const itemData = parseTableData('itemTable', 0, 2, 10);
new Chart(document.getElementById('itemChart'), {
  type: 'doughnut',
  data: {
    labels: itemData.labels,
    datasets: [{ data: itemData.values, backgroundColor: bgColors, borderColor: '#fff', borderWidth: 2 }]
  },
  options: { responsive: true, maintainAspectRatio: false }
});

// 4. Top 10 Shops (Bar Horizontal) - Limit to 10
const shopData = parseTableData('shopTable', 0, 1, 10);
new Chart(document.getElementById('shopChart'), {
  type: 'bar',
  data: {
    labels: shopData.labels,
    datasets: [{ label: 'Shop Purchases ($)', data: shopData.values, backgroundColor: bgColors, borderColor: borderColors, borderWidth: 1 }]
  },
  options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } } // indexAxis 'y' makes it horizontal
});
</script>

<?php include 'footer.php'; ?>