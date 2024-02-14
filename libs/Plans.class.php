<?php
// This file containes the plans and their features
// Although we could use DB for this, it is wasteful to query the DB for this information each time

class Plan
{
  public int $id;
  public string $name;
  public string $image;
  public string $imageAlt;
  public string $promo;
  public string $discountPercentage;
  public int $priceInt;
  public string $price;
  public int $priceInt2y;
  public string $price2y;
  public int $priceInt3y;
  public string $price3y;
  public int $fullPriceInt;
  public int $full2yPriceInt;
  public int $full3yPriceInt;
  public array $features;
  public string $currency;
  public bool $isCurrentPlan = false;

  public function __construct(
    int $id,
    string $name,
    int $price,
    array $features,
    string $currency,
    ?int $remainingDays = null,
    ?int $fromPlanPrice = 0,
    ?int $fromPlanLevel = null,
    string $image = '', // Default to an empty string
    string $imageAlt = '', // Default to an empty string
    string $period = '1y'
  ) {
    $this->id = $id;
    $this->name = $name;
    $this->features = $features;
    $this->currency = $currency;
    $this->promo = false;
    $this->discountPercentage = 0;
    $this->image = $image;
    $this->imageAlt = $imageAlt;

    if ($remainingDays === 0) {
      $proratedPrice = $price - $fromPlanPrice;
    } else {
      $proratedPrice = $price - (($fromPlanPrice / 365) * $remainingDays);
    }

    // If the current plan is the same as the plan we are creating, set isCurrentPlan to true
    $this->isCurrentPlan = $fromPlanLevel === $id;

    // Ensure the prorated price is not negative
    $this->fullPriceInt = $price;
    $this->full2yPriceInt = $price * 1 * (1 - Plans::$twoYearDiscount);
    $this->full3yPriceInt = $price * 2 * (1 - Plans::$threeYearDiscount);

    $this->priceInt = $this->isCurrentPlan ? $price : ($remainingDays === null ? $price : max(0, round($proratedPrice)));

    // Pre-calculating the 2 and 3 year prices
    $this->priceInt2y = $this->priceInt + $this->full2yPriceInt;
    $this->price2y = number_format($this->priceInt2y, 0, '.', ',');
    $this->priceInt3y = $this->priceInt + $this->full3yPriceInt;
    $this->price3y = number_format($this->priceInt3y, 0, '.', ',');

    switch ($period) {
      case '2y':
        $this->priceInt = $this->priceInt2y;
        $this->price = $this->price2y;
        break;
      case '3y':
        $this->priceInt = $this->priceInt3y;
        $this->price = $this->price3y;
        break;
      default:
        $this->price = number_format($this->priceInt, 0, '.', ',');
    }
  }
}

class Plans
{
  // New account level numbering will increment from 10 in increments of 10.
  const ADVANCED = 10; // Starting the account level renumbering from 10 to avoid conflicts with the old account levels.
  const CREATOR = 1; // The highest previouse level
  const PROFESSIONAL = 2;
  const STARTER = 5;
  const VIEWER = 4;
  const MODERATOR = 89;
  const NEW = 0;
  const ADMIN = 99;

  public static array $PLANS = [];
  private static $instance = null;
  // Discount applies only to the additional year(s)
  static $twoYearDiscount = 0.1; // 10% discount for 2 years
  static $threeYearDiscount = 0.20; // 20% discount for 2 years

  private function __construct()
  {
    // private constructor to prevent creating a new instance
  }

