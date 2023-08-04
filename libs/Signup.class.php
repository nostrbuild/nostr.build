<?php
require_once __DIR__ . '/BTCPayClient.class.php';
require_once __DIR__ . '/Plans.class.php';

class Signup
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
    $_SESSION['signup_step'] = $this->step; // Storing the step in the session
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
    if (isset($_SESSION['signup_finished'])) return 4;
    // Get the default plan or the plan from the session
    return 1;
  }

  private function validateStep2(): int
  {
    // If the signup is finished, the step is not valid
    if (isset($_SESSION['signup_finished'])) return 4;
    // Step two is responsible for setting the plan in the session
    if (isset($_GET['plan']) && is_numeric($_GET['plan']) && $this->plans::isValidPlan($_GET['plan'])) {
      $_SESSION['signup_plan'] = $_GET['plan'];
    }
    // Set of requirements for the step to be valid, otherwise fail validation
    // If the plan is not set, the step is not valid
    if (!isset($_SESSION['signup_plan'])) return 1;
    // If the user is already created, the step is not valid
    if (isset($_SESSION['signup_npub'])) return 3;
    return 2;
  }

  private function validateStep3(): int
  {
    // If the signup is finished, the step is not valid
    if (isset($_SESSION['signup_finished'])) return 4;
    // If the plan is not set, the step is not valid
    if (!isset($_SESSION['signup_plan'])) return 1;
    // If the user is not created, the step is not valid
    if (!isset($_SESSION['signup_npub'])) return 2;
    // Check if the invoice is already created and settled
    if (isset($_SESSION['signup_invoiceId'])) {
      if ($this->checkInvoiceSettled()) {
        $_SESSION['signup_finished'] = true;
        return 4;
      }
    }
    return 3;
  }

  private function validateStep4(): int
  {
    if (!isset($_SESSION['signup_plan'])) return 1;
    if (!isset($_SESSION['signup_npub'])) return 2;
    if (!isset($_SESSION['signup_invoiceId'])) return 3;
    // Validate payment settled
    if ($this->checkInvoiceSettled()) {
      $_SESSION['signup_finished'] = true;
    } else {
      return 3;
    }
    return 4;
  }

  private function checkInvoiceSettled(): bool
  {
    $invoiceId = $_SESSION['signup_invoiceId'];
    $invoice = $this->btcpayClient->getInvoice($invoiceId);
    if ($invoice->getStatus() === 'Settled') {
      return true;
    }
    return false;
  }

  private function getStep(): int
  {
    $stepsCount = count($this->steps);
    $this->step = 1;
    if (isset($_GET['step']) && is_numeric($_GET['step']) && $_GET['step'] <= $stepsCount) {
      $this->step = $_GET['step'];
    } elseif (isset($_SESSION['signup_step'])) {
      $this->step = $_SESSION['signup_step'];
    }
    return $this->step;
  }

  private function getSelectedPlan(): int
  {
    $this->selectedPlan = 2; // Default plan
    if (isset($_GET['plan']) && is_numeric($_GET['plan']) && $this->plans::isValidPlan($_GET['plan'])) {
      $this->selectedPlan = $_GET['plan'];
    } elseif (isset($_SESSION['signup_plan'])) {
      $this->selectedPlan = $_SESSION['signup_plan'];
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
