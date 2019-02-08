<?php

$host = '127.0.0.1';
$username = 'rama';
$password = 'ramapradana24';
$db = 'db_sync';

$conn = new mysqli($host, $username, $password, $db);
date_default_timezone_set("Asia/Singapore");
if($conn->connect_error){
    die('Connection Failed: ' . $conn->connect_error);
}