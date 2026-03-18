<?php
// Include config file
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/db/Promotions.class.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/Plans.class.php');

// Create new Permission object
$perm = new Permission();

if (!$perm->isAdmin()) {
  header("location: /login");
  $link->close(); // CLOSE MYSQL LINK
  exit;
}

global $link;

Plans::getInstance();

$promotions = new Promotions($link);

$present_promotions = $promotions->getCurrentAndFuturePromotions();
$past_promotions = $promotions->getPastPromotions();

$link->close();
?>

<!DOCTYPE html>
<html>

<head>
  <title>Manage Promotions</title>
  <link rel="stylesheet" href="/styles/twbuild.css?v=eeae2d6257e41948d0c755b32758efeb" />
</head>

<body class="bg-gray-100">
  <div class="container mx-auto p-4 md:p-8">
    <h2 class="text-2xl md:text-4xl font-bold text-gray-800 mb-6">Manage Promotions</h2>
    <h3 class="text-2xl font-bold text-gray-800 mb-4">Current Promotions (READ THE INSTRUCTIONS BELOW THIS TITLE)</h3>
    <p class="text-sm md:text-lg text-gray-600 mb-4">
      All times are in UTC. <br />
      Description is only for information and not used in the rendering of the HTML. <br />
      Name will be applied to the text "Save XX% with {name}" <br />
      Percentage is the discount percentage. <br />
      Applicable Plans is a list of plan IDs that the promotion applies to. <br />
      Promotion type dictates if it only to the per plan signups or global finall price discount for any type, e.g., renew, signup, upgrade, etc. <br /> 
      When using Global promotion, applicable account types are ignored. <br />
      Start Time is the time when the promotion starts. <br />
      End Time is the time when the promotion ends. <br />
      Promotion with the highest discount will be applied to the plan if times overlap. <br />
      View only shows current and future promotions. <br />
    </p>
    <!-- Display current UTC time -->
    <p class="text-sm md:text-lg text-gray-600 mb-4">
      Current UTC Time: <?= gmdate('Y-m-d H:i:s'); ?>
    </p>

    <!-- Promotions Display -->
    <div class="space-y-4">
      <?php foreach ($present_promotions as $promo) : ?>
        <form class="promo-form bg-white p-4 rounded-lg shadow" data-action="update">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div>
              <label class="block text-gray-700 text-sm font-bold mb-2">Name:</label>
              <input type="text" name="promotion_name" value="<?= htmlspecialchars($promo['promotion_name']); ?>" class="form-input w-full">
            </div>
            <div class="md:col-span-2">
              <label class="block text-gray-700 text-sm font-bold mb-2">Description:</label>
              <textarea name="promotion_description" class="form-textarea w-full" rows="2"><?= htmlspecialchars($promo['promotion_description']); ?></textarea>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div>
              <label class="block text-gray-700 text-sm font-bold mb-2">Start Time:</label>
              <input type="datetime-local" name="promotion_start_time" value="<?= htmlspecialchars($promo['promotion_start_time']); ?>" class="form-input w-full">
            </div>
            <div>
              <label class="block text-gray-700 text-sm font-bold mb-2">End Time:</label>
              <input type="datetime-local" name="promotion_end_time" value="<?= htmlspecialchars($promo['promotion_end_time']); ?>" class="form-input w-full">
            </div>
            <div>
              <label class="block text-gray-700 text-sm font-bold mb-2">Percentage:</label>
              <input type="number" name="promotion_percentage" value="<?= htmlspecialchars($promo['promotion_percentage']); ?>" class="form-input w-full">
            </div>
          </div>

          <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2">Applicable Plans:</label>
            <select name="promotion_applicable_plans[]" multiple required class="form-multiselect w-full">
              <?php foreach (Plans::$PLANS as $planId => $plan) : ?>
                <option value="<?= $plan->id; ?>" <?= in_array($plan->id, $promo['promotion_applicable_plans']) ? 'selected' : ''; ?>>
                  <?= htmlspecialchars($plan->name); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2">Promotion Type:</label>
            <select name="promotion_type" class="form-select w-full">
              <option value="perPlan" <?= $promo['promotion_type'] == 'perPlan' ? 'selected' : ''; ?>>Per Plan</option>
              <option value="global" <?= $promo['promotion_type'] == 'global' ? 'selected' : ''; ?>>Global</option>
            </select>
          </div>

          <div class="flex justify-end space-x-2">
            <input type="hidden" name="id" value="<?= $promo['id']; ?>">
            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded" data-action="update">Update</button>
            <button type="button" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded delete-promo-btn">Delete</button>
          </div>
        </form>
      <?php endforeach; ?>
    </div>

    <!-- Form for adding a new promotion -->
    <div class="mt-8">
      <h3 class="text-2xl font-bold text-gray-800 mb-4">Add New Promotion</h3>
      <form class="promo-form bg-white p-4 shadow rounded-lg" data-action="add">
        <div class="mb-4">
          <label for="promotion_name" class="block text-gray-700 text-sm font-bold mb-2">Name:</label>
          <input type="text" id="promotion_name" name="promotion_name" required class="form-input mt-1 block w-full">
        </div>

        <div class="mb-4">
          <label for="promotion_description" class="block text-gray-700 text-sm font-bold mb-2">Description:</label>
          <textarea id="promotion_description" name="promotion_description" required class="form-textarea mt-1 block w-full" rows="3"></textarea>
        </div>

        <div class="mb-4">
          <label for="promotion_start_time" class="block text-gray-700 text-sm font-bold mb-2">Start Time:</label>
          <input type="datetime-local" id="promotion_start_time" name="promotion_start_time" required class="form-input mt-1 block w-full">
        </div>

        <div class="mb-4">
          <label for="promotion_end_time" class="block text-gray-700 text-sm font-bold mb-2">End Time:</label>
          <input type="datetime-local" id="promotion_end_time" name="promotion_end_time" required class="form-input mt-1 block w-full">
        </div>

        <div class="mb-4">
          <label for="promotion_percentage" class="block text-gray-700 text-sm font-bold mb-2">Percentage:</label>
          <input type="number" id="promotion_percentage" name="promotion_percentage" min="0" max="100" required class="form-input mt-1 block w-full">
        </div>

        <div class="mb-4">
          <label for="promotion_applicable_plans" class="block text-gray-700 text-sm font-bold mb-2">Applicable Plans:</label>
          <select id="promotion_applicable_plans" name="promotion_applicable_plans[]" multiple required class="form-multiselect mt-1 block w-full">
            <?php foreach (Plans::$PLANS as $planId => $plan) : ?>
              <option value="<?= $plan->id ?>"><?= htmlspecialchars($plan->name) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-4">
          <label for="promotion_type" class="block text-gray-700 text-sm font-bold mb-2">Promotion type:</label>
          <select id="promotion_type" name="promotion_type" required class="form-select mt-1 block w-full">
            <option value="perPlan" selected>Per Plan</option>
            <option value="global">Global</option>
          </select>
        </div>

        <button type="submit" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">Add Promotion</button>
      </form>
    </div>
    <!-- past promotions -->
    <table class="table-auto w-full mt-8">
      <thead>
        <tr>
          <th class="px-4 py-2">Name</th>
          <th class="px-4 py-2">Description</th>
          <th class="px-4 py-2">Start Time</th>
          <th class="px-4 py-2">End Time</th>
          <th class="px-4 py-2">Percentage</th>
          <th class="px-4 py-2">Applicable Plans</th>
          <th class="px-4 py-2">Promotion Type</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($past_promotions as $promo) : ?>
          <tr>
            <td class="border px-4 py-2"><?= htmlspecialchars($promo['promotion_name']); ?></td>
            <td class="border px-4 py-2"><?= htmlspecialchars($promo['promotion_description']); ?></td>
            <td class="border px-4 py-2"><?= htmlspecialchars($promo['promotion_start_time']); ?></td>
            <td class="border px-4 py-2"><?= htmlspecialchars($promo['promotion_end_time']); ?></td>
            <td class="border px-4 py-2"><?= htmlspecialchars($promo['promotion_percentage']); ?></td>
            <td class="border px-4 py-2">
              <?php foreach (Plans::$PLANS as $planId => $plan) : ?>
                <?= in_array($plan->id, $promo['promotion_applicable_plans']) ? htmlspecialchars($plan->name) : ''; ?>
              <?php endforeach; ?>
            </td>
            <td class="border px-4 py-2"><?= htmlspecialchars($promo['promotion_type']); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

  </div>
<script>
document.querySelectorAll('.promo-form').forEach(function(form) {
  form.addEventListener('submit', function(e) {
    e.preventDefault();
    const action = this.getAttribute('data-action');
    const url = action === 'add'
      ? '/api/v2/admin/promotions/add'
      : '/api/v2/admin/promotions/update';
    const formData = new FormData(this);

    fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      body: formData
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        location.reload();
      } else {
        alert('Error: ' + (data.error || 'Unknown error'));
      }
    })
    .catch(err => alert('Error: ' + err.message));
  });
});

document.querySelectorAll('.delete-promo-btn').forEach(function(btn) {
  btn.addEventListener('click', function() {
    if (!confirm('Are you sure you want to delete this promotion?')) return;
    const form = this.closest('form');
    const id = form.querySelector('input[name="id"]').value;
    const formData = new FormData();
    formData.append('id', id);

    fetch('/api/v2/admin/promotions/delete', {
      method: 'POST',
      credentials: 'same-origin',
      body: formData
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        location.reload();
      } else {
        alert('Error: ' + (data.error || 'Unknown error'));
      }
    })
    .catch(err => alert('Error: ' + err.message));
  });
});
</script>
</body>

</html>