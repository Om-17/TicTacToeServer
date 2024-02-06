<?php 

require __DIR__ . '/../classes/DBclass.php';

function GenerateOTP($email) {
    $forgotPassObj = new DBClass("forgot_password");
    $otp = rand(100000, 999999); // Generate a 6-digit OTP
    $currentTime = new DateTime(); // Current time as DateTime object
    $expiryTime = new DateTime(); // Expiry time as DateTime object
    $expiryTime->add(new DateInterval('PT15M')); // Add 15 minutes to the expiry time

    $currentFormatted = $currentTime->format('Y-m-d H:i:s'); // Format for DATETIME field
    $expiryFormatted = $expiryTime->format('Y-m-d H:i:s'); // Format for DATETIME field
    // Delete expired OTP records
    $r=$forgotPassObj->querywithparams("DELETE FROM forgot_password WHERE expiry_time < ?",[$currentFormatted]);
    
    $exist = $forgotPassObj->exists('email', $email);
    
    if ($exist) {
        $res = $forgotPassObj->update('email', $email, ['email' => $email, 'otp_code' => $otp, 'expiry_time' => $expiryFormatted]);
    } else {
        $res = $forgotPassObj->create(['email' => $email, 'otp_code' => $otp, 'expiry_time' => $expiryFormatted]);
    }

  
    if (!isset($res['error'])) {
        return $otp;
    }
    return $res;
}


?>