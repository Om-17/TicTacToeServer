<?php 
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token , Authorization');
header('Content-Type: application/json');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../classes/User.php';
use Firebase\JWT\JWT;
$request_method =$_SERVER["REQUEST_METHOD"];
$request = json_decode(file_get_contents("php://input"),true);
if($request_method == 'POST'){
    
    if (empty($request['username']) || empty($request['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Username and password are required']);
        exit;
    }
    else{

        $user = new User();
        $username= $request['username'];
        $password= $request['password'];
        $result = $user->get("username", $username);
       
        if ($result ) {
            if (password_verify($password, $result['password'])){
                $tokenId = base64_encode(random_bytes(32));
                $issuedAt = time();
                $expire = $issuedAt + 3600;  // Expire in 1 hour
                $issuer = 'Tic Tac Toe';
                $secretKey=SecretKey;
                $token = [
                    'iat'  => $issuedAt,
                    'jti'  => $tokenId,
                    'iss'  => $issuer,
                   
                    'userId'   => $result['id'],
                    'username' => $result['username'],
                  
                ];
            
                $jwt = JWT::encode($token, $secretKey, 'HS256');
            
                // Return the JWT token
                   
                echo json_encode(['token' => $jwt,'id'=>$result['id'], 'username' => $result['username'],]);
                exit;
           
            }
            else{
                http_response_code(401);
                echo json_encode(['error' => 'Incorrect password.']);
                exit;

            }
        }
        else {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials']);
            exit;

        }
           
    }
    
   }
   else{
    $data=[
        "status"=>405,
        "message"=>$request_method. ' Method Not Allowed',
    ];
    http_response_code(405);

    echo json_encode($data);
    exit();
}
?>