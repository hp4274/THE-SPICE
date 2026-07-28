<?php
// admin/services/NotificationSocket.php
namespace Admin\Services;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class NotificationSocket implements MessageComponentInterface {
    protected \SplObjectStorage $clients;

    public function __construct() {
        $this->clients = new \SplObjectStorage();
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "[" . date('Y-m-d H:i:s') . "] New Admin Connection! (ID: {$conn->resourceId})\n";
        
        $conn->send(json_encode([
            'event' => 'connected',
            'message' => 'Connected to The Spice Live Notification Engine'
        ]));
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        if (!$data || !isset($data['event'])) {
            return;
        }

        echo "[" . date('Y-m-d H:i:s') . "] Inbound Event: {$data['event']}\n";

        if ($data['event'] === 'ping') {
            $from->send(json_encode(['event' => 'pong']));
            return;
        }

        // Broadcast payload to all connected admin sessions
        $this->broadcast($data);
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "[" . date('Y-m-d H:i:s') . "] Connection (ID: {$conn->resourceId}) closed\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "[" . date('Y-m-d H:i:s') . "] Error: {$e->getMessage()}\n";
        $conn->close();
    }

    public function broadcast(array $payload) {
        $json = json_encode($payload);
        foreach ($this->clients as $client) {
            $client->send($json);
        }
    }
}
