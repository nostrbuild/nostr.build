<?php

// Include autoload file.
require __DIR__ . '/../vendor/autoload.php';

// Import Subscriptions client class.
use BTCPayServer\Client\Subscriptions;

// Fill in with your BTCPay Server data.
$apiKey = '';
$host = ''; // e.g. https://your.btcpay-server.tld
$storeId = '';

// Create the subscriptions client.
try {
    $client = new Subscriptions($host, $apiKey);

    echo "=== BTCPay Server Subscriptions API Examples ===\n\n";

    // 1. Create a new offering
    echo "1. Creating a new offering...\n";
    $offering = $client->createOffering(
        $storeId,
        'Premium SaaS App',
        'https://example.com/success',
        [
            'category' => 'saas',
            'region' => 'us',
            'version' => '1.0'
        ],
        [
            ['id' => 'feature-analytics', 'description' => 'Advanced analytics dashboard'],
            ['id' => 'feature-support', 'description' => '24/7 priority support'],
            ['id' => 'feature-api', 'description' => 'Unlimited API access']
        ]
    );
    echo "Offering created with ID: " . $offering->getId() . "\n";
    echo "App Name: " . $offering->getAppName() . "\n\n";

    $offeringId = $offering->getId();

    // 2. Create plans for the offering
    echo "2. Creating plans for the offering...\n";

    // Basic plan
    $basicPlan = $client->createOfferingPlan(
        $storeId,
        $offeringId,
        'Basic monthly subscription with essential features',
        'USD',
        7,
        'Basic Plan',
        true,
        '1.99',
        true,
        null,
        ['tier' => 'basic'],
        'Monthly',
        ['feature-analytics']
    );
    echo "Basic plan created with ID: " . $basicPlan->getId() . "\n";

    // Premium plan
    $premiumPlan = $client->createOfferingPlan(
        $storeId,
        $offeringId,
        'Premium monthly subscription with all features',
        'USD',
        7,
        'Premium Plan',
        true,
        '29.99',
        true,
        14,
        ['tier' => 'premium'],
        'Monthly',
        ['feature-analytics', 'feature-support', 'feature-api']
    );
    echo "Premium plan created with ID: " . $premiumPlan->getId() . "\n\n";

    $basicPlanId = $basicPlan->getId();
    $premiumPlanId = $premiumPlan->getId();

    // 3. Get all offerings for the store
    echo "3. Getting all offerings for the store...\n";
    $offerings = $client->getOfferings($storeId);
    foreach ($offerings->all() as $off) {
        echo "- Offering: " . $off->getAppName() . " (ID: " . $off->getId() . ")\n";
        foreach ($off->getPlans() as $plan) {
            echo "  - Plan: " . $plan->getName() . " - " . $plan->getPrice() . " " . $plan->getCurrency() . "/" . $plan->getRecurringType() . "\n";
        }
    }
    echo "\n";

    // 4. Get a specific offering
    echo "4. Getting specific offering details...\n";
    $specificOffering = $client->getOffering($storeId, $offeringId);
    echo "Offering: " . $specificOffering->getAppName() . "\n";
    echo "Success URL: " . $specificOffering->getSuccessRedirectUrl() . "\n";
    echo "Number of plans: " . count($specificOffering->getPlans()) . "\n\n";

    // 5. Get a specific plan
    echo "5. Getting specific plan details...\n";
    $specificPlan = $client->getOfferingPlan($storeId, $offeringId, $basicPlanId);
    echo "Plan: " . $specificPlan->getName() . "\n";
    echo "Price: " . $specificPlan->getPrice() . " " . $specificPlan->getCurrency() . "\n";
    echo "Trial Days: " . $specificPlan->getTrialDays() . "\n";
    echo "Features: " . implode(', ', $specificPlan->getFeatures()) . "\n\n";

    // 6. Create a plan checkout session
    echo "6. Creating a plan checkout session...\n";
    $checkout = $client->createPlanCheckout(
        $storeId,
        $offeringId,
        $basicPlanId,
        null, // If the customer already exists on BTCPay, fill the email or other id here.
        60,
        'SoftMigration',
        ['source' => 'web'],
        ['campaign' => 'summer2026'],
        ['flow' => 'new_signup'],
        false,
        null, // You can override the plan price here if you want to force more credit or custom amount.
        'https://example.com/welcome',
        'test@example.com' // This is optional and will prefill the checkout page with the email.
    );
    echo "Checkout created with ID: " . $checkout->getId() . "\n";
    echo "Checkout URL: " . $checkout->getUrl() . "\n";
    echo "Is Trial: " . ($checkout->isTrial() ? 'Yes' : 'No') . "\n";
    echo "New Subscriber: " . ($checkout->isNewSubscriber() ? 'Yes' : 'No') . "\n\n";

    $checkoutId = $checkout->getId();

    // 7. Get plan checkout details
    echo "7. Getting plan checkout details...\n";
    $checkoutDetails = $client->getPlanCheckout($checkoutId);
    echo "Checkout ID: " . $checkoutDetails->getId() . "\n";
    echo "Plan: " . $checkoutDetails->getPlan()->getName() . "\n";
    $subscriber = $checkoutDetails->getSubscriber();
    if ($subscriber && $subscriber->getCustomer()->getIdentities()) {
        echo "Subscriber Email: " . ($subscriber->getCustomer()->getIdentities()['Email'] ?? 'N/A') . "\n";
    }
    echo "\n";

    // 8. Subscriber management examples
    /*
    // Fill these variables with actual values to test subscriber operations
    $offeringId = ''; // e.g. "offering_GFbMSBpybM6i5uEiqc"
    $customerSelector = ''; // e.g. "ps_N71XxcPDnKNgNDxKHZ" or customer email
    $suspensionReason = 'User requested cancellation';

    if (!empty($storeId) && !empty($offeringId) && !empty($customerSelector)) {
        try {
            // Get subscriber details
            echo "8. Getting subscriber details...\n";
            $subscriber = $client->getSubscriber($storeId, $offeringId, $customerSelector);
            echo "Customer ID: " . $subscriber->getCustomer()->getId() . "\n";
            echo "Active: " . ($subscriber->isActive() ? 'Yes' : 'No') . "\n";
            echo "Phase: " . $subscriber->getPhase() . "\n";
            echo "Created: " . date('Y-m-d H:i:s', $subscriber->getCreated()) . "\n";
            echo "\n";

            // Suspend subscriber
            if (!empty($suspensionReason)) {
                echo "9. Suspending subscriber...\n";
                $client->suspendSubscriber($storeId, $offeringId, $customerSelector, $suspensionReason);
                echo "Subscriber suspended successfully!\n";

                // Check status after suspension
                $suspendedSubscriber = $client->getSubscriber($storeId, $offeringId, $customerSelector);
                echo "Status after suspension: " . ($suspendedSubscriber->isActive() ? 'Active' : 'Suspended') . "\n";
                echo "Suspension reason: " . ($suspendedSubscriber->getSuspensionReason() ?? 'N/A') . "\n\n";

                // Unsuspend subscriber
                echo "10. Unsuspending subscriber...\n";
                $client->unsuspendSubscriber($storeId, $offeringId, $customerSelector);
                echo "Subscriber unsuspended successfully!\n";

                // Check status after unsuspending
                $reactivatedSubscriber = $client->getSubscriber($storeId, $offeringId, $customerSelector);
                echo "Status after unsuspending: " . ($reactivatedSubscriber->isActive() ? 'Active' : 'Suspended') . "\n";
                echo "Suspension reason: " . ($reactivatedSubscriber->getSuspensionReason() ?? 'N/A') . "\n\n";
            }

        } catch (\Throwable $e) {
            echo "Error in subscriber management: " . $e->getMessage() . "\n";
        }
    } else {
        echo "8. Subscriber management examples skipped - please fill in storeId, offeringId, and customerSelector variables\n";
    }
    */

    echo "=== Examples completed successfully! ===\n";

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
