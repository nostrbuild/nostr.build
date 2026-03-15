<?php
// Include config file
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php');

// Create new Permission object
$perm = new Permission();

if (!$perm->isAdmin()) {
    header("location: /login");
    $link->close();
    exit;
}

$result = mysqli_query($link, "SELECT id, usernpub, created_at FROM users WHERE acctlevel = 0 ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>New Accounts</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-3">
  <h2>Free Accounts (level 0)</h2>
  <table border="1" class="table table-sm table-striped">
    <thead><tr><th>ID</th><th>npub</th><th>Created</th></tr></thead>
    <tbody>
    <?php while ($row = mysqli_fetch_assoc($result)): ?>
      <tr>
        <td><?= htmlspecialchars($row['id']) ?></td>
        <td><?= htmlspecialchars($row['usernpub']) ?></td>
        <td><?= htmlspecialchars($row['created_at']) ?></td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>

  <h3>Update Account Level</h3>
  <form id="acctForm">
    <div class="row g-2 mb-3">
      <div class="col-auto">
        <input type="text" class="form-control" name="usernpub" placeholder="user's npub" required>
      </div>
      <div class="col-auto">
        <input type="number" class="form-control" name="acctlevel" placeholder="account level" required>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-primary">Submit</button>
      </div>
    </div>
  </form>
  <div id="result"></div>
</div>
<script>
document.getElementById('acctForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  fetch('/api/v2/admin/users/account-level', {
    method: 'POST',
    credentials: 'same-origin',
    body: formData
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      document.getElementById('result').innerHTML = '<div class="alert alert-success">Updated ' + data.npub + ' to level ' + data.acctlevel + '</div>';
    } else {
      document.getElementById('result').innerHTML = '<div class="alert alert-danger">Error: ' + (data.error || 'Unknown') + '</div>';
    }
  })
  .catch(err => {
    document.getElementById('result').innerHTML = '<div class="alert alert-danger">' + err.message + '</div>';
  });
});
</script>
</body>
</html>
<?php
$link->close();
?>
