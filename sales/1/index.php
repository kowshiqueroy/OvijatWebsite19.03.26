<?php
include 'header.php';

// ==========================================
// 1. SECURE SESSION LOCK
// ==========================================
$session_company_id = $_SESSION['company_id'] ?? 1; // Fallback to 1 if testing
$session_user_id = $_SESSION['user_id'] ?? 1;       // Fallback to 1 if testing

// ==========================================
// 2. FILTER PARAMETERS
// ==========================================
$interval = $_GET['interval'] ?? 'day'; 
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // Default: Start of this month
$date_to = $_GET['date_to'] ?? date('Y-m-t');      // Default: End of this month
$compare_user_id = $_GET['compare_user_id'] ?? '';

// Determine SQL & PHP Date Formatting based on interval
if ($interval == 'year') {
    $sql_format = '%Y'; $php_format = 'Y'; $step = '+1 year'; $display_format = 'Y';
} elseif ($interval == 'month') {
    $sql_format = '%Y-%m'; $php_format = 'Y-m'; $step = '+1 month'; $display_format = 'F Y';
} else {
    $sql_format = '%Y-%m-%d'; $php_format = 'Y-m-d'; $step = '+1 day'; $display_format = 'd M Y';
}

// ==========================================
// 3. DATA FETCHING FUNCTION
// ==========================================
// This function gets gross and returns for a specific user and calculates net
function getUserNetTrend($conn, $company_id, $user_id, $from, $to, $sql_format) {
    $data = [];
    
    // Fetch Gross Sales
    $q1 = "SELECT DATE_FORMAT(o.created_at, '$sql_format') as dt, SUM(oi.quantity * oi.price) as val 
           FROM orders o JOIN order_items oi ON o.id = oi.order_id 
           WHERE o.order_status=1 AND o.company_id='$company_id' AND o.created_by='$user_id' 
           AND o.created_at BETWEEN '$from 00:00:00' AND '$to 23:59:59' 
           GROUP BY dt";
    $res1 = mysqli_query($conn, $q1);
    if($res1) while($r = mysqli_fetch_assoc($res1)) { $data[$r['dt']]['gross'] = $r['val']; }
    
    // Fetch Returns
    $q2 = "SELECT DATE_FORMAT(r.created_at, '$sql_format') as dt, SUM(ori.return_qty * ori.price) as val 
           FROM order_returns r JOIN order_return_items ori ON r.id = ori.return_id 
           WHERE r.company_id='$company_id' AND r.user_id='$user_id' 
           AND r.created_at BETWEEN '$from 00:00:00' AND '$to 23:59:59' 
           GROUP BY dt";
    $res2 = mysqli_query($conn, $q2);
    if($res2) while($r = mysqli_fetch_assoc($res2)) { $data[$r['dt']]['ret'] = $r['val']; }
    
    return $data;
}

// Fetch Data for Primary User (Logged in User)
$my_data_raw = getUserNetTrend($conn, $session_company_id, $session_user_id, $date_from, $date_to, $sql_format);

// Fetch Data for Compared User (If Selected)
$compare_data_raw = [];
$compare_user_name = "";
if (!empty($compare_user_id)) {
    $compare_data_raw = getUserNetTrend($conn, $session_company_id, $compare_user_id, $date_from, $date_to, $sql_format);
    $c_user_q = mysqli_query($conn, "SELECT username FROM users WHERE id='$compare_user_id'");
    if($c_user_q && mysqli_num_rows($c_user_q) > 0) {
        $compare_user_name = mysqli_fetch_assoc($c_user_q)['username'];
    }
}

// ==========================================
// 4. PREPARE UNIFORM DATA FOR CHART.JS
// ==========================================
$chart_labels = [];
$my_gross_arr = []; $my_ret_arr = []; $my_net_arr = [];
$comp_gross_arr = []; $comp_ret_arr = []; $comp_net_arr = [];

