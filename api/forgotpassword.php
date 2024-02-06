<?php



header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token , Authorization');
header('Content-Type: application/json');

require __DIR__ . '/../classes/User.php';
use Firebase\JWT\JWT;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../libs/GenerateOTP.php';


$mail = new PHPMailer(true);
$request_method =$_SERVER["REQUEST_METHOD"];
$request = json_decode(file_get_contents("php://input"),true);

if($request_method == 'POST'){
    $email = $request['email'] ?? '';

    if (empty($email)) {
        http_response_code(400);
        echo json_encode(['error' => 'email is required']);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $data = [
            "error" => 'Invalid email format',
        ];
        http_response_code(400);
        echo json_encode($data);
        exit();
    }
    $userObj=new DBClass('users');
    $exists=$userObj->exists('email',$email);
    if($exists){
        try {
        
            $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; // Use Gmail's SMTP server
        $mail->SMTPAuth = true;
        $mail->Username = 'omadreja@gmail.com'; 
        $mail->Password = 'jtxxzxwhkslnrvni';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        
            // Recipients
            $mail->setFrom('omadreja@gmail.com', 'TicTacToe Game');
            $mail->addAddress($email); // To mail id
            $otp=GenerateOTP($email);
            // print_r($otp);
    
            $htmlTemplate = '
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        .email-container { max-width: 600px; margin: auto; padding: 20px; text-align: center; font-family: Arial, sans-serif; }
                        .email-header { background-color: #f2f2f2; padding: 10px; font-size: 24px; color: #333; }
                        .email-subheader { background-color: #f2f2f2; padding: 10px; font-size: 19px; color: #333;margin-bottom:5px; }
                        .email-body { margin-top: 20px; padding: 20px; border: 1px solid #ddd; }
                        .otp-code { font-size: 20px; color: #007bff; margin: 15px 0; letter-spacing: 3px; font-weight: bold; }
                    </style>
                </head>
                <body>
                    <div class="email-container">
                    <div class="email-header">Tic Tac Toe Game</div>
                        <br />
                        <div class="email-body">
                        <div class="email-subheader">Your One-Time Password (OTP)</div>

                            <p>Dear User,</p>
                            <p>Your One-Time Password (OTP) for password reset is:</p>
                            <div class="otp-code">[OTP]</div>
                            <p>Please use this code to complete your operation. This code is valid for 15 minutes.</p>
                        </div>
                    </div>
                </body>
                </html>';
                $htmlTemplate = str_replace('[OTP]', $otp, $htmlTemplate);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Your OTP for Password Reset';
            $mail->Body    = $htmlTemplate;
            
      
            if($mail->send()){
                echo json_encode(['message' => "OTP email has been sent. Check your email inbox"]);
                http_response_code(200);
            
            } else {
                echo json_encode(['error' => "Email could not be sent. Mailer Error: " . $mail->ErrorInfo]);
                 http_response_code(400);
           
            }
                exit;
    
        } catch (Exception $e) {
           echo json_encode(['error' => "Email could not be sent. Mailer Error: {$mail->ErrorInfo}"]);
            http_response_code(400);
           
           exit;
        }
    }
    echo json_encode(['error' =>"This email is invalid and trying again with other email ID."]);
    http_response_code(400);
  
    exit;
  
}
 
    $data=[
        "status"=>405,
        "message"=>$request_method. ' Method Not Allowed',
    ];
    http_response_code(405);

    echo json_encode($data);
    exit();




?>