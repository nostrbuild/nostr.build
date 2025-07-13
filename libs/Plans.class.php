<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/SiteConfig.php';
// This file containes the plans and their features
// Although we could use DB for this, it is wasteful to query the DB for this information each time

/*
We have three cases: new, upgrade, renewal.
New: No alteration of the price is needed, only adjustment for promotions.
Renewal: No alteration of the price is needed, only potential adjustment for promotions. TODO
Upgrade:
1) We need to know what plan the user is on now.
2) Only display plans that are higher than the current plan.
3) We need to account for the remaining days of the current plan.
4) Calculate the prorated price for the remaining days.
5) We need to account for the period of the current plan.
6) We need to account for the period of the new plan.
7) We need to account for promotions. TODO
*/

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
  public string $fullPrice;
  public string $full2yPrice;
  public string $full3yPrice;
  public array $features;
  public string $currency;
  public bool $isCurrentPlan = false;
  public bool $isRenewable = false;
  // Credits
  public int $bonusCredits;
  public int $bonusCredits2y;
  public int $bonusCredits3y;

  public function __construct(
    int $id,
    string $name,
    int $price,
    array $features,
    string $currency,
    ?int $remainingDays = 0,
    ?int $credit = 0,
    ?int $fromPlanLevel = null,
    string $image = '', // Default to an empty string
    string $imageAlt = '', // Default to an empty string
    string $currentPeriod = '1y',
    int $bonusCredits = 0,
  ) {
    $this->id = $id;
    $this->name = $name;
    $this->features = $features;
    $this->currency = $currency;
    $this->promo = false;
    $this->discountPercentage = 0;
    $this->image = $image;
    $this->imageAlt = $imageAlt;
    $this->bonusCredits = $bonusCredits;
    $this->bonusCredits2y = $bonusCredits * 1.5;
    $this->bonusCredits3y = $bonusCredits * 2;

    // If the current plan is the same as the plan we are creating, set isCurrentPlan to true
    $this->isCurrentPlan = $fromPlanLevel === $id;
    $this->isRenewable = $remainingDays <= 180; // 6 months or less remaining

    // Ensure the prorated price is not negative
    $this->fullPriceInt = $price;
    $this->full2yPriceInt = $price + ($price * 1 * (1 - Plans::$twoYearDiscount));
    $this->full3yPriceInt = $price + ($price * 2 * (1 - Plans::$threeYearDiscount));

    // Prepopulate the price with the full price
    $this->priceInt = $price;
    $this->priceInt2y = $this->full2yPriceInt;
    $this->priceInt3y = $this->full3yPriceInt;

    // Calculate prorated pricing based on remaining credit, period, and remaining days 
    // With period being 1y, simple negation of the credit from the price is enough
    // With period being 2y or 3y, we need to calculate the full price for the period and then negate the credit
    // This will cause issues if low level plan has 2y or 3y period, so we need to apply the credit so the final price is not 0
    if ($fromPlanLevel !== null) {
      // Prorated pricing applies
      $this->priceInt = match (true) {
        $this->priceInt <= $credit => -1, // If we are dealing with price that is lower than the credit
        default => $this->priceInt - $credit
      };
      // Multi-year price/proration calculations
      $this->priceInt2y = match (true) {
        $this->priceInt2y <= $credit => -1, // Un-upgradeable
        default => $this->priceInt2y - $credit
      };
      $this->priceInt3y = match (true) {
        $this->priceInt3y <= $credit => -1, // Un-upgradeable
        default => $this->priceInt3y - $credit
      };
    }

    // Populate textual representations
    $this->price = number_format($this->priceInt, 0, '.', ',');
    $this->price2y = number_format($this->priceInt2y, 0, '.', ',');
    $this->price3y = number_format($this->priceInt3y, 0, '.', ',');
    $this->fullPrice = number_format($this->fullPriceInt, 0, '.', ',');
    $this->full2yPrice = number_format($this->full2yPriceInt, 0, '.', ',');
    $this->full3yPrice = number_format($this->full3yPriceInt, 0, '.', ',');
    // This code is shit but it will do for now.
  }

  public function getCurrencySymbol(): string
  {
    return match ($this->currency) {
      'USD' => '$',
      'EUR' => '€',
      'GBP' => '£',
      'JPY' => '¥',
      'BTC' => '₿',
      'SAT' => 'sats',
      default => $this->currency
    };
  }

  public function getMonthlyPrice(string $period = '1y'): string
  {
    $totalPrice = match ($period) {
      '1y' => $this->isCurrentPlan ? $this->fullPriceInt : $this->priceInt,
      '2y' => $this->isCurrentPlan ? $this->full2yPriceInt : $this->priceInt2y,
      '3y' => $this->isCurrentPlan ? $this->full3yPriceInt : $this->priceInt3y,
      default => $this->isCurrentPlan ? $this->fullPriceInt : $this->priceInt,
    };
    
    if ($totalPrice === -1) {
      return 'Ineligible';
    }
    
    $months = match ($period) {
      '2y' => 24,
      '3y' => 36,
      default => 12,
    };
    
    $monthlyAmount = $totalPrice / $months;
    
    // Show 2 decimal places if the result isn't a whole number
    if ($monthlyAmount != floor($monthlyAmount)) {
      return number_format($monthlyAmount, 2, '.', ',');
    } else {
      return number_format($monthlyAmount, 0, '.', ',');
    }
  }

  public function getPaymentTerms(string $period = '1y'): string
  {
    return match ($period) {
      '2y' => 'paid for 24 months',
      '3y' => 'paid for 36 months',
      default => 'paid for 12 months',
    };
  }

  public function getTotalPrice(string $period = '1y'): string
  {
    $totalPrice = match ($period) {
      '1y' => $this->isCurrentPlan ? $this->fullPriceInt : $this->priceInt,
      '2y' => $this->isCurrentPlan ? $this->full2yPriceInt : $this->priceInt2y,
      '3y' => $this->isCurrentPlan ? $this->full3yPriceInt : $this->priceInt3y,
      default => $this->isCurrentPlan ? $this->fullPriceInt : $this->priceInt,
    };
    
    if ($totalPrice === -1) {
      return 'Ineligible';
    }
    
    return number_format($totalPrice, 0, '.', ',');
  }

  public function getPaymentTermsWithTotal(string $period = '1y'): string
  {
    $totalPrice = $this->getTotalPrice($period);
    if ($totalPrice === 'Ineligible') {
      return 'Plan not available';
    }
    
    $months = match ($period) {
      '2y' => 24,
      '3y' => 36,
      default => 12,
    };
    
    return "{$this->getCurrencySymbol()}{$totalPrice} total for {$months} months";
  }
}

