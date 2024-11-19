<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/db/DatabaseTable.class.php';
require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use Respect\Validation\Validator as v;

/* SQL table definition
CREATE TABLE `promotions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `promotion_name` varchar(255) NOT NULL,
  `promotion_description` varchar(255) DEFAULT NULL,
  `promotion_start_time` datetime NOT NULL,
  `promotion_end_time` datetime NOT NULL,
  `promotion_percentage` int NOT NULL,
  `promotion_applicable_plans` varchar(255) NOT NULL,
  `promotion_created_at` datetime NOT NULL,
  `promotion_updated_at` datetime NOT NULL,
  `promotion_type` varchar(255) DEFAULT 'perPlan',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Indexes for table `promotions`
ALTER TABLE `promotions`
  ADD KEY `promotion_start_time` (`promotion_start_time`),
  ADD KEY `promotion_end_time` (`promotion_end_time`);
*/

class Promotions extends DatabaseTable
{
  public function __construct(mysqli $db)
  {
    parent::__construct($db, 'promotions');
    $this->requiredValidationRules = [
      'promotion_name' => v::notEmpty()->stringType()->length(null, 255),
      'promotion_start_time' => v::notEmpty()->stringType()->length(null, 255),
      'promotion_end_time' => v::notEmpty()->stringType()->length(null, 255),
      'promotion_percentage' => v::notEmpty()->intVal()->min(0)->max(50),
    ];
    $this->optionalValidationRules = [
      'id' => v::intVal(),
      'promotion_description' => v::optional(v::stringType()->length(null, 255)),
      'promotion_applicable_plans' => v::optional(v::stringType()->length(1, 255)),
    ];
  }

  public function addPromotion(array $promotionData): int
  {
    $promotionData['promotion_start_time'] = gmdate('Y-m-d H:i:s', strtotime($promotionData['promotion_start_time']));
    $promotionData['promotion_end_time'] = gmdate('Y-m-d H:i:s', strtotime($promotionData['promotion_end_time']));
    $promotionData['promotion_created_at'] = gmdate('Y-m-d H:i:s');
    $promotionData['promotion_updated_at'] = gmdate('Y-m-d H:i:s');
    return $this->insert($promotionData);
  }

  public function updatePromotion(int $id, array $promotionData): void
  {
    $promotionData['promotion_start_time'] = gmdate('Y-m-d H:i:s', strtotime($promotionData['promotion_start_time']));
    $promotionData['promotion_end_time'] = gmdate('Y-m-d H:i:s', strtotime($promotionData['promotion_end_time']));
    $promotionData['promotion_updated_at'] = gmdate('Y-m-d H:i:s');
    $this->update($id, $promotionData);
  }

  public function deletePromotion(int $id): int
  {
    return $this->delete($id);
  }

  public function getPromotionById(int $promotionId): array
  {
    $promotion = $this->get($promotionId);
    $promotion['promotion_applicable_plans'] = explode(',', $promotion['promotion_applicable_plans']);
    return $promotion;
  }

  public function getPromotionsByTime(string $date, string $promotion_type = 'perPlan'): array
  {
    $date = gmdate('Y-m-d H:i:s', strtotime($date));
    $query = "SELECT * FROM {$this->tableName} WHERE promotion_start_time <= ? AND promotion_end_time >= ? AND promotion_type = ? ORDER BY promotion_end_time desc";
    // Prepare the query
    $stmt = $this->db->prepare($query);
    $stmt->bind_param('sss', $date, $date, $promotion_type);
    $stmt->execute();
    $result = $stmt->get_result();
    $promotions = [];
    while ($row = $result->fetch_assoc()) {
      $row['promotion_applicable_plans'] = explode(',', $row['promotion_applicable_plans']);
      $promotions[] = $row;
    }
    // Close the statement
    $stmt->close();
    return $promotions;
  }

  public function getCurrentAndFuturePromotions(): array
  {
    $date = gmdate('Y-m-d H:i:s');
    $query = "SELECT * FROM {$this->tableName} WHERE promotion_end_time >= ? ORDER BY promotion_end_time desc";
    // Prepare the query
    $stmt = $this->db->prepare($query);
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $promotions = [];
    while ($row = $result->fetch_assoc()) {
      $row['promotion_applicable_plans'] = explode(',', $row['promotion_applicable_plans']);
      $promotions[] = $row;
    }
    // Close the statement
    $stmt->close();
    return $promotions;
  }

  public function getPastPromotions(): array
  {
    $date = gmdate('Y-m-d H:i:s');
    $query = "SELECT * FROM {$this->tableName} WHERE promotion_end_time <= ? ORDER BY promotion_end_time desc";
    // Prepare the query
    $stmt = $this->db->prepare($query);
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $promotions = [];
    while ($row = $result->fetch_assoc()) {
      $row['promotion_applicable_plans'] = explode(',', $row['promotion_applicable_plans']);
      $promotions[] = $row;
    }
    // Close the statement
    $stmt->close();
    return $promotions;
  }

  public function getCurrentPromotions(): array
  {
    return $this->getPromotionsByTime(gmdate('Y-m-d H:i:s'));
  }

  // Special promotion that applies to all plans for all types of transactions, e.g., new, renew, upgrade
  public function getGlobalPromotionDiscount(): array
  {
    return $this->getPromotionsByTime(gmdate('Y-m-d H:i:s'), 'global');
  }

  // Get the predominant promotion, prioritizing global promotions over perPlan promotions
  public function getAllCurrentPromotions(): array
  {
    $date = gmdate('Y-m-d H:i:s');
    $query = "SELECT * FROM {$this->tableName} WHERE promotion_start_time <= ? AND promotion_end_time >= ? ORDER BY promotion_type desc, promotion_end_time desc";
    // Prepare the query
    $stmt = $this->db->prepare($query);
    $stmt->bind_param('ss', $date, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $globalPromotions = [];
    $perPlanPromotions = [];
    while ($row = $result->fetch_assoc()) {
      $row['promotion_applicable_plans'] = explode(',', $row['promotion_applicable_plans']);
      if ($row['promotion_type'] === 'global') {
        $globalPromotions[] = $row;
      } else {
        $perPlanPromotions[] = $row;
      }
    }
    // DEBUG
    error_log('Global promotions: ' . json_encode($globalPromotions));
    error_log('Per plan promotions: ' . json_encode($perPlanPromotions));
    return [
      'global' => $globalPromotions,
      'perPlan' => $perPlanPromotions,
    ];
  }
}
