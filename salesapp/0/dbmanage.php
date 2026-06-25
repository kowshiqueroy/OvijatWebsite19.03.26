<?php
$pageTitle = 'DB Manager';
include 'header.php';

/* Build a PDO connection using the same credentials from config.php */
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('<div class="alert alert-error">PDO connection failed: ' . htmlspecialchars($e->getMessage()) . '</div>');
}
// -------------------------------------------------------------------------
// 0. AJAX API (For Query Builder & Exports)
// -------------------------------------------------------------------------
if (isset($_GET['api_action'])) {
    
    // --- 0a. Get Columns (JSON) ---
    if ($_GET['api_action'] === 'get_columns' && isset($_GET['table'])) {
        if (ob_get_length()) ob_clean(); 
        $tbl = $_GET['table'];
        $cols = [];
        if (!empty($pdo) && !empty($tbl)) {
            try {
                $safeTable = str_replace("`", "``", $tbl); 
                $stmt = $pdo->prepare("DESCRIBE `$safeTable`");
                $stmt->execute();
                $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } catch (Exception $e) {}
        }
        header('Content-Type: application/json');
        echo json_encode($cols);
        exit;
    }

    // --- 0b. Export Full Database (.sql) ---
    if ($_GET['api_action'] === 'export_db') {
        if (ob_get_length()) ob_clean();
        
        $filename = 'db_backup_' . date('Y-m-d_H-i-s') . '.sql';
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        if (!empty($pdo)) {
            // Get Tables
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            
            echo "-- SQL Dump\n";
            echo "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
            echo "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
            echo "SET time_zone = \"+00:00\";\n\n";

            foreach ($tables as $table) {
                // Drop
                echo "DROP TABLE IF EXISTS `$table`;\n";
                
                // Structure
                $createRow = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
                echo $createRow[1] . ";\n\n";
                
                // Data
                $rows = $pdo->query("SELECT * FROM `$table`");
                while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
                    $keys = array_map(function($k) { return "`$k`"; }, array_keys($row));
                    $vals = array_map(function($v) use ($pdo) {
                        return $v === null ? "NULL" : $pdo->quote($v);
                    }, array_values($row));
                    
                    echo "INSERT INTO `$table` (" . implode(", ", $keys) . ") VALUES (" . implode(", ", $vals) . ");\n";
                }
                echo "\n";
            }
        }
        exit;
    }
}

// -------------------------------------------------------------------------
// 1. DATABASE CONNECTION CHECK
// -------------------------------------------------------------------------
if (!isset($pdo)) {
    echo "<div class='alert alert-danger'>Error: \$pdo variable not found in header.php.</div>";
    exit;
}

$message = "";
$messageType = "";
$results = null;
$headers = [];
$currentQuery = "";
$detectedTable = "";
$primaryKey = "";

