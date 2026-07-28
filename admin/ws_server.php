<?php
// admin/ws_server.php
require dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/services/NotificationSocket.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Loop;
use React\Socket\SocketServer;
use Admin\Services\NotificationSocket;

$port = 8080;
$internalPort = 8081;

$socketComponent = new NotificationSocket();

// 1. WebSocket Server for Browser Admin Clients (port 8080)
$loop = Loop::get();
$webSocketServer = new SocketServer("0.0.0.0:{$port}", [], $loop);

$httpServer = new HttpServer(
    new WsServer(
        $socketComponent
    )
);

$ioServer = new IoServer($httpServer, $webSocketServer, $loop);

// 2. Internal TCP Listener for Backend Event Triggers (port 8081)
$internalServer = new SocketServer("127.0.0.1:{$internalPort}", [], $loop);
$internalServer->on('connection', function (React\Socket\ConnectionInterface $connection) use ($socketComponent) {
    $connection->on('data', function ($data) use ($socketComponent, $connection) {
        $payload = json_decode($data, true);
        if ($payload && isset($payload['event'])) {
            echo "[" . date('Y-m-d H:i:s') . "] Triggered Live Broadcast: {$payload['event']}\n";
            $socketComponent->broadcast($payload);
            $connection->write("OK\n");
        }
    });
});

echo "========================================================\n";
echo " THE SPICE RATCHET WEBSOCKET SERVER STARTED ON PORT {$port}\n";
echo " Internal Trigger Channel Listening on 127.0.0.1:{$internalPort}\n";
echo "========================================================\n";

$loop->run();
