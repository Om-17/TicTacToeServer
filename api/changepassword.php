<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token , Authorization');
header('Content-Type: application/json');

require __DIR__ . '/../classes/DBclass.php';
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../libs/TokenGenerater.php';


$request_method =$_SERVER["REQUEST_METHOD"];
$request = json_decode(file_get_contents("php://input"),true);

function getBearerToken() {
    $headers = apache_request_headers();
    if (isset($headers['Authorization'])) {
        $matches = array();
        preg_match('/Bearer (.*)/', $headers['Authorization'], $matches);
        if (isset($matches[1])) {
            return $matches[1];
        }
    }
    return null;
}
if ($request_method == 'POST') {
    $jwttoken = getBearerToken();
    if ($jwttoken) {
        $decoded = Token::decodeToken($jwttoken, 'SecretKey'); // Replace 'SecretKey' with your actual secret key
       
        if (is_array($decoded) && isset($decoded['error'])) {
            http_response_code(401);
            echo json_encode(['error' => $decoded['error']]);
            exit;
        }
            $new_password = $request['password'] ?? '';
            $old_password = $request['oldPassword'] ?? '';

            if (empty($new_password)) {
                http_response_code(400);
                echo json_encode(['error' => 'new_password is required']);
                exit;
            }
            if (empty($old_password)) {
                http_response_code(400);
                echo json_encode(['error' => 'old_password is required']);
                exit;
            }

            // Retrieve user information from the database
            $userId = $decoded->userId;
            $userObj = new DBClass('users');
            $userData = $userObj->get('id', $userId);

            // Check if old password matches
            if (password_verify($old_password, $userData['password'])) {
                $res=$userObj->update('id',$userId,["password"=>password_hash($new_password, PASSWORD_DEFAULT)]);
                 if($res['status']){
                     echo json_encode(["message"=>"Password Change Successfully."]);
                http_response_code(200);

                     exit;
                 }
                 else{
                     echo json_encode(["error"=>"Password Change Failed."]);
                http_response_code(400);

                     exit;

                 }
                
            } else {
                // Old password does not match
                http_response_code(403);
                echo json_encode(['error' => 'Invalid old password']);
                exit;
            }
        
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Token not provided']);
        exit;
    }
}

NotAllowMethod($request_method);
exit;

?>