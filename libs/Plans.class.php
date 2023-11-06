<?php
// This file containes the plans and their features
// Although we could use DB for this, it is wasteful to query the DB for this information each time

class Plan
{
  public int $id;
  public string $name;
  public string $image;
  public string $imageAlt;
  public string $price;
  public int $priceInt;
  public array $features;
  public string $currency;
  public bool $isCurrentPlan = false;

  public function __construct(
    int $id,
    string $name,
    string $image,
    string $imageAlt,
    int $price,
    array $features,
    string $currency,
    ?int $remainingDays = null,
    ?int $fromPlanPrice = 0,
    ?int $fromPlanLevel = null
  ) {
    $this->id = $id;
    $this->name = $name;
    $this->image = $image;
    $this->imageAlt = $imageAlt;
    $this->features = $features;
    $this->currency = $currency;

    if ($remainingDays === 0) {
      $proratedPrice = $price - $fromPlanPrice;
    } else {
      $proratedPrice = ($price - $fromPlanPrice) / 365 * $remainingDays;
    }

    // Ensure the prorated price is not negative
    $this->priceInt = $remainingDays === null ? $price : max(0, round($proratedPrice));
    $this->price = number_format($this->priceInt, 0, '.', ',');

    // If the current plan is the same as the plan we are creating, set isCurrentPlan to true
    $this->isCurrentPlan = $fromPlanLevel === $id;
  }
}

class Plans
{
  const CREATOR = 1;
  const PROFESSIONAL = 2;
  const STARTER = 5;
  const VIEWER = 4;
  const MODERATOR = 89;
  const NEW = 0;
  const ADMIN = 99;

  public static array $PLANS = [];
  private static $instance = null;

  private function __construct()
  {
    // private constructor to prevent creating a new instance
  }

  public static function init(?int $remainingDays = null, ?int $currentPlanLevel = null): void
  {
    $originalPrices = [
      self::CREATOR => 100_000,
      self::PROFESSIONAL => 50_000,
      self::STARTER => 21_000,
      self::VIEWER => 21_000,
      self::NEW => 0,
      self::MODERATOR => 0,
      self::ADMIN => 0
    ];

    // Calculate the price based on the level and days remaining
    // Take into account special cases like NEW, MODERATOR, ADMIN
    switch ($currentPlanLevel) {
      case self::NEW:
      case self::MODERATOR:
      case self::ADMIN:
      case null:
        $fromPlanPrice = 0;
        $remainingDays = 365;
        break;
      default:
        $fromPlanPrice = $originalPrices[$currentPlanLevel];
        if ((365 - $remainingDays) <= 30) { // Only 30 days used so far 
          $fromPlanPrice = $originalPrices[$currentPlanLevel]; // 30 days grace period, so we negate the full current plan price
          $remainingDays = 365; // Reset the remaining days to 365
        } elseif ($remainingDays <= 30) { // Only 30 days remain
          $fromPlanPrice = 0; // Negate the full current plan price
          $remainingDays = 0;
        } else {
          $dailyRate = $originalPrices[$currentPlanLevel] / 365;
          $amountUsedSoFar = $dailyRate * (365 - $remainingDays);
          $fromPlanPrice -= $amountUsedSoFar; // Negate the amount used so far
        }
        break;
    }

    /* Logic behind the upgrade pricing:
    If the user is on a special plan like NEW, MODERATOR, ADMIN,
    or if the current plan level is not set (null), then the remaining
    days are reset to 365, and no amount is subtracted from the upgrade price.

    For all other cases:

    If only 30 days or fewer have been used so far ((365 - $remainingDays) <= 30),
    then the full original price of the current plan level is subtracted from
    the upgrade price, and the remaining days are reset to 365.

    If only 30 days or fewer remain in the subscription, then nothing is subtracted
    from the upgrade price, and the remaining days are set to 0.

    In all other cases, the amount used so far is calculated based on a daily rate
    and subtracted from the upgrade price. The remaining days are unchanged.
    */
    self::$PLANS[self::CREATOR] = new Plan(
      self::CREATOR,
      'Creator',
      'https://cdn.nostr.build/assets/signup/creator.png',
      'creator plan image',
      $originalPrices[self::CREATOR],
      [
        '<b><a class="ref_link" target="_blank" href="https://nostr.build/creators/">Host on nostr.build Creators page</a></b>',
        '<b>20GB of private storage</b>',
        'BTCPay Server Account',
        'View All : 800k+ pics, GIFs & videos',
        'Add/Delete your media'
      ],
      'sats',
      $remainingDays,
      $fromPlanPrice,
      $currentPlanLevel
    );

    self::$PLANS[self::PROFESSIONAL] = new Plan(
      self::PROFESSIONAL,
      'Professional',
      'https://cdn.nostr.build/assets/signup/pro.png',
      'pro plan image',
      $originalPrices[self::PROFESSIONAL],
      [
        '<b>10GB of private storage</b>',
        '<b>View All : 800k+ pics, GIFs & videos</b>',
        'Add/Delete your media'
      ],
      'sats',
      $remainingDays,
      $fromPlanPrice,
      $currentPlanLevel
    );
  }

  public static function isValidPlan(int $plan): bool
  {
    return array_key_exists($plan, self::$PLANS);
  }

  public static function getInstance(?int $remainingDays = null, ?int $currentPlanLevel = null): Plans
  {
    if (self::$instance === null) {
      self::$instance = new Plans();
      self::$instance::init($remainingDays, $currentPlanLevel);
    }
    return self::$instance;
  }
}

/*
Plans::init();
or
Plans::init(<days remaining>, <current plan level>);
// Accessing the plans in a loop
foreach (Plans::$PLANS as $plan) {
  echo "ID: " . $plan->id . "\n";
  echo "Name: " . $plan->name . "\n";
  echo "Description: " . $plan->description . "\n";
  echo "Price: " . $plan->price . "\n";
  echo "Currency: " . $plan->currency . "\n";
  echo "Features: \n";
  foreach ($plan->features as $feature) {
    echo "  - " . $feature . "\n";
  }
  echo "\n";
}
*/
