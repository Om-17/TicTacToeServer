<?php

// if (extension_loaded('pdo_mysql')) {
//     echo "PDO MySQL extension is installed.";
// } else {
//     echo "PDO MySQL extension is NOT installed.";
// }

require __DIR__ . '../vendor/autoload.php';
require __DIR__ . '/config/initialize.php';
require __DIR__ . '/classes/DBclass.php';
require __DIR__ . '/libs/TokenGenerater.php';

use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Add this to your code to handle database connection

$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME;
$username = DB_USER;
$password = DB_PASSWORD;

try {
    $pdo = new PDO($dsn, $username, $password);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

class TicTacToeServer implements MessageComponentInterface
{
    private $secretKey = SecretKey;
    protected $connections = [];

    // protected $users_connections = [];
    protected $players_connections = [];
    protected $games;
    public function __construct()
    {
        $this->games = [];
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $token = $this->extractTokenFromQueryString($conn);
        if (!is_null($token)) {

            try {
                $user = $this->getUserFromToken($token);

                $this->connections[$conn->resourceId] = $conn;
             
            
                // $this->users_connections[$user['id']] = $conn; 
                $this->players_connections[] =['id'=>$user['id'],'conn'=>$conn];  
                
                
                echo "Connection {$conn->resourceId} (User: {$user['username']}, {$user['id']}) has connected\n";
                $user_obj = new DBClass('users');
                $user_obj->update('id', $user['id'], ['status' => 1]);
                $user_obj = null;
                $conn->send(json_encode(["message" => "Welcome to the WebSocket server!"]));
            } catch (\Exception $e) {
                // Token verification failed
                echo "Connection {$conn->resourceId} failed authentication\n";
                $conn->close();
            }
        }
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = json_decode($msg, true);
        print_r($data);

        if (isset($data['action'])) {
            switch ($data['action']) {
                case 'createRoom':

                    $token = $this->extractTokenFromQueryString($from);
                    $user = $this->getUserFromToken($token);

                    $roomCode = $this->createRoom($user['id'], $from);

                    break;
                case 'joinRoom':
                    $token = $this->extractTokenFromQueryString($from);

                    $user = $this->getUserFromToken($token);

                    $roomCode = $data['roomCode'];
                    echo $roomCode;
                    $this->joinRoom($from, $user['id'], $roomCode);

                    break;
                case 'leaveRoom':
                    unset($this->connections[$from->resourceId]);
                    $this->leaveRoom($from);


                default:
                    if (isset($data['roomCode'])) {
                        $roomCode = $data['roomCode'];

                        $this->startGame($from, $roomCode, $msg);
                    }

                    // Handle other actions
                    break;
            }
        } else {
            if (isset($data['roomCode'])) {
                $roomCode = $data['roomCode'];

                $this->startGame($from, $roomCode, $msg);
            }
        }

        // echo "Received message from client {$from->resourceId}: $msg\n";

        // foreach ($this->connections as $client) {
        //     if ($from !== $client) {
        //         $client->send("User {$from->resourceId}: $msg");
        //     }
        // }
    }

    public function onClose(ConnectionInterface $conn)
    {

        $userId = null;

        foreach ($this->players_connections as $key => $player) {
            if ($player['conn'] == $conn) {
                $previousPlayerId = $player['id'];
                unset($this->players_connections[$key]);
        
                // Check if the same player has another connection
                $hasOtherConnection = false;
                foreach ($this->players_connections as $otherPlayer) {
                    if ($otherPlayer['id'] == $previousPlayerId) {
                        $hasOtherConnection = true;
                        break;
                    }
                }
        
                // Update the player's status based on whether they have another connection
                $user_obj = new DBClass('users');
                $status = $hasOtherConnection ? true : false;
                $user_obj->update('id', $previousPlayerId, ['status' => $status]);
        
                break; // Stop the loop once the matching connection is found and removed
            }
        }
        
        

        unset($this->connections[$conn->resourceId]);
        // $this->leaveRoom($conn);

        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

    protected function extractTokenFromQueryString(ConnectionInterface $conn)
    {
        $queryString = $conn->httpRequest->getUri()->getQuery();

        parse_str($queryString, $params);
        return $params['token'] ?? null;
    }

    protected function checkuserstatus($userid)
    {
        if ($userid !== null) {
            $user_obj = new DBClass('users');
            $res_user = $user_obj->get('id', $userid);

            if (is_array($res_user) && isset($res_user['status'])) {

                return (bool) $res_user['status'];
            }
        }
        return false;
    }

    protected function getUserFromToken($token)
    {
        // Decode and verify the JWT token to get user information
        $decoded = Token::decodeToken($token, $this->secretKey);
        // Replace this with your actual user retrieval logic from the decoded token
        return [
            'id' => $decoded->userId,
            'username' => $decoded->username,
            // Add more user information as needed
        ];
    }

    protected function createRoom($userId, ConnectionInterface $conn)
    {
        $roomCode = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)); // Combining uniqid with random bytes


        $roomobj = new DBClass('rooms');
        $roomobjexist = $roomobj->exists('player1', $userId);

        if ($roomobjexist) {
            $result = $roomobj->delete('player1', $userId);
        }
        echo "Creating room for user" . $userId;
        // Insert the room into the database

        $result = $roomobj->create([
            "roomCode" => $roomCode, "player1" => $userId, 'player2' => null,
            'board' => "[['', '', ''], ['', '', ''], ['', '', '']]",
            'turn' => 1,
            'gameOver' => false,
            'chancesLeft' => 9,
        ]);
        // $roomId =$result['last_id'];
        print_r($result);
        $roomobj = null;
        $this->games[$roomCode] = [
            'player1' => $userId,
            'player2' => null,
            'board' => [['', '', ''], ['', '', ''], ['', '', '']],
            'turn' => 1,
            'gameOver' => false,
            'chancesLeft' => 9,
        ];
        $conn->send(json_encode(["createroomCode" => $roomCode]));
        echo "Room {$roomCode} created\n";
        return $roomCode;
    }

    protected function joinRoom(ConnectionInterface $conn, $userId, $roomCode)
    {
        // Check if the room exists
        $roomobj = new DBClass('rooms');
        $result = $roomobj->get('roomCode', $roomCode);

        // print_r($result['player1']);
        // echo(!isset($result['player1']));
        // echo  $this->checkuserstatus($result['player1']);
        // echo  $this->checkuserstatus($userId);
        // echo $this->checkuserstatus($result['player1']) &&  $this->checkuserstatus($userId)!=true;
        // echo !isset($result['player1']);
        // echo !$this->checkuserstatus($result['player1']) &&  !$this->checkuserstatus($userId);

        if (isset($result['player1'])) {
            if ($result['player1'] == $userId) {
                $conn->send(json_encode(["error" => "player 1 and player2 both are same."]));
                return;
            } elseif ($this->checkuserstatus($result['player1']) &&  $this->checkuserstatus($userId)) {
                if (!isset($result['player2'])) {
                    $roomobj->update('id', $result['id'], ['player2' => $userId]);
                     $roomId = $result['id'];
                    $user2 = new DBClass('users');
                    $resultuser2 = $user2->get('id', $userId);

                    echo "User id {$userId} joined room {$roomId}\n";

                    // Notify the other user in the room
                    $otherUserId = $result['player1'];
                    if ($otherUserId) {
                        $user1 = new DBClass('users');
                        $resultuser1 = $user1->get('id', $otherUserId);
                        $resultuser2 = $user1->get('id', $userId);
                        if (isset($resultuser1)) {
                            $user1name = $resultuser1['username'];
                            $user2name = $resultuser2['username'];

                            $conn->send(json_encode(["userjoined" => "Game Start Now", "player1name" => $user1name, "player2name" => $user2name, "roomCode" => $roomCode]));
                        }
                        if (isset($resultuser2)) {
                            $user1name = $resultuser1['username'];

                            $user2name = $resultuser2['username'];
                            foreach ($this->players_connections as $player) {
                                if ($player['id'] == $otherUserId) {
                                    $conn = $player['conn']; // Get the connection object
                                    $conn->send(json_encode([
                                        "userjoined" => "Game Start Now",
                                        "player1name" => $user1name,
                                        "player2name" => $user2name,
                                        "roomCode" => $roomCode
                                    ]));
                                    break; // Break the loop once the message is sent
                                }
                            }
                            
                        }

                        return;
                    }
                } else {
                    $conn->send(json_encode(["error" => "Room {$roomCode} is full"]));
                    return;
                }
            } else {
                $conn->send(json_encode(["status" => "other player are offline."]));
                return;
            }
        } else {
            $conn->send(json_encode(["error" => "Room {$roomCode} is  does not exist and try again"]));

            return;
        }
    }

    protected function leaveRoom(ConnectionInterface $conn)
    {
        $userId = null;

        

        foreach ($this->players_connections as $player) {
            if ($conn === $player['conn']) {
                $userId = $player['id'];
                break;
            }
        }
        

        if ($userId !== null) {

            global $pdo;

            // Check if the user is in any room
            $roomId = null;
            $stmt = $pdo->prepare("SELECT id, player1, player2 FROM rooms WHERE player1 = :userId OR player2 = :userId");
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                $roomId = $result['id'];

                // Remove the user from the room
                if ($result['player1'] == $userId) {
                    $stmt = $pdo->prepare("UPDATE rooms SET player1 = NULL WHERE id = :roomId");
                } elseif ($result['player2'] == $userId) {
                    $stmt = $pdo->prepare("UPDATE rooms SET player2 = NULL WHERE id = :roomId");
                }

                $stmt->bindParam(':roomId', $roomId, PDO::PARAM_INT);
                $stmt->execute();


                // Notify the other user in the room, if any
                $otherUserId = ($result['player1'] == $userId) ? $result['player2'] : $result['player1'];
                echo "User id {$userId} left room {$roomId}\n{$otherUserId}";

                if ($otherUserId) {
                    foreach ($this->players_connections as $player) {
                        if ($player['id'] == $otherUserId) {
                            $player['conn']->send(json_encode(["message" => "other player left the game."]));
                            break; // Stop the loop once the message is sent
                        }
                    }
                }
                
                $stmt = $pdo->prepare("SELECT id, player1, player2 FROM rooms WHERE player1 = :userId OR player2 = :userId");
                $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
                $stmt->execute();
                $getresult = $stmt->fetch(PDO::FETCH_ASSOC);
                // Check if both users have left, then delete the room
                print_r($getresult);

                if (is_null($getresult['player1'])  || is_null($getresult['player2'])) {
                    $stmt = $pdo->prepare("DELETE FROM rooms WHERE id = :roomId");
                    $stmt->bindParam(':roomId', $roomId, PDO::PARAM_INT);
                    $stmt->execute();
                    echo "Room {$roomId} deleted\n";
                }
            }
        }
    }
    protected function startGame(ConnectionInterface $from, $roomCode, $msg)
    {
        $roomobj = new DBClass('rooms');
        $roomobjexist = $roomobj->exists('roomCode', $roomCode);
        if ($roomobjexist) {

            $result = $roomobj->get('roomCode', $roomCode);
            $fromuser_id = null;

            // Find the user ID for the given connection
            foreach ($this->players_connections as $player) {
                if ($from === $player['conn']) {
                    $fromuser_id = $player['id'];
                    break;
                }
            }
            
            if ($result && isset($result['player1']) && isset($result['player2'])) {
                $user1Conn = [];
                $user2Conn = [];
            
                // Find the connections for player1 and player2
                foreach ($this->players_connections as $player) {
                    if ($player['id'] == $result['player1']) {
                        $user1Conn[] = $player['conn'];
                        
                        // $user1Conn->send(trim($msg));

                    } elseif ($player['id'] == $result['player2']) {
                        $user2Conn[] = $player['conn'];
                        // $user2Conn->send(trim($msg));

                    }
                }
            
                // Check which player to send the message to
                if ($fromuser_id != (int)$result['player2']) {
                    if ($user2Conn !== null) {
                        foreach ($user2Conn as $user2Connect){

                            $user2Connect->send(trim($msg));
                        }
                     
                    }
                } else {
                    if ($user1Conn !== null) {
                        foreach ($user1Conn as $user1Connnet){

                            $user1Connnet->send(trim($msg));
                        }
                      
                    }
                }
            }
            
            // print_r($result);
            // if ($result && isset($result['player1']) && isset($result['player2'])) {
            //     $user1Conn = $this->users_connections[$result['player1']];
            //     $user2Conn = $this->users_connections[$result['player2']];
            //     // $resultupdate=$roomobj->update('roomCode',$roomCode,[
            //     //         'board' => $msg['board'],
            //     //      'turn' => $msg['turn'],
            //     //      'chancesLeft' => $msg['chancesLeft'],
            //     // ]);
                
            //     // echo "from user " . $fromuser_id."  to ".$result['player1']." to ".$result['player2'];
            //     if ($fromuser_id != (int)$result['player2']) {

            //         $user2Conn->send(trim($msg));
            //         // $user2Conn->send(json_encode(trim($msg)));

            //     } else {

            //         $user1Conn->send(trim($msg));
            //         // $user1Conn->send(json_encode(trim($msg)));
            //     }
            // }
        }


        $roomobj = null;
    }
}

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new TicTacToeServer()
        )
    ),
    8088
);

echo "WebSocket server started at ws://0.0.0.0:8088\n";
$server->run();
