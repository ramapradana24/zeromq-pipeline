<?php

$context = new ZMQContext(1);

#database configuration
$host = '127.0.0.1';
$username = 'rama';
$password = 'ramapradana24';
$db = 'db_sync_master';

$conn = new mysqli($host, $username, $password, $db);
date_default_timezone_set("Asia/Singapore");
if($conn->connect_error){
    die('Connection Failed: ' . $conn->connect_error);
}

//  Socket to talk to clients
$responder = new ZMQSocket($context, ZMQ::SOCKET_REP);
$responder->bind("tcp://*:5555");
$totalTimelapse = 0;
$startTime = 0;
$endTime = 0;

while (true) {
    //  Wait for next request from client
    $packet = json_decode($responder->recv());
    
    # initialize start time
    if($packet->order == 1){
        $startTime = microtime(true);
    }
    # last packet is received, calculate time
    else if($packet->order == 0){
        $endTime = microtime(true);
        $responder->send("0");
        echo "START TIME: " . $startTime, PHP_EOL;
        echo "END TIME: " . $endTime, PHP_EOL;
        echo "timelapse: " . ($endTime - $startTime) * 1000;
        exit;
    }

    echo $packet->order, PHP_EOL;
    #insert log from other db
    if($conn->query($packet->msg))$responder->send("1");
    else $responder->send("0");
}