class Plans
{
  // New account level numbering will increment from 10 in increments of 10.
  const ADVANCED = 10; // Starting the account level renumbering from 10 to avoid conflicts with the old account levels.
  const CREATOR = 1; // The highest previouse level
  const PROFESSIONAL = 2;
  const PURIST = 3;
  const STARTER = 5;
  const VIEWER = 4;
  const MODERATOR = 89;
  const NEW = 0;
  const ADMIN = 99;

  public static array $PLANS = [];
  private static $instance = null;
  // Discount applies only to the additional year(s)
  static $twoYearDiscount = 0.1; // 10% discount for 2 years
  static $threeYearDiscount = 0.2; // 20% discount for 2 years

  static $originalPrices = [
    self::ADVANCED => 240,
    self::CREATOR => 120,
    self::PROFESSIONAL => 69,
    self::PURIST => 18,
    self::STARTER => 21,
    self::VIEWER => 5,
    self::NEW => 0,
    self::MODERATOR => 0,
    self::ADMIN => 0
  ];

  private function __construct()
  {
    // private constructor to prevent creating a new instance
  }

  private static function getMultiyearFullPrice(int $price, string $period): int
  {
    switch ($period) {
      case '2y':
        return round($price + ($price * 1 * (1 - self::$twoYearDiscount)));
      case '3y':
        return round($price + ($price * 2 * (1 - self::$threeYearDiscount)));
      default:
        return $price;
    }
  }

  private static function getMultiyearDailyRate(int $price, string $period): float
  {
    $days = match ($period) {
      '2y' => 730,
      '3y' => 1095,
      default => 365,
    };
    return self::getMultiyearFullPrice($price, $period) / $days;
  }

