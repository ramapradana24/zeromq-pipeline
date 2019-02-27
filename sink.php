<?php
/*
*  Task sink
*  Binds PULL socket to tcp://localhost:5558
*  Collects results from workers via that socket
* @author Ian Barber <ian(dot)barber(at)gmail(dot)com>
*/

$host = '127.0.0.1';
$username = 'rama';
$password = 'ramapradana24';
$db = 'db_sync_master';

$conn = new mysqli($host, $username, $password, $db);
date_default_timezone_set("Asia/Singapore");
if($conn->connect_error){
    die('Connection Failed: ' . $conn->connect_error);
}

//  Prepare our context and socket
$context = new ZMQContext();
$receiver = new ZMQSocket($context, ZMQ::SOCKET_PULL);
$receiver->bind("tcp://*:5558");

$sender = new ZMQSocket($context, ZMQ::SOCKET_PUSH);
$sender->connect("tcp://localhost:5559");

$startTime = 0;
$endTime = 0;


while(true){
    $packet = json_decode($receiver->recv());
    
    # initialize start time
    if($packet->order == 1){
        $startTime = microtime(true);
    }
    # last packet is received, calculate time
    else if($packet->order == 0){
        $endTime = microtime(true);
        echo $endTime, PHP_EOL;
        echo $startTime, PHP_EOL;
        echo "timelapse: " . (($endTime - $startTime) * 1000);
        exit;
    }

    echo 'OK!', PHP_EOL;
    // if($conn->query($packet->msg)){
    //     echo 'OK!', PHP_EOL;
    // }else{
    //     echo 'ERROR!!', PHP_EOL;
    // }
}


// //  Wait for start of batch
// $string = $receiver->recv();

// //  Start our clock now
// $tstart = microtime(true);

// //  Process 100 confirmations
// $total_msec = 0;     //  Total calculated cost in msecs
// for ($task_nbr = 0; $task_nbr < 100; $task_nbr++) {
//     $string = $receiver->recv();
//     if ($task_nbr % 10 == 0) {
//         echo ":";
//     } else {
//         echo ".";
//     }
// }

// $tend = microtime(true);

// $total_msec = ($tend - $tstart) * 1000;
// echo PHP_EOL;
// printf ("Total elapsed time: %d msec", $total_msec);
// echo PHP_EOL;