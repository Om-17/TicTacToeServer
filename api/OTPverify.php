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
    $otp_code = $request['otp_code'] ?? '';
    $email=$request['email']??'';

    if (empty($otp_code)) {
        http_response_code(400);
        echo json_encode(['error' => 'otp_code is required']);
        exit;
    }
    if (empty($email)) {
        http_response_code(400);
        echo json_encode(['error' => 'email is required']);
        exit;
    }
    $forgotObj = new DBClass("forgot_password");
    $currentTime = new DateTime();
   
    $currentFormatted = $currentTime->format('Y-m-d H:i:s'); 
    // echo json_encode(['current_time' => $currentFormatted]);
    $sql = "SELECT * FROM forgot_password WHERE email=? AND otp_code = ? AND expiry_time > ?";
    $params = [$email,$otp_code, $currentFormatted];
    $res = $forgotObj->querywithparams($sql, $params);
    // print_r($res);
    if(isset($res)){
        if(empty($res)){
            echo json_encode(['error' =>"Invalid OTP code","isvalid" =>false]);
            http_response_code(400);
            
            $r=$forgotObj->querywithparams("DELETE FROM forgot_password WHERE expiry_time < ?",[$currentFormatted]);
            exit;


        }
        else{
            $userObj=new DBClass('users');
            $userRes=$userObj->get('email',$email);
            echo json_encode(['message' =>"OTP verify succussfully.","isvalid" =>true,'userId'=>$userRes['id']]);
            http_response_code(200);
            
            exit;

        }
    }
    echo json_encode(['error' =>"Invalid OTP code","isvalid" =>false]);
    http_response_code(400);

    exit;
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