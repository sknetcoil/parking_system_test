<?php

namespace App\Services;

class PusherService
{
    public static function push(array $data): void
    {
        $fp = @stream_socket_client("tcp://websocket:8082", $errno, $errstr, 2);
        if (!$fp) {
            error_log("[PusherService] Failed to connect to WebSocket server: ($errno) $errstr");
            return;
        }
        fwrite($fp, json_encode($data));
        fclose($fp);
    }
}
