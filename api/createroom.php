<?php 
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token , Authorization');
header('Content-Type: application/json');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../classes/DBclass.php';
function getUserFromToken($token)
    {
        // Decode and verify the JWT token to get user information
        try {
            $decoded = Token::decodeToken($token, SecretKey);
            // Replace this with your actual user retrieval logic from the decoded token
            return [
                'id' => $decoded->userId,
                'username' => $decoded->username,
                // Add more user information as needed
            ];
            //code...
        } catch (\Throwable $th) {
            //throw $th;
            return [
               "error" => $th->getMessage(),
                // Add more user information as needed
            ];
        }
      
    }
$request_method =$_SERVER["REQUEST_METHOD"];
$request = json_decode(file_get_contents("php://input"),true);
if($request_method == 'POST'){
    $token = $request['token'] ?? '';

    if (empty($token)) {
        http_response_code(400);
        echo json_encode(['error' => 'Token is required']);
        exit;
    }
    $user_obj=getUserFromToken($token);
    if (isset($user_obj['error'])){
    echo json_encode($user_obj);
        exit;
    }
    $roomCode = uniqid();
    $userId=$user_obj['id'];
    $roomobj= new DBClass('rooms');
   
    // Insert the room into the database
    $result=$roomobj->create(["room_code"=>$roomCode,"user1_id"=>$userId]);
    $roomId =$result['last_id'];
    $roomobj=null;
  
    echo json_encode(["room_code"=>$roomCode,"room_id"=>$roomId]);
                exit;
    

} else {
    $data = [
        "status"  => 405,
        "message" => $request_method . ' Method Not Allowed',
    ];
    http_response_code(405);

    echo json_encode($data);
    exit();
}


?>