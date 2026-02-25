<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Managers\ReservationManager;
use App\Services\PusherService;

$reservationManager = new ReservationManager();
$released = [];
try {
    $released = $reservationManager->autoReleaseExpired();
} catch (Exception $e) {
    echo "Error: {$e->getMessage()}\n";
    exit(1);
}

foreach ($released as $res) {
    echo "Auto-released Spot #{$res['spot_id']} (Reservation ID {$res['id']})\n";
    PusherService::push([
        'event' => 'slot_updated',
        'spot_id' => $res['spot_id'],
        'status' => 'Available',
    ]);
}

if (empty($released)) {
    echo "No stale reservations found.\n";
}