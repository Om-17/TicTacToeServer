<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token , Authorization');
header('Content-Type: application/json');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../classes/User.php';

$request_method =$_SERVER["REQUEST_METHOD"];
$request = json_decode(file_get_contents("php://input"),true);
use Firebase\JWT\JWT;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\Key;
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
// echo json_encode(["method"=>$request_method]);

if($request_method == 'GET'){
    $jwt = getBearerToken();
    if ($jwt) {
        try {
            $decoded = JWT::decode($jwt, new Key(SecretKey, 'HS256'));
            // print_r($decoded);
            $userid = $decoded->userId; 
           

            $userObj = new User();
            $userResult = $userObj->get('id', $userid);
           
            http_response_code(200);
            echo json_encode($userResult);
            exit;

         
        } catch (ExpiredException $e) {
            http_response_code(401);
            echo json_encode(['error' => 'Token has expired']);
            exit;

        } catch (BeforeValidException | SignatureInvalidException $e) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid Token']);
            exit;

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal Server Error']);
            exit;

        }
       
    } else {
        // No token provided
        http_response_code(401);
        echo json_encode(['error' => 'No token provided']);
        exit;
    }
}
NotAllowMethod($request_method);
exit;



?>