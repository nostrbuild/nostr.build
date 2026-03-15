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
$link->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>nostr.build DB updater</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>body { font: 14px sans-serif; padding: 20px; }</style>
</head>
<body>
<div class="container">
  <h2>Update User DB</h2>

  <h4>Update @nym</h4>
  <form class="api-form row g-2 mb-3" data-url="/api/v2/admin/users/nym">
    <div class="col-auto"><input type="text" class="form-control" name="usernpub" placeholder="user's npub" required></div>
    <div class="col-auto"><input type="text" class="form-control" name="nym" placeholder="@nym" required></div>
    <div class="col-auto"><button type="submit" class="btn btn-primary">Submit</button></div>
    <div class="col-12 result"></div>
  </form>

  <h4>Update Profile Pic</h4>
  <form class="api-form row g-2 mb-3" data-url="/api/v2/admin/users/pfp">
    <div class="col-auto"><input type="text" class="form-control" name="usernpub" placeholder="user's npub" required></div>
    <div class="col-auto"><input type="text" class="form-control" name="ppic" placeholder="link to profile pic" required></div>
    <div class="col-auto"><button type="submit" class="btn btn-primary">Submit</button></div>
    <div class="col-12 result"></div>
  </form>

  <h4>Update Account Level</h4>
  <form class="api-form row g-2 mb-3" data-url="/api/v2/admin/users/account-level">
    <div class="col-auto"><input type="text" class="form-control" name="usernpub" placeholder="user's npub" required></div>
    <div class="col-auto"><input type="number" class="form-control" name="acctlevel" placeholder="account level" required></div>
    <div class="col-auto"><button type="submit" class="btn btn-primary">Submit</button></div>
    <div class="col-12 result"></div>
  </form>
</div>

<script>
document.querySelectorAll('.api-form').forEach(function(form) {
  form.addEventListener('submit', function(e) {
    e.preventDefault();
    const url = this.getAttribute('data-url');
    const resultDiv = this.querySelector('.result');
    const formData = new FormData(this);

    fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      body: formData
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        resultDiv.innerHTML = '<div class="alert alert-success mt-2">Updated successfully!</div>';
      } else {
        resultDiv.innerHTML = '<div class="alert alert-danger mt-2">Error: ' + (data.error || 'Unknown') + '</div>';
      }
    })
    .catch(err => {
      resultDiv.innerHTML = '<div class="alert alert-danger mt-2">' + err.message + '</div>';
    });
  });
});
</script>
</body>
</html>
