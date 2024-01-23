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

$dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME;
$username = DB_USER;
$password =DB_PASSWORD;

try {
    $pdo = new PDO($dsn, $username, $password);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

class TicTacToeServer implements MessageComponentInterface
{
    private $secretKey = SecretKey;
    protected $connections = [];
    
    protected $users_connections = [];

    public function onOpen(ConnectionInterface $conn)
    {
        $token = $this->extractTokenFromQueryString($conn);
        if (!is_null($token)){

            try {
                $user = $this->getUserFromToken($token);
    
                $this->connections[$conn->resourceId] = $conn;
                $this->users_connections[$user['id']] = $conn;
                echo "Connection {$conn->resourceId} (User: {$user['username']}, {$user['id']}) has connected\n";
                $user_obj=new DBClass('users');
                $user_obj->update('id',$user['id'],['status'=>1]);
                $user_obj=null;
                $conn->send( json_encode(["message"=>"Welcome to the WebSocket server!"]));
    
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

                    $roomId = $this->createRoom($user['id'],$from);

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
                    if(isset( $data['roomCode'])){
                        $roomCode = $data['roomCode'];
                    
                        $this->startCommunication($from, $roomCode,$msg);
                    }
                    
                    // Handle other actions
                    break;
                
               
            }
        }
        else{
            if(isset( $data['roomCode'])){
                $roomCode = $data['roomCode'];

                $this->startCommunication($from, $roomCode,$msg);
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
    
        // Find the user ID based on the connection
        foreach ($this->users_connections as $id => $userConn) {
            if ($conn === $userConn) {
                $userId = $id;
                break;
            }
        }
        if ($userId!==null) {
            $user_obj=new DBClass('users');
            $user_obj->update('id',$userId,['status'=>false]);
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

    protected function checkuserstatus($userid) {
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

    protected function createRoom($userId,ConnectionInterface $conn)
    {   
        $roomCode = substr( bin2hex(random_bytes(3)), 0, 7); // Combining uniqid with random bytes

     
        $roomobj= new DBClass('rooms');
        $roomobjexist=$roomobj->exists('user1_id',$userId);
        if($roomobjexist){
            $result=$roomobj->delete('user1_id',$userId);   
        }
        echo "Creating room for user".$userId;
        // Insert the room into the database
        $result=$roomobj->create(["room_code"=>$roomCode,"user1_id"=>$userId]);
        $roomId =$result['last_id'];
        $roomobj=null;
        $conn->send(json_encode(["room_code"=>$roomCode]));
        echo "Room {$roomCode} created\n";
        return $roomId;
    }

    protected function joinRoom(ConnectionInterface $conn, $userId, $roomCode)
    {
        // Check if the room exists
        $roomobj= new DBClass('rooms');
        $result=$roomobj->get('room_code',$roomCode);
        
        // print_r($result['user1_id']);
        // echo(!isset($result['user1_id']));
        // echo  $this->checkuserstatus($result['user1_id']);
        // echo  $this->checkuserstatus($userId);
        // echo $this->checkuserstatus($result['user1_id']) &&  $this->checkuserstatus($userId)!=true;
            // echo !isset($result['user1_id']);
            // echo !$this->checkuserstatus($result['user1_id']) &&  !$this->checkuserstatus($userId);

            if(isset($result['user1_id'])){
                if($result['user1_id']==$userId){
                    $conn->send(json_encode(["error"=>"player 1 and player2 both are same."]));
                    return ;

                }
                elseif($this->checkuserstatus($result['user1_id']) &&  $this->checkuserstatus($userId)){
                    if(!isset($result['user2_id'])){
                        $roomobj->update('id',$result['id'],['user2_id'=>$userId]);
                        $roomId = $result['id'];
                        $user2=new DBClass('users');
                        $resultuser2=$user2->get('id',$userId);
                      
                        echo "User id {$userId} joined room {$roomId}\n";
            
                        // Notify the other user in the room
                        $otherUserId = $result['user1_id'];
                        if($otherUserId){
                             $user1=new DBClass('users');
                          $resultuser1=$user1->get('id',$otherUserId);
                          $resultuser2=$user1->get('id',$userId);
                          if(isset($resultuser1)){
                             $user1name=$resultuser1['username'] ;
                             $user2name=$resultuser2['username'] ;
         
                             $conn->send(json_encode(["userjoined"=>"Game Start Now","player1name"=>$user1name,"player2name"=>$user2name,"roomCode"=>$roomCode]));
         
         
                          }
                          if(isset($resultuser2)){
                             $user1name=$resultuser1['username'] ;
         
                             $user2name=$resultuser2['username'] ;
                             $this->users_connections[$otherUserId]->send(json_encode(["userjoined"=>"Game Start Now","player1name"=>$user1name,"player2name"=>$user2name,"roomCode"=>$roomCode]));
             
                            }
                          
                            return ;
         
                        }
                       
                    }
                    else{
                        $conn->send(json_encode(["error"=>"Room {$roomCode} is full"]));
                  return ;
                    }
                }
               
           
                 else{
                    $conn->send(json_encode(["status"=>"other player are offline."]));
                    return ;
                  
                
                 }
        } else {
            $conn->send(json_encode(["error"=>"Room {$roomCode} is  does not exist and try again"]));
            
            return ;

        }
    }

    protected function leaveRoom(ConnectionInterface $conn)
    {
        $userId = null;
    
        // Find the user ID based on the connection
        foreach ($this->users_connections as $id => $userConn) {
            if ($conn === $userConn) {
                $userId = $id;
                break;
            }
        }
    
        if ($userId !== null) {
            
            global $pdo;

            // Check if the user is in any room
            $roomId = null;
            $stmt = $pdo->prepare("SELECT id, user1_id, user2_id FROM rooms WHERE user1_id = :userId OR user2_id = :userId");
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
            if ($result) {
                $roomId = $result['id'];
    
                // Remove the user from the room
                if ($result['user1_id'] == $userId) {
                    $stmt = $pdo->prepare("UPDATE rooms SET user1_id = NULL WHERE id = :roomId");
                } elseif ($result['user2_id'] == $userId) {
                    $stmt = $pdo->prepare("UPDATE rooms SET user2_id = NULL WHERE id = :roomId");
                }
    
                $stmt->bindParam(':roomId', $roomId, PDO::PARAM_INT);
                $stmt->execute();
    
                echo "User id {$userId} left room {$roomId}\n";
    
                // Notify the other user in the room, if any
                $otherUserId = ($result['user1_id'] == $userId) ? $result['user2_id'] : $result['user1_id'];
    
                if ($otherUserId && isset($this->users_connections[$otherUserId])) {
                    $this->users_connections[$otherUserId]->send(json_encode(["message"=>"other player left the game."]));
                }
                $stmt = $pdo->prepare("SELECT id, user1_id, user2_id FROM rooms WHERE user1_id = :userId OR user2_id = :userId");
                $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
                $stmt->execute();
                $getresult = $stmt->fetch(PDO::FETCH_ASSOC);
                // Check if both users have left, then delete the room
                print_r($getresult);
                
                    if ( is_null($getresult['user1_id'])  || is_null($getresult['user2_id'] )) {
                        $stmt = $pdo->prepare("DELETE FROM rooms WHERE id = :roomId");
                        $stmt->bindParam(':roomId', $roomId, PDO::PARAM_INT);
                        $stmt->execute();
                        echo "Room {$roomId} deleted\n";
                    }
               
               
            }
        }
    }
    protected function startCommunication(ConnectionInterface $from, $roomCode,$msg)
    {
        $roomobj = new DBClass('rooms');
        $result = $roomobj->get('room_code', $roomCode);
        $fromuser_id=null;
        foreach ($this->users_connections as $id => $userConn) {
            if ($from === $userConn) {
                $fromuser_id = $id;
                break;
            }
        }
        if ($result && isset($result['user1_id']) && isset($result['user2_id'])) {
            $user1Conn = $this->users_connections[$result['user1_id']];
            $user2Conn = $this->users_connections[$result['user2_id']];
            echo "from user " . $fromuser_id."  to ".$result['user1_id']." to ".$result['user2_id'];
            if ($fromuser_id != (int)$result['user2_id']) {
                $user1Conn->send(json_encode(["player2"=>$msg]));
            }
            else{
             
                    $user2Conn->send(json_encode(["player1"=>$msg]));
             

            }

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
?>