$total_my_gross = 0; $total_my_ret = 0; $total_my_net = 0;
$total_comp_gross = 0; $total_comp_ret = 0; $total_comp_net = 0;

$current_date = strtotime($date_from);
$end_date = strtotime($date_to);

// Create a continuous timeline so charts don't have gaps
while ($current_date <= $end_date) {
    $period_key = date($php_format, $current_date);
    $chart_labels[] = date($display_format, $current_date);
    
    // My Data Calculation
    $m_gross = $my_data_raw[$period_key]['gross'] ?? 0;
    $m_ret = $my_data_raw[$period_key]['ret'] ?? 0;
    $m_net = $m_gross - $m_ret;
    
    $my_gross_arr[] = $m_gross; $my_ret_arr[] = $m_ret; $my_net_arr[] = $m_net;
    $total_my_gross += $m_gross; $total_my_ret += $m_ret; $total_my_net += $m_net;
    
    // Compare Data Calculation
    if (!empty($compare_user_id)) {
        $c_gross = $compare_data_raw[$period_key]['gross'] ?? 0;
        $c_ret = $compare_data_raw[$period_key]['ret'] ?? 0;
        $c_net = $c_gross - $c_ret;
        
        $comp_gross_arr[] = $c_gross; $comp_ret_arr[] = $c_ret; $comp_net_arr[] = $c_net;
        $total_comp_gross += $c_gross; $total_comp_ret += $c_ret; $total_comp_net += $c_net;
    }
    
    $current_date = strtotime($step, $current_date);
}
?>

