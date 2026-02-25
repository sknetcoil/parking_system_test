<?php

namespace App\Managers;

use App\Core\Database;
use PDO;
use Exception;
use InvalidArgumentException;
use DateTimeImmutable;

class ReservationManager
{
    private PDO $db;
    private TimeSlotManager $timeSlotManager;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->timeSlotManager = new TimeSlotManager();
    }

    public function create(int $userId, int $spotId, string $startTime, string $endTime): ?int
    {
        $this->assertValidSlotWindow($startTime, $endTime);

        try {
            $this->db->beginTransaction();

            // Lock the spot to prevent double booking. This forces requests to serialize
            $stmt = $this->db->prepare("
                SELECT id FROM parking_spots WHERE id = ? FOR UPDATE
            ");
            $stmt->execute([$spotId]);
            $spot = $stmt->fetch();

            if (!$spot) {
                throw new Exception("Parking spot not found.");
            }

            // Check if already booked with overlapping time bounds using pessimistic lock on reservations side
            $stmt = $this->db->prepare("
                SELECT id FROM reservations 
                WHERE spot_id = ? 
                AND status = 'Booked' 
                AND (start_time < ? AND end_time > ?)
                FOR UPDATE
            ");
            $stmt->execute([$spotId, $endTime, $startTime]);
            if ($stmt->fetch()) {
                throw new Exception("Parking spot is already booked for this time slot.");
            }

            // Create reservation
            $stmt = $this->db->prepare("
                INSERT INTO reservations (user_id, spot_id, start_time, end_time, status)
                VALUES (?, ?, ?, ?, 'Booked') RETURNING id
            ");
            $stmt->execute([$userId, $spotId, $startTime, $endTime]);
            $id = $stmt->fetchColumn();

            $this->db->commit();
            return $id;
        }
        catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function assertValidSlotWindow(string $startTime, string $endTime): void
    {
        $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $startTime);
        $end = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $endTime);

        if (!$start || !$end) {
            throw new InvalidArgumentException('Invalid datetime format. Expected YYYY-MM-DD HH:MM:SS');
        }

        if ($start >= $end) {
            throw new InvalidArgumentException('start_time must be earlier than end_time.');
        }

        if ($start->format('Y-m-d') !== $end->format('Y-m-d')) {
            throw new InvalidArgumentException('Reservation must start and end on the same day.');
        }

        $slotStart = $start->format('H:i:s');
        $slotEnd = $end->format('H:i:s');

        if (!$this->timeSlotManager->existsForRange($slotStart, $slotEnd)) {
            throw new InvalidArgumentException('Reservation must match one of the configured parking slots.');
        }
    }

    public function complete(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE reservations
            SET status = 'Completed'
            WHERE id = ? AND user_id = ? AND status = 'Booked'
        ");
        $stmt->execute([$id, $userId]);
        return $stmt->rowCount() > 0;
    }

    public function getActiveReservations(): array
    {
        $stmt = $this->db->query("SELECT * FROM reservations WHERE status = 'Booked'");
        return $stmt->fetchAll();
    }

    public function autoReleaseExpired(): array
    {
        $this->db->beginTransaction();

        try {
            $select = $this->db->query("
                SELECT id, spot_id
                FROM reservations
                WHERE status = 'Booked' AND end_time < CURRENT_TIMESTAMP
                FOR UPDATE
            ");
            $expired = $select->fetchAll();

            if (empty($expired)) {
                $this->db->commit();
                return [];
            }

            $ids = array_map(static fn(array $row): int => (int)$row['id'], $expired);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            $update = $this->db->prepare("
                UPDATE reservations
                SET status = 'Completed'
                WHERE id IN ($placeholders) AND status = 'Booked'
            ");
            $update->execute($ids);

            $this->db->commit();
            return $expired;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
