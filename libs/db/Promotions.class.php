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
    $promotionData['promotion_start_time'] = date('Y-m-d H:i:s', strtotime($promotionData['promotion_start_time']));
    $promotionData['promotion_end_time'] = date('Y-m-d H:i:s', strtotime($promotionData['promotion_end_time']));
    $promotionData['promotion_created_at'] = date('Y-m-d H:i:s');
    $promotionData['promotion_updated_at'] = date('Y-m-d H:i:s');
    return $this->insert($promotionData);
  }

  public function updatePromotion(int $id, array $promotionData): void
  {
    $promotionData['promotion_start_time'] = date('Y-m-d H:i:s', strtotime($promotionData['promotion_start_time']));
    $promotionData['promotion_end_time'] = date('Y-m-d H:i:s', strtotime($promotionData['promotion_end_time']));
    $promotionData['promotion_updated_at'] = date('Y-m-d H:i:s');
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

  public function getPromotionsByTime(string $date): array
  {
    $date = date('Y-m-d H:i:s', strtotime($date));
    $query = "SELECT * FROM {$this->tableName} WHERE promotion_start_time <= '{$date}' AND promotion_end_time >= '{$date}' ORDER BY promotion_end_time desc";
    $result = $this->db->query($query);
    $promotions = [];
    while ($row = $result->fetch_assoc()) {
      $row['promotion_applicable_plans'] = explode(',', $row['promotion_applicable_plans']);
      $promotions[] = $row;
    }
    return $promotions;
  }

  public function getCurrentAndFuturePromotions(): array
  {
    $date = date('Y-m-d H:i:s');
    $query = "SELECT * FROM {$this->tableName} WHERE promotion_end_time >= '{$date}' ORDER BY promotion_end_time desc";
    $result = $this->db->query($query);
    $promotions = [];
    while ($row = $result->fetch_assoc()) {
      $row['promotion_applicable_plans'] = explode(',', $row['promotion_applicable_plans']);
      $promotions[] = $row;
    }
    return $promotions;
  }

  public function getPastPromotions(): array
  {
    $date = date('Y-m-d H:i:s');
    $query = "SELECT * FROM {$this->tableName} WHERE promotion_end_time <= '{$date}' ORDER BY promotion_end_time desc";
    $result = $this->db->query($query);
    $promotions = [];
    while ($row = $result->fetch_assoc()) {
      $row['promotion_applicable_plans'] = explode(',', $row['promotion_applicable_plans']);
      $promotions[] = $row;
    }
    return $promotions;
  }

  public function getCurrentPromotions(): array
  {
    return $this->getPromotionsByTime(date('Y-m-d H:i:s'));
  }
}
