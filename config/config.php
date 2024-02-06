<?php


ob_start();

require_once  __DIR__ . '/../config/initialize.php';

require_once  __DIR__ . '/../classes/DBconnection.php';

$secret_key = bin2hex(random_bytes(32));

function NotAllowMethod($request_method)  {
  $data=[
    "status"=>405,
    "message"=>$request_method. ' Method Not Allowed',
];
http_response_code(405);

json_encode($data);

}
ob_end_flush();
?>