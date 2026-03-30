<?php
include_once 'header.php';
?>

<main class="printable">
<div class="button-container">
  <a href="damages_create.php" class="btn btn-primary">Create New Damage</a>
  <a href="damages.php" class="btn btn-secondary">View All Damages</a>
</div>
 
<div class="developer-info">
  <h2>Developed by Ovijat IT</h2>
  <p>Version: 1.1.2</p>
  <p>Last Updated: 30/07/2025</p>
  <p>Developer: Kowshique Roy</p>
</div>

<style>
.developer-info {
  margin: 2rem auto;
  padding: 1rem;
  max-width: 600px;
  text-align: center;

  border-radius: 8px;
}
</style>
<style>
.button-container {
  display: flex;
  justify-content: center;
  gap: 24px;
  margin: 3rem 0;
}
.btn {
  padding: 14px 28px;
  border-radius: 8px;
  text-decoration: none;
  font-weight: 600;
  transition: all 0.3s ease;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}
.btn:hover {
  transform: translateY(-3px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}
.btn-primary {
  background: linear-gradient(135deg, #31dd31ff, #ec3b2fff);
  color: white;
}
.btn-secondary {
 background: linear-gradient(135deg, #31dd31ff, #ec3b2fff);
  color: white;
}
</style>
</main>

<?php
include_once 'footer.php';
?>