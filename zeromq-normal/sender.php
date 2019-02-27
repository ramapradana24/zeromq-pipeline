<?php
$context = new ZMQContext();

$host = '127.0.0.1';
$username = 'rama';
$password = 'ramapradana24';
$db = 'db_sync';

$conn = new mysqli($host, $username, $password, $db);
date_default_timezone_set("Asia/Singapore");
if($conn->connect_error){
    die('Connection Failed: ' . $conn->connect_error);
}

$requester = new ZMQSocket($context, ZMQ::SOCKET_REQ);
$requester->connect("tcp://localhost:5555");

$errorCount = 0;
$successCount = 0;
$totalTimelapse = 0;

while(true){
    $logQuery = "SELECT * FROM tb_log WHERE status = 0 ORDER BY created_at ASC";
    $result = $conn->query($logQuery);

    if(mysqli_num_rows($result) > 0){

        $order = 1;
        $start = microtime(true);
        while($row = $result->fetch_assoc()){
            // $start = microtime(true);

            $packet = $row;

            $requester->send(json_encode($packet));
            $response = $requester->recv();
            if($response == 1) $successCount++;
            else $errorCount++;
            $updateLog = 'UPDATE tb_log set status = 1 WHERE id = ' . $row['id'];
            $conn->query($updateLog);

            $end = microtime(true);
            $totalTimelapse += (($end - $start) * 1000);
            $order++;
        }
    }

    else{
        $packet = [
            'order' => 0,
            'msg'   => $row['query']
        ];

        $requester->send(json_encode($packet));
        $response = $requester->recv();

        echo "START TIME: " . $start, PHP_EOL;
        echo "END TIME: " . $end, PHP_EOL;
        echo 'Sukses:' . $successCount . ' Error:'. $errorCount, PHP_EOL;
        echo 'Sending time: '. $totalTimelapse;
        exit;
        sleep(1);
    }
}