<?php
include_once 'header.php';
?>
<?php
if (isset($_POST['submit'])) {

    // Process the form data
    $shop_type = $_POST['shop_type'];
    $received_date = $_POST['received_date'];
    $inspection_date = $_POST['inspection_date'];
    $trader_name = $_POST['trader_name'];
    $user_id = $_SESSION['user_id']; // Assuming user ID is stored in session

    // Prepare and execute the SQL statement
    $stmt = $conn->prepare("INSERT INTO damage_details (shop_type, received_date, inspection_date, trader_name, created_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $shop_type, $received_date, $inspection_date, $trader_name, $user_id);
    if ($stmt->execute()) {
        $msg = "Damage report created successfully!";
        $id = $conn->insert_id;
        header("Location: damage_edit.php?id=" . $id);
         exit();
    } else {
        $msg = "Error creating damage report: " . $stmt->error;
    }
    $stmt->close();
    // Redirect to the damages page
    $_SESSION['msg'] = $msg;
    // Use header to redirect




    header("Location: damages.php");
    exit();
}
?>
<main class="printable">
    <h2>Damage Form</h2>
   <style>
   

    .form-wrapper {
        width: 100%;
       
        margin: 1px;
        background-color: #fff;
        padding: 10px;
        border-radius: 1rem;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        border: 1px solid #e0e0e0;
    }

    .form-wrapper h3 {
        font-size: 1.5rem;
        font-weight: 700;
        text-align: center;
        margin-bottom: 2rem;
        color: #2c3e50;
    }

    .form-row {
        display: flex;
        gap: 1rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
    }

    .form-group {
        flex: 1 1 25%;
        display: flex;
        flex-direction: column;
    }

    label {
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: #34495e;
    }

    input {
        padding: 0.75rem 1rem;
        border: 1px solid var(--input-border);
        border-radius: 0.5rem;
        background-color: var(--input-bg);
        font-size: 1rem;
        transition: border-color 0.3s, box-shadow 0.3s;
    }
    select {
        padding: 0.75rem 1rem;
        border: 1px solid var(--input-border);
        border-radius: 0.5rem;
        background-color: var(--input-bg);
        font-size: 1rem;
        transition: border-color 0.3s, box-shadow 0.3s;
    }

    input:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.25rem rgba(26,115,232,0.2);
        outline: none;
    }

    .btn-submit {
        display: block;
        width: 100%;
        background-color: var(--primary-color);
        color: white;
        border: none;
        padding: 0.75rem 1.25rem;
        font-size: 1rem;
        font-weight: 600;
        border-radius: 0.5rem;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }

    .btn-submit:hover {
        background-color: #1666cc;
    }

    @media screen and (max-width: 600px) {
        .form-group {
            flex: 1 1 20%;
        }
        .form-wrapper {
            width: 100%;
        }
        
    }
</style>

<div class="form-wrapper">
  
    <form action="damages_create.php" method="POST">

            <div class="form-row">
                <div class="form-group">
                <label for="shop_type">Type</label>
                <select name="shop_type" id="shop_type" required>
                    <option value="TP">TP</option>
                    <option value="DP">DP</option>
                </select>
                </div>
                <div class="form-group">
                <label for="received_date">R.date</label>
                <input type="text" name="received_date" id="received_date" required value="<?= date('Y-m-d') ?>">
                </div>
                 <div class="form-group">
                <label for="inspection_date">I.date</label>
                <input type="text" name="inspection_date" id="inspection_date" required value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="form-group">
                <input type="text" name="trader_name" class="form-control" id="trader-name-input" placeholder="Trader" value="<?= htmlspecialchars($_POST['trader_name'] ?? '') ?>" autocomplete="off" required>
                <div id="trader-suggestions" class="list-group position-absolute w-100" style="z-index: 10; display: none; max-height: 200px; overflow-y: auto;"></div>
            </div>
            <script>
                // Example company names, replace with AJAX if needed
                const companies = [
                    <?php
                    // Fetch unique company names from the database
                    $companyNames = [];
                    $res = $conn->query("SELECT DISTINCT trader_name FROM damage_details WHERE trader_name IS NOT NULL AND trader_name != '' ORDER BY trader_name ASC");
                    while ($row = $res->fetch_assoc()) {
                        // Escape for JS string
                        $jsCompany = addslashes($row['trader_name']);
                        echo "                                \"$jsCompany\",\n";
                    }
                    ?>
                ];

                const input = document.getElementById('trader-name-input');
                const suggestions = document.getElementById('trader-suggestions');

                input.addEventListener('input', function() {
                    const val = this.value.trim().toLowerCase();
                    suggestions.innerHTML = '';
                    if (!val) {
                        suggestions.style.display = 'none';
                        return;
                    }
                    const matches = companies.filter(c => c.toLowerCase().includes(val));
                    if (matches.length === 0) {
                        suggestions.style.display = 'none';
                        return;
                    }
                    matches.forEach(company => {
                        const item = document.createElement('button');
                        item.type = 'button';
                        item.className = 'list-group-item list-group-item-action';
                        item.textContent = company;
                        item.onclick = function() {
                            input.value = company;
                            suggestions.style.display = 'none';
                        };
                        suggestions.appendChild(item);
                    });
                    suggestions.style.display = 'block';
                });

                // Show suggestions on click, even if input is empty
                // input.addEventListener('click', function() {
                //     const val = this.value.trim().toLowerCase();
                //     suggestions.innerHTML = '';
                //     let matches = [];
                //     if (!val) {
                //         matches = companies;
                //     } else {
                //         matches = companies.filter(c => c.toLowerCase().includes(val));
                //     }
                //     if (matches.length === 0) {
                //         suggestions.style.display = 'none';
                //         return;
                //     }
                //     matches.forEach(company => {
                //         const item = document.createElement('button');
                //         item.type = 'button';
                //         item.className = 'list-group-item list-group-item-action';
                //         item.textContent = company;
                //         item.onclick = function() {
                //             input.value = company;
                //             suggestions.style.display = 'none';
                //         };
                //         suggestions.appendChild(item);
                //     });
                //     suggestions.style.display = 'block';
                // });

                document.addEventListener('click', function(e) {
                    if (!input.contains(e.target) && !suggestions.contains(e.target)) {
                        suggestions.style.display = 'none';
                    }
                });
            </script>
      
            
     
        <button type="submit" class="btn-submit" name="submit">Submit</button>
    </form>
</div>

</main>

<?php
include_once 'footer.php';
?>