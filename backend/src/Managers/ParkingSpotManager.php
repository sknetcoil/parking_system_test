<?php

namespace App\Managers;

use App\Core\Database;
use PDO;

class ParkingSpotManager
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("
            SELECT id, spot_number FROM parking_spots ORDER BY spot_number
        ");
        $spots = $stmt->fetchAll();

        // Fetch active reservations to attach to spots
        $stmtRes = $this->db->query("
            SELECT id, spot_id, user_id, start_time, end_time, status
            FROM reservations
            WHERE status = 'Booked'
        ");
        $reservations = $stmtRes->fetchAll();

        // Group reservations by spot_id
        $groupedReservations = [];
        foreach ($reservations as $res) {
            $groupedReservations[$res['spot_id']][] = $res;
        }

        // Attach to spots
        foreach ($spots as &$spot) {
            $spot['reservations'] = $groupedReservations[$spot['id']] ?? [];
        }

        return $spots;
    }
}
