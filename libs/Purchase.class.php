<?php
require_once __DIR__ . '/BTCPayClient.class.php';
require_once __DIR__ . '/Plans.class.php';

class Purchase
{
  private int $step;
  private int $selectedPlan;
  private array $steps = [
    ["Choose a Plan", 1],
    ["Create Your Account", 2],
    ["Pay the Fee", 3],
    ["Done", 4],
  ];
  private BTCPayClient $btcpayClient;
  private Plans $plans;

  public function __construct(BTCPayClient $btcpayClient, Plans $plans)
  {
    $this->btcpayClient = $btcpayClient;
    $this->plans = $plans;
    $this->selectedPlan = $this->getSelectedPlan();

    $this->validateStep($this->getStep());
    $_SESSION['purchase_step'] = $this->step; // Storing the step in the session
  }

  private function validateStep(int $step): void
  {
    switch ($step) {
      case 1:
        $target = $this->validateStep1();
        if ($target === $step) return;
        $this->step = $target;
        $this->validateStep($this->step);
        break;
      case 2:
        $target = $this->validateStep2();
        if ($target === $step) return;
        $this->step = $target;
        $this->validateStep($this->step);
        break;
      case 3:
        $target = $this->validateStep3();
        if ($target === $step) return;
        $this->step = $target;
        $this->validateStep($this->step);
        break;
      case 4:
        $target = $this->validateStep4();
        if ($target === $step) return;
        $this->step = $target;
        $this->validateStep($this->step);
        break;
      default:
        throw new Exception("Invalid step: " . $this->step);
    }
  }

  private function validateStep1(): int
  {
    // If the signup is finished, the step is not valid
    if (isset($_SESSION['purchase_finished'])) return 4;
    // Get the default plan or the plan from the session
    return 1;
  }

  private function validateStep2(): int
  {
    // If the signup is finished, the step is not valid
    if (isset($_SESSION['purchase_finished'])) return 4;
    // Step two is responsible for setting the plan in the session
    if (isset($_GET['plan']) && is_numeric($_GET['plan']) && $this->plans::isValidPlan($_GET['plan'])) {
      $_SESSION['purchase_plan'] = $_GET['plan'];
    }
    // Set of requirements for the step to be valid, otherwise fail validation
    // If the plan is not set, the step is not valid
    if (!isset($_SESSION['purchase_plan'])) return 1;
    // If the user is already created, the step is not valid
    if (isset($_SESSION['purchase_npub'])) return 3;
    return 2;
  }

  private function validateStep3(): int
  {
    // If the signup is finished, the step is not valid
    if (isset($_SESSION['purchase_finished'])) return 4;
    // If the plan is not set, the step is not valid
    if (!isset($_SESSION['purchase_plan'])) return 1;
    // If the user is not created, the step is not valid
    if (!isset($_SESSION['purchase_npub'])) return 2;

    // Persist expected order type based on stable purchase origin and selected plan
    $_SESSION['purchase_order_type'] = $this->resolveExpectedOrderType();

    // Check if the invoice is already created and settled
    if (isset($_SESSION['purchase_invoiceId'])) {
      if ($this->checkInvoiceSettled()) {
        $_SESSION['purchase_finished'] = true;
        return 4;
      }
    }
    return 3;
  }

  private function validateStep4(): int
  {
    if (!isset($_SESSION['purchase_plan'])) return 1;
    if (!isset($_SESSION['purchase_npub'])) return 2;
    if (!isset($_SESSION['purchase_invoiceId'])) return 3;
    // Validate payment settled
    if ($this->checkInvoiceSettled()) {
      $_SESSION['purchase_finished'] = true;
    } else {
      return 3;
    }
    return 4;
  }

