<?php
$dbservername = "127.0.0.1";
$dbusername = "xxx";
$dbpassword = "xxx";
$dbname = "xxx";

// Create connection
$conn = new mysqli($dbservername, $dbusername, $dbpassword, $dbname);

// Check connection
if ($conn->connect_error) {
  apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => 'Connect error'));
} else {
  //apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => 'Connect successful'));
}
?>
