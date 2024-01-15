<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token, Authorization');
header('Content-Type: application/json');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../classes/DBclass.php';

$request_method = $_SERVER["REQUEST_METHOD"];

// Ensure that the request is a DELETE request
if ($request_method == "DELETE") {
    // Assuming the ID is passed as a query parameter
    $room_id = isset($_GET['id']) ? $_GET['id'] : null;

    if ($room_id !== null) {
        // Assuming you have a method to handle the deletion in your DBclass
        $db = new DBclass();
        $result = $db->delete('id',$room_id);

        if ($result) {
            echo json_encode(array("message" => "Room deleted successfully."));
            exit;
        } else {
            echo json_encode(array("message" => "Failed to delete room."));
            exit;

        }
    } else {
        echo json_encode(array("message" => "Missing 'id' parameter."));
        exit;

    }
} else {
    echo json_encode(array("message" => "Invalid request method."));
    exit;

}
?>
