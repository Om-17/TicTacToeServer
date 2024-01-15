<?php 
require '../classes/User.php';
require __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token , Authorization');
header('Content-Type: application/json');
$request_method = $_SERVER["REQUEST_METHOD"];
$inputdata = json_decode(file_get_contents("php://input"), true);

if ($request_method == 'POST') {

    if (
        !isset($inputdata['username']) && !isset($inputdata['password']) && !isset($inputdata['email'])){
        $data = [
            "error" => 'All fields is required',
        ];
        http_response_code(400);

        echo json_encode($data);
        exit();
    }
    elseif (empty($inputdata['email'])) {
        $data = [
            "error" => 'email is blank',
        ];
        http_response_code(400);

      
        echo json_encode($data);
        exit();
    
    }elseif (!filter_var($inputdata['email'], FILTER_VALIDATE_EMAIL)) {
        $data = [
            "error" => 'Invalid email format',
        ];
        http_response_code(400);
        echo json_encode($data);
        exit();
    }
    elseif (empty($inputdata['username'])) {
        $data = [
            "error" => 'Username is blank',
        ];
        http_response_code(400);

      
        echo json_encode($data);
        exit();
    
    } elseif (empty($inputdata['password'])) {
        $data = [
            "error" => ' Password is blank',
        ];
        http_response_code(400);

        echo json_encode($data);
        exit();

    }
    else {
        $user = new User();

        $username = $inputdata['username'];
        $email = $inputdata['email'];
        $password = $inputdata['password'];
       if(isset($inputdata['name'])){
        $name=$inputdata['name'];
        $user->name=$name;

       }
       
        $user->username = $username;
        $user->password = $password;
        $user->email = $email;
        $result = $user->create();

        if (isset($result["error"])) {
            http_response_code(400);
            echo json_encode($result);
            exit;
        } else {
            $tokenId = base64_encode(random_bytes(32));
            $issuedAt = time();
            $expire = $issuedAt + 3600;  // Expire in 1 hour
            $issuer = 'om adreja';
            $secretKey=SecretKey;
            $token = [
                'iat'  => $issuedAt,
                'jti'  => $tokenId,
                'iss'  => $issuer,
                'data' => [
                    'userId'   => $result['user']['id'],
                    'username' => $result['user']['username'],
                ],
            ];
        
            $jwt = JWT::encode($token, $secretKey, 'HS256');
            $result['token']=$jwt;
            echo json_encode($result);
            exit;

        }
    }

}else{
    
    echo NotAllowMethod($request_method);
    exit();
}


?>