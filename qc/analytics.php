<?php
include_once 'header.php';
?>

<main class="printable">
<?php
$sql = "SELECT 
    SUM(shop_total_qty) as total_shop_qty,
    SUM(received_total_qty) as total_received_qty,
    SUM(actual_total_qty) as total_actual_qty,
    SUM(shop_total_amount) as total_shop_amount,
    SUM(received_total_amount) as total_received_amount,
    SUM(actual_total_amount) as total_actual_amount,
    COUNT(*) as total_records,
    SUM(CASE WHEN shop_type = 'TP' THEN 1 ELSE 0 END) as total_tp_records,
    SUM(CASE WHEN shop_type = 'DP' THEN 1 ELSE 0 END) as total_dp_records
    FROM damage_details where status = 1";
$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);
?>


  <style>
   
    .card {
      background: #fff;
      border-radius: 10px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      padding: 2rem;
      max-width: 1200px;
      margin: 0 auto 2rem;
    }

    .card h2 {
      margin-bottom: 2rem;
      font-size: 1.8rem;
      color: #007bff;
      border-bottom: 1px solid #dee2e6;
      padding-bottom: 0.5rem;
    }

    .summary-row {
      display: flex;
      justify-content: space-between;
      gap: 1.5rem;
      flex-wrap: wrap;
    }

    .stats-box {
      flex: 1 1 30%;
      background: #f8f9fa;
      border: 1px solid #dee2e6;
      border-radius: 8px;
      padding: 1.5rem;
      transition: all 0.3s ease;
    }

    .stats-box:hover {
      background-color: #ffffff;
      transform: translateY(-4px);
      box-shadow: 0 0.5rem 1.2rem rgba(0,0,0,0.15);
    }

    .stats-box h5 {
      color: #6c757d;
      font-size: 1.2rem;
      margin-bottom: 1rem;
      border-bottom: 2px solid #e9ecef;
      padding-bottom: 0.5rem;
    }

    .stat-line {
      display: flex;
      justify-content: space-between;
      margin-bottom: 0.8rem;
    }

    .stat-line span {
      color: #555;
    }

    .stat-line strong {
      color: #000;
    }

    @media (max-width: 768px) {
      .stats-box {
        flex: 1 1 100%;
      }
    }
  </style>

  <div class="card">
    <h2>Summary</h2>
    <div class="summary-row">
      <div class="stats-box">
        <h5>Confirmed</h5>
        <div class="stat-line"><span>Total:</span><strong><?php echo $row['total_records']; ?></strong></div>
        <div class="stat-line"><span>TP Records:</span><strong><?php echo $row['total_tp_records']; ?></strong></div>
        <div class="stat-line"><span>DP Records:</span><strong><?php echo $row['total_dp_records']; ?></strong></div>
      </div>
      <div class="stats-box">
        <h5>Quantities</h5>
        <div class="stat-line"><span>Shop:</span><strong><?php echo number_format($row['total_shop_qty']); ?></strong></div>
        <div class="stat-line"><span>Received:</span><strong><?php echo number_format($row['total_received_qty']); ?></strong></div>
        <div class="stat-line"><span>Actual:</span><strong><?php echo number_format($row['total_actual_qty']); ?></strong></div>
      </div>
      <div class="stats-box">
        <h5>Amounts</h5>
        <div class="stat-line"><span>Shop:</span><strong>$<?php echo number_format($row['total_shop_amount'], 2); ?></strong></div>
        <div class="stat-line"><span>Received:</span><strong>$<?php echo number_format($row['total_received_amount'], 2); ?></strong></div>
        <div class="stat-line"><span>Actual:</span><strong>$<?php echo number_format($row['total_actual_amount'], 2); ?></strong></div>
      </div>
    </div>
  </div>

<div class="card">
    <h2>Data Visualization</h2>
    <div style="display: flex; justify-content: space-between; gap: 20px;">
        <div style="flex: 1;">
            <canvas id="recordsChart"></canvas>
        </div>
        <div style="flex: 1;">
            <canvas id="quantityChart"></canvas>
        </div>
        <div style="flex: 1;">
            <canvas id="amountChart"></canvas>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Records Chart
new Chart(document.getElementById('recordsChart'), {
    type: 'pie',
    data: {
        labels: ['TP Records', 'DP Records'],
        datasets: [{
            data: [<?php echo $row['total_tp_records']; ?>, <?php echo $row['total_dp_records']; ?>],
            backgroundColor: ['#007bff', '#28a745']
        }]
    },
    options: {
        responsive: true,
        plugins: { title: { display: true, text: 'Record Distribution' } }
    }
});

// Quantities Chart
new Chart(document.getElementById('quantityChart'), {
    type: 'bar',
    data: {
        labels: ['Shop', 'Received', 'Actual'],
        datasets: [{
            label: 'Quantities',
            data: [<?php echo $row['total_shop_qty']; ?>, <?php echo $row['total_received_qty']; ?>, <?php echo $row['total_actual_qty']; ?>],
            backgroundColor: '#17a2b8'
        }]
    },
    options: {
        responsive: true,
        plugins: { title: { display: true, text: 'Quantity Comparison' } }
    }
});

// Amounts Chart
new Chart(document.getElementById('amountChart'), {
    type: 'line',
    data: {
        labels: ['Shop', 'Received', 'Actual'],
        datasets: [{
            label: 'Amounts ($)',
            data: [<?php echo $row['total_shop_amount']; ?>, <?php echo $row['total_received_amount']; ?>, <?php echo $row['total_actual_amount']; ?>],
            borderColor: '#dc3545',
            fill: false
        }]
    },
    options: {
        responsive: true,
        plugins: { title: { display: true, text: 'Amount Trends' } }
    }
});
</script>
</main>

<?php
include_once 'footer.php';
?>