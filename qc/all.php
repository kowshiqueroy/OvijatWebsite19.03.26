<?php
include_once 'config.php'; // Include your database connection and configuration file
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $company_name; ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="menu-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-menu"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
        </div>
        <div class="website-name">
            <h1><?php echo $company_name; ?></h1>
        </div>
        <div class="logout-button">
            <button onclick="window.location.href='logout.php'">Logout</button>
        </div>
    </header>
    <nav class="sidebar">
        <ul>
            <li><a href="#">Dashboard</a></li>
            <li><a href="#">Users</a></li>
            <li><a href="#">Products</a></li>
            <li><a href="#">Orders</a></li>
            <li><a href="#">Settings</a></li>
        </ul>
    </nav>
    <main class="printable">
        <div class="form-row">
            <form class="card">
                <h2>Form 1</h2>
                <div class="form-group">
                    <label for="name1">Name</label>
                    <input type="text" id="name1" name="name1">
                </div>
                <div class="form-group">
                    <label for="email1">Email</label>
                    <input type="email" id="email1" name="email1">
                </div>
                <button type="submit">Submit</button>
            </form>
            <form class="card">
                <h2>Form 2</h2>
                <div class="form-group">
                    <label for="name2">Name</label>
                    <input type="text" id="name2" name="name2">
                </div>
                <div class="form-group">
                    <label for="email2">Email</label>
                    <input type="email" id="email2" name="email2">
                </div>
                <button type="submit">Submit</button>
            </form>
        </div>
        <div class="form-row-center">
            <form class="card">
                <h2>Single Form</h2>
                <div class="form-group">
                    <label for="name3">Name</label>
                    <input type="text" id="name3" name="name3">
                </div>
                <div class="form-group">
                    <label for="email3">Email</label>
                    <input type="email" id="email3" name="email3">
                </div>
                 <div class="form-group">
                    <label for="message">Message</label>
                    <textarea id="message" name="message" rows="4"></textarea>
                </div>
                <button type="submit">Submit</button>
            </form>
        </div>
        <div class="table-container card">
            <h2>Users</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td>John Doe</td>
                        <td>john.doe@example.com</td>
                        <td><button class="edit-btn">Edit</button></td>
                    </tr>
                    <tr>
                        <td>2</td>
                        <td>Jane Smith</td>
                        <td>jane.smith@example.com</td>
                        <td><button class="edit-btn">Edit</button></td>
                    </tr>
                    <tr>
                        <td>3</td>
                        <td>Peter Jones</td>
                        <td>peter.jones@example.com</td>
                        <td><button class="edit-btn">Edit</button></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </main>
    <script src="script.js"></script>
</body>
</html>