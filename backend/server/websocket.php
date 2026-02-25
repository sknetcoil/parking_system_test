<?php

namespace App;

require __DIR__ . '/../vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

class Broadcaster implements MessageComponentInterface
{
    protected \SplObjectStorage $clients;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        echo "Received message from client: $msg\n";
    }

    // Custom method to broadcast from our internal TCP server
    public function broadcast(string $msg)
    {
        foreach ($this->clients as $client) {
            $client->send($msg);
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }
}

$loop = \React\EventLoop\Loop::get();
$broadcaster = new Broadcaster();

// Set up WebSocket Server
$webSock = new \React\Socket\SocketServer('0.0.0.0:8081');
$server = new IoServer(
    new HttpServer(
    new WsServer($broadcaster)
    ),
    $webSock,
    $loop
    );

// Set up internal TCP Server for REST API/Cron to push messages
$internalSock = new \React\Socket\SocketServer('0.0.0.0:8082');
$internalSock->on('connection', function (\React\Socket\ConnectionInterface $conn) use ($broadcaster) {
    $conn->on('data', function ($data) use ($broadcaster) {
            // Broadcast the JSON data to all connected WS clients
            $broadcaster->broadcast($data);
        }
        );
    });

echo "WebSocket server started on port 8081\n";
echo "Internal TCP push server started on port 8082\n";
$loop->run();