<div class="container">
    
    <div class="form-section glass-panel" style="margin-bottom: 30px; border-top: 4px solid var(--primary);">
        <h2 style="margin-top:0;"><i class="fa-solid fa-chart-line"></i> My Performance Dashboard</h2>
        <p style="color: #666; font-size: 0.9rem;">Analyze your net sales trends. Expand the date range to compare months, or select a colleague to compare performance.</p>
        
        <form method="GET">
            <div class="grid-layout desktop-4">
                <div>
                    <label>View By</label>
                    <select name="interval" style="border-color: var(--primary); font-weight: bold;">
                        <option value="day" <?php echo $interval == 'day' ? 'selected' : ''; ?>>Daily</option>
                        <option value="month" <?php echo $interval == 'month' ? 'selected' : ''; ?>>Monthly</option>
                        <option value="year" <?php echo $interval == 'year' ? 'selected' : ''; ?>>Yearly</option>
                    </select>
                </div>
                <div><label>Date From</label><input type="date" name="date_from" value="<?php echo $date_from; ?>"></div>
                <div><label>Date To</label><input type="date" name="date_to" value="<?php echo $date_to; ?>"></div>
                
                <div>
                    <label>Compare With (Optional)</label>
                    <select name="compare_user_id">
                        <option value="">-- No Comparison --</option>
                        <?php
                        // Fetch other users in the same company
                        $uq = mysqli_query($conn, "SELECT id, username FROM users WHERE company_id='$session_company_id' AND id != '$session_user_id' AND status=1");
                        if ($uq) while ($u = mysqli_fetch_assoc($uq)) {
                            $sel = ($compare_user_id == $u['id']) ? 'selected' : '';
                            echo "<option value='{$u['id']}' $sel>{$u['username']}</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class="form-actions" style="margin-top: 15px;">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-bolt"></i> Update Dashboard</button>
            </div>
        </form>
    </div>

    <style>
        .kpi-wrapper { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .kpi-box { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .kpi-main h4 { margin: 0 0 5px 0; color: #888; font-weight: 500; text-transform: uppercase; font-size: 0.85rem; }
        .kpi-main h2 { margin: 0; color: #333; font-size: 2rem; }
        .kpi-sub { text-align: right; font-size: 0.9rem; }
    </style>

    <div class="kpi-wrapper">
        <div class="kpi-box" style="border-left: 5px solid #28a745;">
            <div class="kpi-main">
                <h4><i class="fa-solid fa-user"></i> My Net Sales</h4>
                <h2>$<?php echo number_format($total_my_net, 2); ?></h2>
            </div>
            <div class="kpi-sub">
                <div style="color: #666;">Gross: $<?php echo number_format($total_my_gross, 2); ?></div>
                <div style="color: #dc3545;">Returns: -$<?php echo number_format($total_my_ret, 2); ?></div>
            </div>
        </div>

        <?php if (!empty($compare_user_id)): ?>
        <div class="kpi-box" style="border-left: 5px solid #9966ff;">
            <div class="kpi-main">
                <h4><i class="fa-solid fa-user-group"></i> <?php echo $compare_user_name; ?>'s Net Sales</h4>
                <h2>$<?php echo number_format($total_comp_net, 2); ?></h2>
            </div>
            <div class="kpi-sub">
                <div style="color: #666;">Gross: $<?php echo number_format($total_comp_gross, 2); ?></div>
                <div style="color: #dc3545;">Returns: -$<?php echo number_format($total_comp_ret, 2); ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="grid-layout desktop-2" style="margin-bottom: 30px;">
        
        <div class="glass-panel printable">
            <h3 style="margin-top:0; border-bottom: 1px solid #eee; padding-bottom:10px;">📈 Net Sales Trend (<?php echo ucfirst($interval); ?>)</h3>
            <div style="position: relative; height: 350px; width: 100%;">
                <canvas id="netTrendChart"></canvas>
            </div>
        </div>

        <div class="glass-panel printable">
            <h3 style="margin-top:0; border-bottom: 1px solid #eee; padding-bottom:10px;">📊 Breakdown: Gross vs Returns</h3>
            <div style="position: relative; height: 350px; width: 100%;">
                <canvas id="breakdownChart"></canvas>
            </div>
        </div>
    </div>

    <div class="glass-panel printable">
        <h3 style="margin-top:0; border-bottom: 1px solid #eee; padding-bottom:10px;"><i class="fa-solid fa-table"></i> Period-by-Period Summary</h3>
        <div class="table-responsive">
            <table class="table-simple" style="width: 100%; text-align: left;">
                <thead style="background: #f4f4f4;">
                    <tr>
                        <th>Period</th>
                        <th>My Gross</th>
                        <th style="color:#dc3545;">My Returns</th>
                        <th style="color:#28a745;">My Net</th>
                        <?php if (!empty($compare_user_id)): ?>
                            <th style="border-left: 2px solid #ddd;"><?php echo $compare_user_name; ?> Gross</th>
                            <th style="color:#dc3545;"><?php echo $compare_user_name; ?> Returns</th>
                            <th style="color:#9966ff;"><?php echo $compare_user_name; ?> Net</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($chart_labels as $index => $label): ?>
                        <tr>
                            <td><strong><?php echo $label; ?></strong></td>
                            <td>$<?php echo number_format($my_gross_arr[$index], 2); ?></td>
                            <td style="color:#dc3545;">-$<?php echo number_format($my_ret_arr[$index], 2); ?></td>
                            <td style="color:#28a745; font-weight:bold;">$<?php echo number_format($my_net_arr[$index], 2); ?></td>
                            
                            <?php if (!empty($compare_user_id)): ?>
                                <td style="border-left: 2px solid #ddd;">$<?php echo number_format($comp_gross_arr[$index], 2); ?></td>
                                <td style="color:#dc3545;">-$<?php echo number_format($comp_ret_arr[$index], 2); ?></td>
                                <td style="color:#9966ff; font-weight:bold;">$<?php echo number_format($comp_net_arr[$index], 2); ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>


    <div class="box" style="display:flex; justify-content:center; align-items:center; margin:30px auto; box-shadow: 0 15px 35px rgba(0,0,0,0.1); border-radius: 12px; padding: 20px; background-color: #fff;">
      <p id="latitude"><span id="latitude-val"></span></p>
      <p id="longitude"><span id="longitude-val"></span></p>
      <p id="address" style="font-weight:bold; color:#333;">
        <span id="address-val"></span>
      </p>
    </div>
  </div>

  <script>
    function getLocation() {
      if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(async function (position) {
          let latitude = position.coords.latitude;
          let longitude = position.coords.longitude;

          document.getElementById("latitude-val").textContent = "Latitude: " + latitude.toFixed(6);
          document.getElementById("longitude-val").textContent = "Longitude: " + longitude.toFixed(6);

          try {
            // Call BigDataCloud Reverse Geocoding API
            const response = await fetch(
              `https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=${latitude}&longitude=${longitude}&localityLanguage=en`
            );
            const data = await response.json();

            // Build a clean address string
            const parts = [
              data.locality || data.city || "",
              data.principalSubdivision || "",
              data.countryName || ""
            ].filter(Boolean);

            document.getElementById("address-val").textContent =
              "Address: " + (parts.length ? parts.join(", ") : "Unknown location");
          } catch (err) {
            document.getElementById("address-val").textContent = "Error fetching address.";
          }
        }, function () {
          alert("Please allow LOCATION permission.");
        });
      } else {
        document.getElementById("address-val").textContent = "Geolocation not supported.";
      }
    }

    getLocation();
  </script>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Parse PHP Data to JS
const labels = <?php echo json_encode($chart_labels); ?>;
const myNet = <?php echo json_encode($my_net_arr); ?>;
const myGross = <?php echo json_encode($my_gross_arr); ?>;
const myRet = <?php echo json_encode($my_ret_arr); ?>;

const isComparing = <?php echo !empty($compare_user_id) ? 'true' : 'false'; ?>;
const compName = "<?php echo $compare_user_name; ?>";
const compNet = <?php echo json_encode($comp_net_arr); ?>;
const compGross = <?php echo json_encode($comp_gross_arr); ?>;
const compRet = <?php echo json_encode($comp_ret_arr); ?>;

// 1. LINE CHART: Net Sales Trend
const trendCtx = document.getElementById('netTrendChart').getContext('2d');
const trendDatasets = [{
    label: 'My Net Sales ($)',
    data: myNet,
    borderColor: '#28a745',
    backgroundColor: 'rgba(40, 167, 69, 0.1)',
    borderWidth: 3,
    fill: true,
    tension: 0.3,
    pointBackgroundColor: '#28a745',
    pointRadius: 4
}];

if (isComparing) {
    trendDatasets.push({
        label: compName + "'s Net Sales ($)",
        data: compNet,
        borderColor: '#9966ff',
        backgroundColor: 'rgba(153, 102, 255, 0.1)',
        borderWidth: 3,
        fill: true,
        tension: 0.3,
        pointBackgroundColor: '#9966ff',
        pointRadius: 4
    });
}

new Chart(trendCtx, {
    type: 'line',
    data: { labels: labels, datasets: trendDatasets },
    options: {
        responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        scales: { y: { beginAtZero: true } }
    }
});

// 2. BAR CHART: Gross vs Returns Breakdown
const breakdownCtx = document.getElementById('breakdownChart').getContext('2d');
const barDatasets = [
    { label: 'My Gross', data: myGross, backgroundColor: 'rgba(54, 162, 235, 0.8)' },
    { label: 'My Returns', data: myRet, backgroundColor: 'rgba(220, 53, 69, 0.8)' }
];

if (isComparing) {
    barDatasets.push({ label: compName + ' Gross', data: compGross, backgroundColor: 'rgba(153, 102, 255, 0.8)' });
    barDatasets.push({ label: compName + ' Returns', data: compRet, backgroundColor: 'rgba(255, 159, 64, 0.8)' });
}

new Chart(breakdownCtx, {
    type: 'bar',
    data: { labels: labels, datasets: barDatasets },
    options: {
        responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        scales: { y: { beginAtZero: true } }
    }
});
</script>

<?php include 'footer.php'; ?>