  private function checkInvoiceSettled(): bool
  {
    $invoiceId = $_SESSION['purchase_invoiceId'];
    try {
      $invoice = $this->btcpayClient->getInvoice($invoiceId);
      if (!$invoice->isSettled()) {
        return false;
      }

      $invoiceData = $invoice->getData();
      $metadata = $invoiceData['metadata'] ?? [];

      $invoiceNpub = $metadata['userNpub'] ?? null;
      $invoicePlan = isset($metadata['plan']) ? (int)$metadata['plan'] : null;
      $invoicePeriod = $metadata['orderPeriod'] ?? null;
      $invoiceOrderType = $metadata['orderType'] ?? null;
      $invoicePurchasePrice = $metadata['purchasePrice'] ?? null;

      $sessionNpub = $_SESSION['purchase_npub'] ?? null;
      $sessionPlan = isset($_SESSION['purchase_plan']) ? (int)$_SESSION['purchase_plan'] : null;
      $sessionPeriod = $_SESSION['purchase_period'] ?? null;
      $sessionOrderType = $_SESSION['purchase_order_type'] ?? $this->resolveExpectedOrderType();

      if (
        empty($sessionNpub) ||
        $sessionPlan === null ||
        empty($sessionPeriod) ||
        empty($sessionOrderType) ||
        $invoiceNpub !== $sessionNpub ||
        $invoicePlan !== $sessionPlan ||
        $invoicePeriod !== $sessionPeriod ||
        $invoiceOrderType !== $sessionOrderType
      ) {
        return false;
      }

      if (!is_string($invoicePurchasePrice) && !is_numeric($invoicePurchasePrice)) {
        return false;
      }

      if (!BTCPayClient::amountEqualString((string)$invoicePurchasePrice, $invoice->getAmount())) {
        return false;
      }

      return true;
    } catch (Exception $e) {
      // Log the error
      error_log($e->getMessage());
    }
    return false;
  }

  private function resolveExpectedOrderType(): string
  {
    $origin = $_SESSION['purchase_origin'] ?? null;
    if ($origin === 'existing-user') {
      $sessionPlan = isset($_SESSION['purchase_plan']) ? (int)$_SESSION['purchase_plan'] : null;
      $sessionLevel = isset($_SESSION['acctlevel']) ? (int)$_SESSION['acctlevel'] : null;

      if ($sessionPlan !== null && $sessionLevel !== null) {
        return $sessionLevel === $sessionPlan ? 'renewal' : 'upgrade';
      }
    }
    return 'signup';
  }

  private function getStep(): int
  {
    $stepsCount = count($this->steps);
    $this->step = 1;
    if (isset($_GET['step']) && is_numeric($_GET['step'])) {
      $requestedStep = (int)$_GET['step'];
      if ($requestedStep >= 1 && $requestedStep <= $stepsCount) {
        $this->step = $requestedStep;
      }
    } elseif (isset($_SESSION['purchase_step'])) {
      $sessionStep = (int)$_SESSION['purchase_step'];
      if ($sessionStep >= 1 && $sessionStep <= $stepsCount) {
        $this->step = $sessionStep;
      }
    }
    return $this->step;
  }

  private function getSelectedPlan(): int
  {
    $planKeys = array_keys(Plans::$PLANS);
    $this->selectedPlan = empty($planKeys) ? 1 : (int)reset($planKeys);
    if (isset($_GET['plan']) && is_numeric($_GET['plan']) && $this->plans::isValidPlan($_GET['plan'])) {
      $this->selectedPlan = (int)$_GET['plan'];
    } elseif (isset($_SESSION['purchase_plan'])) {
      $sessionPlan = (int)$_SESSION['purchase_plan'];
      if ($this->plans::isValidPlan($sessionPlan)) {
        $this->selectedPlan = $sessionPlan;
      }
    }
    return $this->selectedPlan;
  }

  public function getStepInfo(): array
  {
    return $this->steps[$this->step - 1];
  }

  public function getSteps(): array
  {
    return $this->steps;
  }

  public function getBtcpayClient(): BTCPayClient
  {
    return $this->btcpayClient;
  }

  public function getValidatedStep(): int
  {
    return $this->step;
  }

  public function getSelectedPlanId(): int
  {
    return $this->selectedPlan;
  }
}
