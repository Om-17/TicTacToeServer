<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token , Authorization');
header('Content-Type: application/json');

require __DIR__ . '/../classes/DBclass.php';
require __DIR__ . '/../vendor/autoload.php';


$request_method =$_SERVER["REQUEST_METHOD"];
$request = json_decode(file_get_contents("php://input"),true);

if($request_method == 'POST'){
    $user_id = $request['id'] ?? '';
    $new_password = $request['password'] ?? '';
    if (empty($new_password)) {
        http_response_code(400);
        echo json_encode(['error' => 'new_password is required']);
        exit;
    }
    if (empty($user_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'user_id is required']);
        exit;
    } 
    $userObj=new DBClass('users');
    if($userObj->exists('id',$user_id)){
        $res=$userObj->update('id',$user_id,["password"=>password_hash($new_password, PASSWORD_DEFAULT)]);
        if($res['status']){
            echo json_encode(["message"=>"Password Reset Successfully."]);
        http_response_code(200);

            exit;
        }
        else{
            echo json_encode(["error"=>"Password Reset Failed."]);
            http_response_code(400);

            exit;

        }
    }


}
NotAllowMethod($request_method);
exit;

?>