  public static function getPriceForPeriod(int $plan, string $period): int
  {
    $planPrice = self::$originalPrices[$plan];
    return self::getMultiyearFullPrice($planPrice, $period);
  }

  private static function init(?int $remainingDays = null, ?int $currentPlanLevel = null, ?array $promotions = null, ?array $globalPromotionDiscount = [], ?string $currentPeriod = '1y'): void
  {
    // Calculate the price based on the level and days remaining
    // Take into account special cases like NEW, MODERATOR, ADMIN
    switch ($currentPlanLevel) {
      case self::NEW:
      case self::MODERATOR:
      case self::ADMIN:
      case null:
        $credit = 0;
        $remainingDays = 0;
        break;
      default:
        $credit = self::getPriceForPeriod($currentPlanLevel, $currentPeriod);
        if ($remainingDays <= 30) { // Only 30 days remain
          $credit = 0; // Negate the full current plan price
          $remainingDays = 0;
        } else {
          $dailyRate = self::getMultiyearDailyRate(self::$originalPrices[$currentPlanLevel], $currentPeriod);
          $credit = (int)round($dailyRate * $remainingDays);
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

    // Purist plan
    if (
      ($currentPlanLevel === null || $currentPlanLevel === 0) ||
      ($currentPlanLevel >= self::PURIST && $currentPlanLevel < self::ADVANCED)
    ) {
      self::$PLANS[self::PURIST] = new Plan(
        id: self::PURIST,
        name: 'Purist',
        image: 'https://cdn.nostr.build/assets/signup/pro.png',
        imageAlt: 'pur plan image',
        price: self::$originalPrices[self::PURIST],
        bonusCredits: 0,
        features: [
          '<b>' . SiteConfig::STORAGE_LIMITS[self::PURIST]['message'] . '</b> of private storage',
          SiteConfig::PURIST_PER_FILE_UPLOAD_LIMIT/1024/1024 . 'MB per file upload limit',
          'Only images and video common formats supported',
          'Add/Move/Delete your media',
          'Share media direct to Nostr',
          'Global, lightning fast CDN',
          'Detailed stats on all media',
        ],
        currency: 'USD',
        remainingDays: $remainingDays,
        fromPlanLevel: $currentPlanLevel,
        credit: $credit,
        currentPeriod: $currentPeriod,
      );
    };
    
    // Proffessional plan
    if (
      ($currentPlanLevel === null || $currentPlanLevel === 0) ||
      ($currentPlanLevel >= self::PROFESSIONAL && $currentPlanLevel < self::ADVANCED)
    ) {
      self::$PLANS[self::PROFESSIONAL] = new Plan(
        id: self::PROFESSIONAL,
        name: 'Professional',
        image: 'https://cdn.nostr.build/assets/signup/pro.png',
        imageAlt: 'pro plan image',
        price: self::$originalPrices[self::PROFESSIONAL],
        bonusCredits: 250,
        features: [
          '<b>' . SiteConfig::STORAGE_LIMITS[self::PROFESSIONAL]['message'] . '</b> of private storage',
          'PDF and SVG file support',
          'Add/Move/Delete your media',
          'Share media direct to Nostr',
          'Global, lightning fast CDN',
          'Detailed stats on all media',
        ],
        currency: 'USD',
        remainingDays: $remainingDays,
        fromPlanLevel: $currentPlanLevel,
        credit: $credit,
        currentPeriod: $currentPeriod,
      );
    };

    // Creator plan
    if (
      ($currentPlanLevel === null || $currentPlanLevel === 0) ||
      ($currentPlanLevel >= self::CREATOR && $currentPlanLevel < self::ADVANCED)
    ) {
      self::$PLANS[self::CREATOR] = new Plan(
        id: self::CREATOR,
        name: 'Creator',
        price: self::$originalPrices[self::CREATOR],
        bonusCredits: 500,
        features: [
          '<b>' . SiteConfig::STORAGE_LIMITS[self::CREATOR]['message'] . '</b> of private storage',
          'ZIP, PDF, and SVG file support',
          'AI Studio (Text-to-Image)',
          '<a class="ref_link" target="_blank" href="https://nostr.build/creators/">Host a Creators page</a></b>',
          'S3 backup for all media',
          'All Professional features',
        ],
        currency: 'USD',
        remainingDays: $remainingDays,
        fromPlanLevel: $currentPlanLevel,
        credit: $credit,
        currentPeriod: $currentPeriod,
      );
    };

    // Advanced plan
    // New numbering for the advanced plan, hence the less than or equal
    if (
      ($currentPlanLevel === null || $currentPlanLevel === 0) ||
      ($currentPlanLevel <= self::ADVANCED)
    ) {
      self::$PLANS[self::ADVANCED] = new Plan(
        id: self::ADVANCED,
        name: 'Advanced',
        price: self::$originalPrices[self::ADVANCED],
        bonusCredits: 1000,
        features: [
          '<b>' . SiteConfig::STORAGE_LIMITS[self::ADVANCED]['message'] . '</b> of private storage',
          'AI Studio Extended Access',
          'NIP-05 @nostr.build',
          'Expandable storage *',
          'All Creator features',
        ],
        currency: 'USD',
        remainingDays: $remainingDays,
        fromPlanLevel: $currentPlanLevel,
        credit: $credit,
        currentPeriod: $currentPeriod,
      );
    };

    // TODO: Make promotion applicable to upgrades as well
    if (
      is_array($promotions) &&
      !empty($promotions) &&
      ($remainingDays === null || $remainingDays === 0 || $currentPlanLevel === null) &&
      empty($globalPromotionDiscount)
    ) {
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

    // Apply global promotion if there is one that is more than 0 to all plans and 
    if(!empty($globalPromotionDiscount) && $globalPromotionDiscount['promotion_percentage'] > 0) {
      foreach (self::$PLANS as $plan) {
        // Skip promotion for the current plan
        if ($plan->isCurrentPlan) {
          continue;
        }
        $plan->priceInt = $plan->priceInt * (1 - $globalPromotionDiscount['promotion_percentage'] / 100);
        $plan->price = number_format($plan->priceInt, 0, '.', ',');
        // Apply the promotion to the multi-year plans as well
        $plan->priceInt2y = $plan->priceInt2y * (1 - $globalPromotionDiscount['promotion_percentage'] / 100);
        $plan->price2y = number_format($plan->priceInt2y, 0, '.', ',');
        $plan->priceInt3y = $plan->priceInt3y * (1 - $globalPromotionDiscount['promotion_percentage'] / 100);
        $plan->price3y = number_format($plan->priceInt3y, 0, '.', ',');
        // Update fullPrice as well
        /* BROKEN
        $plan->fullPriceInt = $plan->fullPriceInt * (1 - $globalPromotionDiscount['promotion_percentage'] / 100);
        $plan->fullPrice = number_format($plan->fullPriceInt, 0, '.', ',');
        $plan->full2yPriceInt = $plan->full2yPriceInt * (1 - $globalPromotionDiscount['promotion_percentage'] / 100);
        $plan->full2yPrice = number_format($plan->full2yPriceInt, 0, '.', ',');
        $plan->full3yPriceInt = $plan->full3yPriceInt * (1 - $globalPromotionDiscount['promotion_percentage'] / 100);
        $plan->full3yPrice = number_format($plan->full3yPriceInt, 0, '.', ',');
        */
        // Prepend the promotion to the features with the end date
        array_unshift($plan->features, "<span class=\"promotion_text\">Save {$globalPromotionDiscount['promotion_percentage']}% with {$globalPromotionDiscount['promotion_name']}!</span>");
        // Indicate that there is a promotion
        $plan->promo = true;
        $plan->discountPercentage = $globalPromotionDiscount['promotion_percentage'];
        self::$PLANS[$plan->id] = $plan;
      }
    }
  }

  public static function isValidPlan(int $plan): bool
  {
    return array_key_exists($plan, self::$PLANS);
  }

  public static function getInstance(?int $remainingDays = null, ?int $currentPlanLevel = null, ?array $promotions = null, ?array $globalPromotionDiscount = [], ?string $period = '1y', ?string $currentPeriod = '1y'): Plans
  {
    if (self::$instance === null) {
      self::$instance = new Plans();
      self::$instance::init($remainingDays, $currentPlanLevel, $promotions, $globalPromotionDiscount, $currentPeriod);
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
