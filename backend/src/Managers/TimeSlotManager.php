<?php

namespace App\Managers;

use App\Core\Database;
use PDO;

class TimeSlotManager
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getAllActive(): array
    {
        $stmt = $this->db->query("
            SELECT id, label, start_time::text AS start_time, end_time::text AS end_time
            FROM time_slots
            WHERE is_active = TRUE
            ORDER BY start_time
        ");
        return $stmt->fetchAll();
    }

    public function existsForRange(string $startTime, string $endTime): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM time_slots
            WHERE is_active = TRUE
              AND start_time = ?
              AND end_time = ?
            LIMIT 1
        ");
        $stmt->execute([$startTime, $endTime]);
        return (bool)$stmt->fetchColumn();
    }

    public function findActiveById(int $slotId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, label, start_time::text AS start_time, end_time::text AS end_time
            FROM time_slots
            WHERE id = ? AND is_active = TRUE
            LIMIT 1
        ");
        $stmt->execute([$slotId]);
        $slot = $stmt->fetch();
        return $slot ?: null;
    }
}