// -------------------------------------------------------------------------
// 2. QUERY EXECUTION LOGIC
// -------------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $currentQuery = trim($_POST['sql_query']);
    
    if (!empty($currentQuery)) {
        try {
            $stmt = $pdo->prepare($currentQuery);
            $stmt->execute();

            // Detect Query Type
            if (preg_match('/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN)/i', $currentQuery)) {
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // --- SMART TABLE & PK DETECTION ---
                // Try to find which table we are querying to enable Row Actions
                if (preg_match('/FROM\s+[`]?([a-zA-Z0-9_]+)[`]?/i', $currentQuery, $matches)) {
                    $detectedTable = $matches[1];
                    try {
                        // Find PK
                        $pkStmt = $pdo->query("SHOW KEYS FROM `$detectedTable` WHERE Key_name = 'PRIMARY'");
                        $pkRow = $pkStmt->fetch(PDO::FETCH_ASSOC);
                        if ($pkRow) {
                            $primaryKey = $pkRow['Column_name'];
                        }
                    } catch (Exception $e) {}
                }
                // ----------------------------------

                if (count($results) > 0) {
                    $headers = array_keys($results[0]);
                    $message = "Query executed successfully. " . count($results) . " rows returned.";
                    $messageType = "success";
                } else {
                    $message = "Query executed successfully. 0 results returned.";
                    $messageType = "warning";
                }
            } else {
                $message = "Query executed successfully. Affected rows: " . $stmt->rowCount();
                $messageType = "success";
            }
        } catch (PDOException $e) {
            $message = "SQL Error: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// -------------------------------------------------------------------------
// 3. GET LIST OF TABLES
// -------------------------------------------------------------------------
$tables = [];
try {
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}
?>

<!-- ----------------------------------------------------------------------- -->
<!-- STYLES -->
<!-- ----------------------------------------------------------------------- -->
<style>
    :root {
        --bg-dark: #2d3748;
        --bg-light: #f7fafc;
        --accent: #4299e1;
        --border: #e2e8f0;
        --danger: #e53e3e;
        --success: #48bb78;
        --warning: #ed8936;
    }
    
    .db-manager-container {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        max-width: 1400px;
        margin: 0 auto;
        padding: 20px;
        font-family: -apple-system, system-ui, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }

    /* Sidebar */
    .db-sidebar {
        flex: 1;
        min-width: 280px;
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 15px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        max-height: 85vh;
        overflow-y: auto;
    }
    .db-sidebar h3 { margin-top: 0; font-size: 1.1rem; border-bottom: 2px solid var(--border); padding-bottom: 10px; color: #2d3748; }
    .table-list { list-style: none; padding: 0; }
    .table-list li { 
        padding: 8px; 
        border-bottom: 1px solid #eee; 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
    }
    .table-list li:hover { background-color: var(--bg-light); }
    .table-name { cursor: pointer; color: var(--accent); font-weight: 600; flex-grow: 1; }
    
    .btn-xs { font-size: 0.75rem; padding: 2px 8px; border-radius: 4px; border: 1px solid var(--border); background: #fff; cursor: pointer; transition: 0.2s; color: #4a5568; }
    .btn-xs:hover { background: var(--bg-light); border-color: #cbd5e0; }
    
    /* Sidebar Header Area */
    .sidebar-header { margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--border); padding-bottom: 10px; }
    .btn-export-db { background-color: var(--success); color: white; border: none; padding: 4px 10px; border-radius: 4px; font-size: 0.75rem; cursor: pointer; text-decoration: none; display: inline-block; }
    .btn-export-db:hover { background-color: #38a169; }

    /* Dropdown for Table Actions */
    .table-actions { position: relative; display: inline-block; margin-left: 5px; }
    .dropdown-content {
        display: none;
        position: absolute;
        right: 0;
        background-color: #fff;
        min-width: 140px;
        box-shadow: 0 8px 16px rgba(0,0,0,0.2);
        z-index: 10;
        border: 1px solid var(--border);
        border-radius: 4px;
    }
    .table-actions:hover .dropdown-content { display: block; }
    .dropdown-content a {
        color: #2d3748;
        padding: 8px 12px;
        text-decoration: none;
        display: block;
        font-size: 0.8rem;
        cursor: pointer;
    }
    .dropdown-content a:hover { background-color: var(--bg-light); color: var(--accent); }
    .text-danger { color: var(--danger) !important; }

    /* Main Content */
    .db-main { flex: 3; min-width: 300px; }

    /* Query Builder & Editor */
    .builder-box {
        background: #fff;
        border-radius: 8px;
        margin-bottom: 20px;
        border: 1px solid #cbd5e0;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        overflow: hidden;
    }
    .builder-title { 
        background: #edf2f7; 
        padding: 12px 15px; 
        font-weight: bold; 
        color: #4a5568; 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        cursor: pointer; 
        border-bottom: 1px solid #cbd5e0;
    }
    .builder-content { padding: 20px; display: block; }
    
    .builder-section { margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px dashed #e2e8f0; }
    .builder-section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
    .section-label { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; color: #718096; font-weight: 700; margin-bottom: 10px; display: block; }

    .control-row { display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 10px; align-items: flex-end; }
    .form-group { flex: 1; min-width: 150px; }
    .form-group label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 5px; color: #4a5568; }
    .form-control { width: 100%; padding: 8px 12px; border-radius: 4px; border: 1px solid #cbd5e0; font-size: 0.9rem; background: #fff; }

    .dynamic-row { display: flex; gap: 8px; align-items: center; margin-bottom: 8px; background: #f8fafc; padding: 8px; border-radius: 4px; border: 1px solid #edf2f7; }
    .dynamic-row select, .dynamic-row input { padding: 6px; border: 1px solid #cbd5e0; border-radius: 4px; font-size: 0.9rem; }
    .btn-remove { color: var(--danger); background: none; border: none; font-weight: bold; cursor: pointer; font-size: 1.2rem; line-height: 1; padding: 0 5px; }
    .btn-add { background: #edf2f7; color: #4a5568; border: 1px solid #cbd5e0; padding: 5px 10px; border-radius: 4px; font-size: 0.8rem; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; }

    #builder-columns-container { padding: 10px; background: #f8fafc; border: 1px solid #edf2f7; border-radius: 4px; max-height: 300px; overflow-y: auto; }
    .columns-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px; }
    .col-check-wrapper { display: flex; align-items: center; gap: 8px; font-size: 0.9rem; background: white; padding: 5px 8px; border: 1px solid #eee; border-radius: 4px; }
    .col-input-wrapper { display: flex; flex-direction: column; gap: 4px; }
    .col-input-wrapper label { font-size: 0.8rem; font-weight: 600; }
    
    .btn-gen { background-color: var(--accent); color: white; border: none; padding: 10px 25px; border-radius: 6px; font-weight: bold; cursor: pointer; box-shadow: 0 2px 4px rgba(66, 153, 225, 0.4); width: 100%; margin-top: 10px; }
    .btn-gen:hover { background-color: #3182ce; }

    .query-box { background: #fff; padding: 15px; border-radius: 8px; border: 1px solid var(--border); box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 20px; }
    textarea.sql-editor { width: 100%; height: 150px; background: #282c34; color: #abb2bf; font-family: 'Courier New', Courier, monospace; padding: 15px; border-radius: 4px; border: 1px solid #444; font-size: 14px; resize: vertical; margin-top: 5px; }
    .action-bar { margin-top: 10px; display: flex; justify-content: space-between; align-items: center; }
    .btn-run { background-color: var(--success); color: white; border: none; padding: 10px 25px; border-radius: 4px; font-weight: bold; cursor: pointer; }
    .btn-clear { background-color: var(--danger); color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; }

    /* Results */
    .results-area { overflow-x: auto; background: white; border-radius: 8px; border: 1px solid var(--border); margin-top: 20px; }
    .results-header { display: flex; justify-content: space-between; align-items: center; padding: 10px 15px; background: #f7fafc; border-bottom: 1px solid var(--border); }
    .sql-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
    .sql-table th { background-color: var(--bg-dark); color: white; text-align: left; padding: 12px; white-space: nowrap; }
    .sql-table td { padding: 10px; border-bottom: 1px solid var(--border); color: #4a5568; white-space: nowrap; max-width: 300px; overflow: hidden; text-overflow: ellipsis; }
    .sql-table tr:hover { background-color: var(--bg-light); }
    
    /* Row Actions */
    .row-action-btn { 
        border: none; background: none; cursor: pointer; font-size: 1.1rem; padding: 0 4px; opacity: 0.7; transition: 0.2s; 
    }
    .row-action-btn:hover { opacity: 1; transform: scale(1.2); }
    
    .msg-box { padding: 15px; margin-bottom: 20px; border-radius: 4px; font-weight: 500; }
    .msg-success { background-color: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
    .msg-error { background-color: #fed7d7; color: #742a2a; border: 1px solid #feb2b2; }
    .msg-warning { background-color: #feebc8; color: #744210; border: 1px solid #fbd38d; }
</style>

<!-- ----------------------------------------------------------------------- -->
<!-- HTML UI -->
<!-- ----------------------------------------------------------------------- -->

<div class="print-header">
    <h1>Ovijat EIS Report</h1>
    <p>Generated by: @<?php echo isset($_SESSION['username']) ? $_SESSION['username'] : 'Guest'; ?> | Date: <?php echo date("Y-m-d"); ?></p>
</div>

<div class="db-manager-container">

    <!-- SIDEBAR -->
    <div class="db-sidebar">
        <div class="sidebar-header">
            <h3 style="margin:0; border:none; padding:0;">Tables (<?php echo count($tables); ?>)</h3>
            <a href="?api_action=export_db" target="_blank" class="btn-export-db">💾 Export DB</a>
        </div>
        
        <ul class="table-list">
            <?php foreach($tables as $tbl): ?>
                <li>
                    <span class="table-name" onclick="insertText('<?php echo $tbl; ?>')"><?php echo $tbl; ?></span>
                    
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <button class="btn-xs" onclick="quickBrowse('<?php echo $tbl; ?>')">Browse</button>
                        
                        <!-- Table Actions Dropdown -->
                        <div class="table-actions">
                            <button class="btn-xs">Manage ▼</button>
                            <div class="dropdown-content">
                                <a onclick="genTableDuplicate('<?php echo $tbl; ?>')">📄 Duplicate Table</a>
                                <a onclick="genTableTruncate('<?php echo $tbl; ?>')" class="text-danger">🧹 Empty (Truncate)</a>
                                <a onclick="genTableDrop('<?php echo $tbl; ?>')" class="text-danger">🗑️ Drop Table</a>
                            </div>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <div style="margin-top: 20px; font-size: 0.8rem; color: #888; padding: 10px; background: #f7fafc; border-radius: 4px;">
            <strong>Tip:</strong> Click "Export DB" to backup everything. Row icons edit data.
        </div>
    </div>

    <!-- MAIN -->
    <div class="db-main">

        <!-- NOTIFICATIONS -->
        <?php if (!empty($message)): ?>
            <div class="msg-box msg-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- VISUAL QUERY BUILDER -->
        <div class="builder-box">
            <div class="builder-title" onclick="toggleBuilder()">
                <span>⚡ Visual Query Builder</span>
                <small id="builder-toggle-icon">▼</small>
            </div>
            
            <div id="builderContent" class="builder-content">
                
                <!-- Section 1: Setup -->
                <div class="builder-section">
                    <span class="section-label">1. Basic Setup</span>
                    <div class="control-row">
                        <div class="form-group">
                            <label>Action</label>
                            <select id="buildAction" class="form-control" onchange="renderUI()">
                                <option value="SELECT">SELECT (Read)</option>
                                <option value="INSERT">INSERT (Create)</option>
                                <option value="UPDATE">UPDATE (Edit)</option>
                                <option value="DELETE">DELETE (Remove)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Table</label>
                            <select id="buildTable" class="form-control" onchange="fetchColumns()">
                                <option value="">-- Select Table --</option>
                                <?php foreach($tables as $tbl): ?>
                                    <option value="<?php echo $tbl; ?>"><?php echo $tbl; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Columns -->
                <div class="builder-section" id="section-columns">
                    <span class="section-label">2. Columns & Values</span>
                    <div id="builder-columns-container">
                        <em style="color:#888;">Select a table above to load columns...</em>
                    </div>
                </div>

                <!-- Section 3: Conditions -->
                <div class="builder-section" id="section-where">
                    <span class="section-label">3. Filters (WHERE)</span>
                    <div id="where-container"></div>
                    <button type="button" class="btn-add" onclick="addWhereRow()">+ Add Condition</button>
                </div>

                <!-- Section 4: Sorting & Limits -->
                <div class="builder-section" id="section-options" style="display:flex; gap: 30px; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 250px;">
                        <span class="section-label">4. Sorting (ORDER BY)</span>
                        <div id="order-container"></div>
                        <button type="button" class="btn-add" onclick="addOrderRow()">+ Add Sort</button>
                    </div>
                    <div style="flex: 1; min-width: 250px;">
                        <span class="section-label">5. Grouping (GROUP BY)</span>
                        <div id="group-container"></div>
                        <button type="button" class="btn-add" onclick="addGroupRow()">+ Add Group</button>
                    </div>
                    <div style="width: 150px;">
                        <span class="section-label">Limit</span>
                        <input type="number" id="queryLimit" class="form-control" placeholder="e.g. 50">
                    </div>
                </div>

                <button type="button" class="btn-gen" onclick="generateSQL()">Generate SQL Query</button>

            </div>
        </div>

        <!-- SQL EDITOR -->
        <div class="query-box">
            <form method="POST" id="sqlForm">
                <?= csrf_field() ?>
                <label style="font-weight: bold; display: block;">SQL Query Editor:</label>
                <textarea name="sql_query" id="sqlInput" class="sql-editor" placeholder="Generated SQL will appear here..."><?php echo htmlspecialchars($currentQuery); ?></textarea>
                
                <div class="action-bar">
                    <button type="button" class="btn-clear" onclick="document.getElementById('sqlInput').value = '';">Clear</button>
                    <div>
                        <small style="margin-right: 10px; color: #666;">Ctrl + Enter to Run</small>
                        <button type="submit" class="btn-run">Execute Query</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- RESULTS DISPLAY -->
        <?php if ($results): ?>
        <div class="results-area">
            
            <!-- RESULTS HEADER & CLIENT EXPORT -->
            <div class="results-header">
                <strong style="color:#4a5568;">Query Results</strong>
                <div style="display:flex; gap: 10px;">
                    <button class="btn-xs" onclick="genViewCSV()">⬇️ Export CSV</button>
                    <button class="btn-xs" onclick="genViewSQL()">⬇️ Export SQL</button>
                </div>
            </div>

            <table class="sql-table" id="resultsTable">
                <thead>
                    <tr>
                        <!-- ACTIONS COLUMN (Only if PK detected) -->
                        <?php if($detectedTable && $primaryKey && in_array($primaryKey, $headers)): ?>
                            <th width="80">Actions</th>
                        <?php endif; ?>

                        <?php foreach ($headers as $header): ?>
                            <th><?php echo htmlspecialchars($header); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $row): ?>
                        <tr>
                            <!-- ACTION BUTTONS -->
                            <?php if($detectedTable && $primaryKey && isset($row[$primaryKey])): ?>
                            <td>
                                <?php 
                                    $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                                ?>
                                <button class="row-action-btn" title="Edit Row" onclick="genRowUpdate(<?php echo $rowJson; ?>, '<?php echo $detectedTable; ?>', '<?php echo $primaryKey; ?>')">✏️</button>
                                <button class="row-action-btn" title="Duplicate Row" onclick="genRowClone(<?php echo $rowJson; ?>, '<?php echo $detectedTable; ?>')">📄</button>
                                <button class="row-action-btn" style="color:var(--danger)" title="Delete Row" onclick="genRowDelete('<?php echo $row[$primaryKey]; ?>', '<?php echo $detectedTable; ?>', '<?php echo $primaryKey; ?>')">🗑️</button>
                            </td>
                            <?php endif; ?>

                            <?php foreach ($row as $cell): ?>
                                <td title="<?php echo htmlspecialchars($cell ?? 'NULL'); ?>">
                                    <?php echo htmlspecialchars($cell ?? 'NULL'); ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- ----------------------------------------------------------------------- -->
<!-- JAVASCRIPT LOGIC -->
<!-- ----------------------------------------------------------------------- -->
<script>
    let currentColumns = [];
    let columnOptionsHTML = "";

    function toggleBuilder() {
        const content = document.getElementById('builderContent');
        const display = content.style.display === 'none' ? 'block' : 'none';
        content.style.display = display;
    }

    // --- HELPER: Quote values for SQL ---
    function sqlVal(val) {
        if (val === null) return 'NULL';
        if (typeof val === 'number') return val;
        return "'" + String(val).replace(/'/g, "''") + "'";
    }
    
    // --- EXPORT CURRENT VIEW (Client Side) ---
    function genViewCSV() {
        var csvContent = "sep=,\n";
        var rows = document.querySelectorAll("#resultsTable tr");
        rows.forEach(function(row){
            var cols = row.querySelectorAll("td, th");
            var rowContent = "";
            var skipFirst = row.querySelector("th") && cols[0].textContent === 'Actions' ? true : false;
            
            // Skip action column if present
            var startIndex = (cols.length > 0 && cols[0].innerHTML.includes('row-action-btn')) || (cols[0].textContent === 'Actions') ? 1 : 0;

            for (var i = startIndex; i < cols.length; i++) {
                rowContent += '"' + cols[i].innerText.replace(/"/g, '""') + '",';
            }
            csvContent += rowContent.slice(0, -1) + "\n";
        });
        
        downloadFile(csvContent, 'data.csv', 'text/csv');
    }

    function genViewSQL() {
        var sqlContent = "";
        var tableName = "exported_table"; 
        // Try to guess table name from active selection if possible, otherwise generic
        var activeTable = document.querySelector('.table-name[style*="font-weight: bold"]'); // Simple heuristic
        if(activeTable) tableName = activeTable.textContent;

        var rows = document.querySelectorAll("#resultsTable tbody tr");
        rows.forEach(function(row){
            var cols = row.querySelectorAll("td");
            var rowVals = [];
            
            // Skip action column
            var startIndex = (cols.length > 0 && cols[0].innerHTML.includes('row-action-btn')) ? 1 : 0;

            for (var i = startIndex; i < cols.length; i++) {
                var txt = cols[i].innerText;
                rowVals.push(txt === 'NULL' ? 'NULL' : "'" + txt.replace(/'/g, "''") + "'");
            }
            sqlContent += "INSERT INTO `" + tableName + "` VALUES (" + rowVals.join(", ") + ");\n";
        });
        
        downloadFile(sqlContent, 'data.sql', 'text/plain');
    }

    function downloadFile(content, fileName, mimeType) {
        var link = document.createElement("a");
        link.setAttribute("href", "data:" + mimeType + ";charset=utf-8," + encodeURIComponent(content));
        link.setAttribute("download", fileName);
        link.style.display = "none";
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    // --- ROW ACTIONS (Results) ---

    function genRowUpdate(row, table, pkCol) {
        let sets = [];
        for (const [key, val] of Object.entries(row)) {
            if (key !== pkCol) { 
                sets.push(`\`${key}\` = ${sqlVal(val)}`);
            }
        }
        const pkVal = sqlVal(row[pkCol]);
        const sql = `UPDATE \`${table}\` \nSET ${sets.join(", ")} \nWHERE \`${pkCol}\` = ${pkVal};`;
        setEditor(sql);
    }

    function genRowClone(row, table) {
        let cols = [];
        let vals = [];
        for (const [key, val] of Object.entries(row)) {
             cols.push(`\`${key}\``);
             vals.push(sqlVal(val));
        }
        const sql = `INSERT INTO \`${table}\` \n(${cols.join(", ")}) \nVALUES (${vals.join(", ")});`;
        setEditor(sql);
    }

    function genRowDelete(pkVal, table, pkCol) {
        const safeVal = isNaN(pkVal) ? `'${pkVal}'` : pkVal;
        const sql = `DELETE FROM \`${table}\` WHERE \`${pkCol}\` = ${safeVal};`;
        setEditor(sql);
    }

    // --- TABLE ACTIONS (Sidebar) ---

    function genTableTruncate(table) {
        if(!confirm("Generate TRUNCATE query for " + table + "?")) return;
        setEditor(`TRUNCATE TABLE \`${table}\`;`);
    }

    function genTableDrop(table) {
        if(!confirm("WARNING: Generate DROP query for " + table + "?")) return;
        setEditor(`DROP TABLE \`${table}\`;`);
    }

    function genTableDuplicate(table) {
        const newName = prompt("Name for the new table copy:", table + "_copy");
        if(newName) {
            setEditor(`CREATE TABLE \`${newName}\` LIKE \`${table}\`;\nINSERT INTO \`${newName}\` SELECT * FROM \`${table}\`;`);
        }
    }

    function setEditor(sql) {
        const editor = document.getElementById('sqlInput');
        editor.value = sql;
        editor.scrollIntoView({behavior: "smooth"});
        editor.style.backgroundColor = "#e6fffa";
        setTimeout(() => editor.style.backgroundColor = "#282c34", 300);
    }

    // --- EXISTING QUERY BUILDER LOGIC ---

    function fetchColumns() {
        const table = document.getElementById('buildTable').value;
        const container = document.getElementById('builder-columns-container');
        
        if (!table) {
            container.innerHTML = '<em style="color:#888;">Select a table...</em>';
            currentColumns = [];
            return;
        }
        container.innerHTML = '<em style="color:#666;">Loading...</em>';

        fetch('?api_action=get_columns&table=' + table)
            .then(response => response.json())
            .then(data => {
                currentColumns = data;
                columnOptionsHTML = data.map(c => `<option value="${c}">${c}</option>`).join('');
                renderUI();
                document.getElementById('where-container').innerHTML = '';
                document.getElementById('order-container').innerHTML = '';
                document.getElementById('group-container').innerHTML = '';
            })
            .catch(err => {
                console.error(err);
                container.innerHTML = '<em style="color:red;">Error loading columns.</em>';
            });
    }

    function renderUI() {
        const action = document.getElementById('buildAction').value;
        const container = document.getElementById('builder-columns-container');
        const sectionOptions = document.getElementById('section-options');
        
        if (action === 'INSERT' || action === 'DELETE') sectionOptions.style.display = 'none';
        else sectionOptions.style.display = 'flex';

        if (currentColumns.length === 0) {
            if (document.getElementById('buildTable').value) container.innerHTML = '<em>No columns loaded.</em>';
            return;
        }

        let html = '';
        if (action === 'DELETE') {
            container.innerHTML = '<em style="color:#4a5568;">Select rows to delete using the Filters section below.</em>';
            return;
        }
        html = '<div class="columns-grid">';
        
        if (action === 'SELECT') {
            html += `<div class="col-check-wrapper" style="background:#edf2f7; font-weight:bold;">
                        <input type="checkbox" id="col_all" onchange="toggleAll(this)" checked> 
                        <label for="col_all">Select All (*)</label>
                     </div>`;
            currentColumns.forEach(col => {
                html += `<div class="col-check-wrapper">
                            <input type="checkbox" class="col-chk" value="${col}" id="chk_${col}"> 
                            <label for="chk_${col}">${col}</label>
                         </div>`;
            });
        } 
        else if (action === 'INSERT' || action === 'UPDATE') {
            currentColumns.forEach(col => {
                let placeholder = "Value";
                if(col.includes('id') && action === 'UPDATE') placeholder = "ID (Ref)";
                html += `<div class="col-input-wrapper">
                            <label>${col}</label>
                            <input type="text" class="col-input form-control" data-col="${col}" placeholder="${placeholder}">
                         </div>`;
            });
        }
        html += '</div>';
        container.innerHTML = html;
    }

    function toggleAll(source) {
        document.querySelectorAll('.col-chk').forEach(cb => {
            cb.checked = !source.checked;
            cb.disabled = source.checked;
        });
    }

    function addWhereRow() {
        if(!columnOptionsHTML) { alert("Please select a table first."); return; }
        const div = document.createElement('div');
        div.className = 'dynamic-row where-row';
        const isFirst = document.getElementById('where-container').children.length === 0;
        const logicDisplay = isFirst ? 'display:none' : '';

        div.innerHTML = `
            <select class="where-logic" style="width:70px; ${logicDisplay}"><option value="AND">AND</option><option value="OR">OR</option></select>
            <select class="where-col" style="flex:2"><option value="">- Col -</option>${columnOptionsHTML}</select>
            <select class="where-op" style="flex:1">
                <option value="=">=</option><option value="LIKE">LIKE</option>
                <option value=">">&gt;</option><option value="<">&lt;</option>
                <option value="!=">!=</option><option value="IS NULL">IS NULL</option>
            </select>
            <input type="text" class="where-val" placeholder="Value" style="flex:2">
            <button type="button" class="btn-remove" onclick="this.parentElement.remove()">×</button>
        `;
        document.getElementById('where-container').appendChild(div);
    }

    function addOrderRow() {
        if(!columnOptionsHTML) { alert("Please select a table first."); return; }
        const div = document.createElement('div');
        div.className = 'dynamic-row order-row';
        div.innerHTML = `
            <select class="order-col" style="flex:2"><option value="">- Column -</option>${columnOptionsHTML}</select>
            <select class="order-dir" style="flex:1"><option value="ASC">ASC</option><option value="DESC">DESC</option></select>
            <button type="button" class="btn-remove" onclick="this.parentElement.remove()">×</button>
        `;
        document.getElementById('order-container').appendChild(div);
    }

    function addGroupRow() {
        if(!columnOptionsHTML) { alert("Please select a table first."); return; }
        const div = document.createElement('div');
        div.className = 'dynamic-row group-row';
        div.innerHTML = `
            <select class="group-col" style="flex:1"><option value="">- Column -</option>${columnOptionsHTML}</select>
            <button type="button" class="btn-remove" onclick="this.parentElement.remove()">×</button>
        `;
        document.getElementById('group-container').appendChild(div);
    }

    function generateSQL() {
        const action = document.getElementById('buildAction').value;
        const table = document.getElementById('buildTable').value;
        if (!table) { alert("Please select a table."); return; }

        let sql = "";
        let whereStr = "";
        
        const whereRows = document.querySelectorAll('.where-row');
        if (whereRows.length > 0) {
            let conditions = [];
            whereRows.forEach((row, index) => {
                const logic = row.querySelector('.where-logic').value;
                const col = row.querySelector('.where-col').value;
                const op = row.querySelector('.where-op').value;
                const val = row.querySelector('.where-val').value.trim();
                if (col) {
                    let cond = "";
                    if (op === "IS NULL") cond = `\`${col}\` IS NULL`;
                    else {
                        const safeVal = (isNaN(val) && op !== 'IN') ? `'${val}'` : val;
                        cond = `\`${col}\` ${op} ${safeVal}`;
                    }
                    if (index === 0) conditions.push(cond);
                    else conditions.push(`${logic} ${cond}`);
                }
            });
            if (conditions.length > 0) whereStr = " WHERE " + conditions.join(" ");
        }

        if (action === 'SELECT') {
            let cols = "*";
            const allChk = document.getElementById('col_all');
            if (!allChk || !allChk.checked) {
                const checked = Array.from(document.querySelectorAll('.col-chk:checked')).map(c => "`" + c.value + "`");
                if (checked.length > 0) cols = checked.join(", ");
            }
            sql = `SELECT ${cols} FROM \`${table}\`${whereStr}`;
            
            const groupRows = document.querySelectorAll('.group-row');
            if (groupRows.length > 0) {
                const groups = Array.from(groupRows).map(r => "`" + r.querySelector('.group-col').value + "`").filter(v => v !== "``");
                if(groups.length > 0) sql += " GROUP BY " + groups.join(", ");
            }
            const orderRows = document.querySelectorAll('.order-row');
            if (orderRows.length > 0) {
                const orders = Array.from(orderRows).map(r => {
                    const c = r.querySelector('.order-col').value;
                    const d = r.querySelector('.order-dir').value;
                    return c ? `\`${c}\` ${d}` : null;
                }).filter(v => v);
                if(orders.length > 0) sql += " ORDER BY " + orders.join(", ");
            }
            const lim = document.getElementById('queryLimit').value;
            if(lim) sql += ` LIMIT ${lim}`;
        }
        else if (action === 'INSERT') {
            const inputs = document.querySelectorAll('.col-input');
            let cols = []; let vals = [];
            inputs.forEach(input => {
                if (input.value.trim() !== "") {
                    cols.push("`" + input.dataset.col + "`");
                    vals.push(isNaN(input.value) ? `'${input.value}'` : input.value);
                }
            });
            if (cols.length === 0) { alert("Please fill at least one value."); return; }
            sql = `INSERT INTO \`${table}\` (${cols.join(", ")}) VALUES (${vals.join(", ")})`;
        }
        else if (action === 'UPDATE') {
            const inputs = document.querySelectorAll('.col-input');
            let sets = [];
            inputs.forEach(input => {
                if (input.value.trim() !== "") {
                    let safeVal = isNaN(input.value) ? `'${input.value}'` : input.value;
                    sets.push(`\`${input.dataset.col}\` = ${safeVal}`);
                }
            });
            if (sets.length === 0) { alert("Please fill at least one value."); return; }
            if (!whereStr && !confirm("Warning: No WHERE clause set. Update ALL rows?")) return;
            sql = `UPDATE \`${table}\` SET ${sets.join(", ")}${whereStr}`;
        }
        else if (action === 'DELETE') {
            if (!whereStr && !confirm("Warning: No WHERE clause set. Delete ALL rows?")) return;
            sql = `DELETE FROM \`${table}\`${whereStr}`;
        }
        setEditor(sql);
    }

    function insertText(text) {
        const textarea = document.getElementById('sqlInput');
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        textarea.value = textarea.value.substring(0, start) + " `" + text + "` " + textarea.value.substring(end);
        textarea.focus();
    }

    function quickBrowse(tableName) {
        const textarea = document.getElementById('sqlInput');
        textarea.value = "SELECT * FROM `" + tableName + "` LIMIT 50";
        document.getElementById('sqlForm').submit();
    }

    document.getElementById('sqlInput').addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.keyCode === 13) document.getElementById('sqlForm').submit();
    });
</script>

<?php include 'footer.php'; ?>