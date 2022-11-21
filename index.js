const WebSocketServer = require('websocket').server;
const http = require('http');

const server = http.createServer();
server.listen(8080);

const wsServer = new WebSocketServer({ httpServer: server });

let clients = [];
let lastId = 0;

wsServer.on('request', function (request) {
  const connection = request.accept();
  const id = (lastId++).toString();

  connection.send(JSON.stringify({ type: 'myId', id }));

  connection.send(JSON.stringify({
    type: 'allConnections',
    ids: clients.map(client => client.id)
  }));

  clients.push({ id, connection });

  for (const client of clients) {
    client.connection.send(
      JSON.stringify({ type: 'newConnection', id })
    );
  }

  connection.on('message', function (message) {
    for (const client of clients) {
      client.connection.send(
        JSON.stringify({ type: 'movement', id, direction: message.utf8Data })
      );
    }
  });

  connection.on('close', function () {
    clients = clients.filter(client => client.id != id);
    for (const client of clients) {
      client.connection.send(
        JSON.stringify({ type: 'deleteConnection', id })
      );
    }
  });
});