<?php
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServer;
use Ratchet\MessageComponentInterface;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;

require dirname( __FILE__ ) . '/vendor/autoload.php';

class Socket implements MessageComponentInterface {

    public function __construct()
    {
        $this->clients = [];
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $conn->send(<<<JSON
        {
            "type": "myId",
            "id": "{$conn->resourceId}"
        }
        JSON);

        $clientsJsonArray = json_encode(
            array_map(
                fn($client) => $client->resourceId,
                $this->clients
            )
        );

        $conn->send(<<<JSON
        {
            "type": "allConnections",
            "ids": $clientsJsonArray
        }
        JSON);

        $this->clients[] = $conn;

        foreach ($this->clients as $client)
        {
            $client->send(<<<JSON
            {
                "type": "newConnection",
                "id": "{$conn->resourceId}"
            }
            JSON);
        }
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        foreach ($this->clients as $client)
        {
            $client->send(<<<JSON
            {
                "type": "movement",
                "id": "{$from->resourceId}",
                "direction": "$msg"
            }
            JSON);
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients = array_filter(
            $this->clients,
            fn($client) => $client->resourceId != $conn->resourceId
        );
        foreach ($this->clients as $client)
        {
            $client->send(<<<JSON
            {
                "type": "deleteConnection",
                "id": "{$conn->resourceId}"
            }
            JSON);
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
    }
}

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new Socket()
        )
    ),
    8080
);

$server->run();
