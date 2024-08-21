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
  $link->close();
  exit;
}

global $link;

//Plans::init();
Plans::getInstance();

$promotions = new Promotions($link);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_promotion'])) {
  $promotionData = [
    'promotion_name' => $_POST['promotion_name'],
    'promotion_description' => $_POST['promotion_description'],
    'promotion_start_time' => $_POST['promotion_start_time'],
    'promotion_end_time' => $_POST['promotion_end_time'],
    'promotion_percentage' => $_POST['promotion_percentage'],
    'promotion_applicable_plans' => implode(",", $_POST['promotion_applicable_plans']),
  ];

  $promotions->addPromotion($promotionData);
  header("location: /account/admin/promo.php");
  $link->close();
  exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_promotion'])) {
  $promotionData = [
    'promotion_name' => $_POST['promotion_name'],
    'promotion_description' => $_POST['promotion_description'],
    'promotion_start_time' => $_POST['promotion_start_time'],
    'promotion_end_time' => $_POST['promotion_end_time'],
    'promotion_percentage' => $_POST['promotion_percentage'],
    'promotion_applicable_plans' => implode(",", $_POST['promotion_applicable_plans']),
  ];

  $promotions->updatePromotion($_POST['id'], $promotionData);
  header("location: /account/admin/promo.php");
  $link->close();
  exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_promotion'])) {
  $promotions->deletePromotion($_POST['id']);
  header("location: /account/admin/promo.php");
  $link->close();
  exit;
}

$present_promotions = $promotions->getCurrentAndFuturePromotions();
$past_promotions = $promotions->getPastPromotions();

// Build HTML for promotions table and a form for each promotion, as well as a form for adding a new promotion

?>

<!DOCTYPE html>
<html>

<head>
  <title>Manage Promotions</title>
  <link rel="stylesheet" href="/styles/twbuild.css?v=384be5c08d9cc0c2325e03b56a1b1e14" />
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
      Start Time is the time when the promotion starts. <br />
      End Time is the time when the promotion ends. <br />
      Promotion with the highest discount will be applied to the plan if times overlap. <br />
      View only shows current and future promotions. <br />
    </p>

    <!-- Promotions Display -->
    <div class="space-y-4">
      <?php foreach ($present_promotions as $promo) : ?>
        <form method="post" class="bg-white p-4 rounded-lg shadow">
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

          <div class="flex justify-end space-x-2">
            <input type="hidden" name="id" value="<?= $promo['id']; ?>">
            <input type="submit" name="update_promotion" value="Update" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            <input type="submit" name="delete_promotion" value="Delete" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
          </div>
        </form>
      <?php endforeach; ?>
    </div>

    <!-- Form for adding a new promotion -->
    <div class="mt-8">
      <h3 class="text-2xl font-bold text-gray-800 mb-4">Add New Promotion</h3>
      <form method="post" class="bg-white p-4 shadow rounded-lg">
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

        <input type="submit" name="add_promotion" value="Add Promotion" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
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
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

  </div>
</body>

</html>