  private static function init(?int $remainingDays = null, ?int $currentPlanLevel = null, ?array $promotions = null, ?string $period = '1y'): void
  {
    $originalPrices = [
      self::ADVANCED => 250_000,
      self::CREATOR => 120_000,
      self::PROFESSIONAL => 69_000,
      self::STARTER => 21_000,
      self::VIEWER => 5_000,
      self::NEW => 0,
      self::MODERATOR => 0,
      self::ADMIN => 0
    ];


    // Calculate the price based on the level and days remaining
    // Take into account special cases like NEW, MODERATOR, ADMIN
    // TODO: need to account upgfrade from plans that are longer than 1 year
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

    // Create the plans
    // Only create plans that are for the current user level or higher
    // Do not show current plan for renew if it has more than 30 days remaining
    if (
      ($currentPlanLevel === null || $currentPlanLevel === 0) ||
      ($currentPlanLevel === self::PROFESSIONAL && $remainingDays <= 30) ||
      ($currentPlanLevel > self::PROFESSIONAL && $currentPlanLevel < self::ADVANCED)
    ) {
      self::$PLANS[self::PROFESSIONAL] = new Plan(
        id: self::PROFESSIONAL,
        name: 'Professional',
        image: 'https://cdn.nostr.build/assets/signup/pro.png',
        imageAlt: 'pro plan image',
        price: $originalPrices[self::PROFESSIONAL],
        features: [
          '<b>10GB of private storage</b>',
          '<b>View All 1M+ free media</b>',
          'Add/Delete your media',
          'Global CDN',
          '❤️ Support nostr.build ❤️',
        ],
        currency: 'sats',
        remainingDays: $remainingDays,
        fromPlanPrice: $fromPlanPrice,
        fromPlanLevel: $currentPlanLevel
      );
    };

    if (
      ($currentPlanLevel === null || $currentPlanLevel === 0) ||
      ($currentPlanLevel === self::CREATOR && $remainingDays <= 30) ||
      ($currentPlanLevel > self::CREATOR && $currentPlanLevel < self::ADVANCED)
    ) {
      self::$PLANS[self::CREATOR] = new Plan(
        id: self::CREATOR,
        name: 'Creator',
        price: $originalPrices[self::CREATOR],
        features: [
          '<b>20GB of private storage</b>',
          '<b><a class="ref_link" target="_blank" href="https://nostr.build/creators/">Host on nostr.build page</a></b>',
          '<b>Global CDN for all media</b>',
          '<b>All the Professional Features</b>',
          '❤️ Support nostr.build ❤️',
        ],
        currency: 'sats',
        remainingDays: $remainingDays,
        fromPlanPrice: $fromPlanPrice,
        fromPlanLevel: $currentPlanLevel
      );
    };

    // New numbering for the advanced plan, hence the less than or equal
    if (
      ($currentPlanLevel === null || $currentPlanLevel === 0) ||
      ($currentPlanLevel === self::ADVANCED && $remainingDays <= 30) ||
      ($currentPlanLevel < self::ADVANCED)
    ) {
      self::$PLANS[self::ADVANCED] = new Plan(
        id: self::ADVANCED,
        name: 'Advanced',
        price: $originalPrices[self::ADVANCED],
        features: [
          '<b>50GB of private storage</b>',
          '<b>Early access to new features</b>',
          '<b>Media backed-up to S3</b>',
          '<b>@nostr.build NIP-05 *</b>',
          '<b>Expandable storage *</b>',
          '<b>All the Creator features</b>',
          '❤️ Support nostr.build ❤️',
        ],
        currency: 'sats',
        remainingDays: $remainingDays,
        fromPlanPrice: $fromPlanPrice,
        fromPlanLevel: $currentPlanLevel
      );
    };


    // TODO: Make promotion applicable to upgrades as well
    if (is_array($promotions) && !empty($promotions) && ($remainingDays !== null || $currentPlanLevel !== null)) {
      // Loop over all plans and check if there is a promotion for them
      // Apply the promotion if there is one
      foreach (self::$PLANS as $plan) {
        // Pick promotion with the highest discount for the plan
        $applicablePromotions = array_filter($promotions, function ($promotion) use ($plan) {
          // Ensure the plan ID is the expected type, e.g., cast to integer if necessary
          return in_array((int)$plan->id, array_map('intval', $promotion['promotion_applicable_plans']));
        });
        // Pick the promotion with the highest discount or the latest end date if there is a tie
        $promotion = array_reduce($applicablePromotions, function ($highestDiscount, $currentPromotion) {
          if (
            $highestDiscount['promotion_percentage'] < $currentPromotion['promotion_percentage'] ||
            ($highestDiscount['promotion_percentage'] == $currentPromotion['promotion_percentage'] &&
              strtotime($highestDiscount['promotion_end_time']) < strtotime($currentPromotion['promotion_end_time']))
          ) {
            return $currentPromotion;
          }
          return $highestDiscount;
        }, ['promotion_percentage' => 0, 'promotion_end_time' => '']);
        // Apply the promotion if there is one
        if ($promotion['promotion_percentage'] > 0) {
          $plan->priceInt = $plan->priceInt * (1 - $promotion['promotion_percentage'] / 100);
          $plan->price = number_format($plan->priceInt, 0, '.', ',');
          // Prepend the promotion to the features with the end date
          array_unshift($plan->features, "<span class=\"promotion_text\">Valid until {$promotion['promotion_end_time']} UTC.</span>");
          array_unshift($plan->features, "<span class=\"promotion_text\">Save {$promotion['promotion_percentage']}% with {$promotion['promotion_name']}!</span>");
          // Indicate that there is a promotion
          $plan->promo = true;
          $plan->discountPercentage = $promotion['promotion_percentage'];
          self::$PLANS[$plan->id] = $plan;
        }
      }
    }
  }

  public static function isValidPlan(int $plan): bool
  {
    return array_key_exists($plan, self::$PLANS);
  }

  public static function getInstance(?int $remainingDays = null, ?int $currentPlanLevel = null, ?array $promotions = null, ?string $period = '1y'): Plans
  {
    if (self::$instance === null) {
      self::$instance = new Plans();
      self::$instance::init($remainingDays, $currentPlanLevel, $promotions, $period);
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
