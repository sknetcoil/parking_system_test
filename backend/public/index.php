<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\AuthController;
use App\Controllers\ReservationController;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Simple Router
if ($uri === '/login' && $method === 'POST') {
    (new AuthController())->login();
}
elseif ($uri === '/me' && $method === 'GET') {
    (new AuthController())->me();
}
elseif ($uri === '/spots' && $method === 'GET') {
    (new ReservationController())->listSpots();
}
elseif ($uri === '/time-slots' && $method === 'GET') {
    (new ReservationController())->listTimeSlots();
}
elseif ($uri === '/reservations' && $method === 'POST') {
    (new ReservationController())->reserve();
}
elseif (preg_match('#^/reservations/(\d+)/complete$#', $uri, $matches) && $method === 'PUT') {
    (new ReservationController())->complete($matches[1]);
}
else {
    http_response_code(404);
    echo json_encode(['error' => 'Not Found']);
}
