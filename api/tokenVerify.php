<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Auth-Token, Authorization');
header('Content-Type: application/json');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/config.php';
use Firebase\JWT\JWT;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\Key;
// Replace with your actual secret key
$secretKey = SecretKey;

$request_method = $_SERVER["REQUEST_METHOD"];
$request = json_decode(file_get_contents("php://input"), true);

if ($request_method == 'POST') {
    $token = $request['token'] ?? '';

    if (empty($token)) {
        http_response_code(400);
        echo json_encode(['error' => 'Token is required']);
        exit;
    }

    try {
        $decoded = JWT::decode($token, new Key(SecretKey, 'HS256'));
        // print_r($decoded);
       
      
        http_response_code(200);
        echo json_encode(['message' => 'Token verification successful']);
    } catch (ExpiredException $e) {
        http_response_code(401);
        echo json_encode(['error' => 'Token has expired']);
    } catch (BeforeValidException | SignatureInvalidException $e) {
        http_response_code(401);
        echo json_encode(['error' => 'Token verification failed']);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Internal Server Error']);
    }
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
