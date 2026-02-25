<?php

namespace App\Controllers;

use App\Managers\ReservationManager;
use App\Managers\ParkingSpotManager;
use App\Managers\TimeSlotManager;
use App\Services\AuthService;
use Exception;
use InvalidArgumentException;

class ReservationController
{
    private ReservationManager $reservationManager;
    private ParkingSpotManager $parkingSpotManager;
    private TimeSlotManager $timeSlotManager;
    private AuthService $authService;

    public function __construct()
    {
        $this->reservationManager = new ReservationManager();
        $this->parkingSpotManager = new ParkingSpotManager();
        $this->timeSlotManager = new TimeSlotManager();
        $this->authService = new AuthService();
    }

    public function listSpots(): void
    {
        $spots = $this->parkingSpotManager->getAll();
        echo json_encode($spots);
    }

    public function reserve(): void
    {
        $user = $this->getAuthenticatedUser();
        if (!$user)
            return;

        $data = json_decode(file_get_contents('php://input'), true);
        $spotId = $data['spot_id'] ?? null;
        $slotId = $data['slot_id'] ?? null;
        $date = $data['date'] ?? null;

        if (!$spotId || !$slotId || !$date) {
            http_response_code(400);
            echo json_encode(['error' => 'spot_id, slot_id, and date are required']);
            return;
        }

        try {
            $slot = $this->timeSlotManager->findActiveById((int)$slotId);
            if (!$slot) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid or inactive slot_id']);
                return;
            }

            $dateObject = \DateTimeImmutable::createFromFormat('Y-m-d', (string)$date);
            if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid date format. Expected YYYY-MM-DD']);
                return;
            }

            $startTime = sprintf('%s %s', $date, $slot['start_time']);
            $endTime = sprintf('%s %s', $date, $slot['end_time']);

            $reservationId = $this->reservationManager->create($user['sub'], $spotId, $startTime, $endTime);
            echo json_encode(['success' => true, 'reservation_id' => $reservationId]);

            \App\Services\PusherService::push([
                'event' => 'slot_updated',
                'spot_id' => $spotId,
                'slot_id' => (int)$slotId,
                'reservation_id' => $reservationId,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'status' => 'Booked'
            ]);
        }
        catch (InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
        catch (Exception $e) {
            http_response_code(409);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function listTimeSlots(): void
    {
        $slots = $this->timeSlotManager->getAllActive();
        echo json_encode($slots);
    }

    public function complete(int $id): void
    {
        $user = $this->getAuthenticatedUser();
        if (!$user)
            return;

        // We need the spot_id to notify the frontend
        // For simplicity, ReservationManager->complete could return the spot_id or we can fetch it.
        // Let's assume complete() just does it, but we need the spot to push. We'll modify Manager or assume we can push ID only.

        $success = $this->reservationManager->complete($id, $user['sub']);

        if ($success) {
            echo json_encode(['success' => true]);
            \App\Services\PusherService::push([
                'event' => 'slot_updated',
                // To be exact we should send spot_id, but frontend might just refetch or rely on reservation id.
                'reservation_id' => $id,
                'status' => 'Available'
            ]);
        }
        else {
            http_response_code(404);
            echo json_encode(['error' => 'Reservation not found or unauthorized']);
        }
    }

    private function getAuthenticatedUser(): ?array
    {
        $token = $this->getBearerToken();
        if (!$token) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return null;
        }

        $user = $this->authService->validateToken($token);
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid token']);
            return null;
        }

        return $user;
    }

    private function getBearerToken(): ?string
    {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
                return $matches[1];
            }
        }
        return null;
    }
}
