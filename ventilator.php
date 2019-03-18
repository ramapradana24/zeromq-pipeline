<?php

#TO DO
# 1. membuat engine dapat membalas paket missing dari client

// manageWorker(0);
// exit;

$maxRowPerWorker = 50;
include('db.php');
$context = new ZMQContext();

//  Socket to send messages on
$sender = new ZMQSocket($context, ZMQ::SOCKET_PUSH);
$sender->bind("tcp://*:5557");

$pull = new ZMQSocket($context, ZMQ::SOCKET_PULL);
$pull->bind('tcp://*:5559');

$server = new ZMQSocket($context, ZMQ::SOCKET_PUSH);
$server->connect("tcp://localhost:5558");

$startTime = 0;
$endTime = 0;

#send ready check message
$readyCheckPacket = [
    'packet_type'   => 1, #packet ready check request
    'status'        => 0
];
#

while(true){
    $query = "SELECT * FROM tb_log WHERE status = 0 ORDER BY id";
    $result = $conn->query($query);

    $rowCount = mysqli_num_rows($result);
    if($rowCount > 0){
        $server->send(json_encode($readyCheckPacket));

        #waiting for client reply
        $readyCheckReply = json_decode($pull->recv());

        $startTime = microtime(true);

        #initialize how many worker is needed
        $targetProcess = $rowCount/$maxRowPerWorker;
        if($targetProcess - (int) $targetProcess > 0) $targetProcess = (int) $targetProcess + 1;
        
        echo 'worker: ' . $targetProcess, PHP_EOL;
        manageWorker($targetProcess);
        // manageWorker(0);

        #initialize sending log file to empty
        // prepareLogFile();

        $order = 1;
        $packetSent = [];
        while($row = $result->fetch_assoc()){
            $server->send(json_encode($readyCheckPacket));

            #waiting for client reply
            $readyCheckReply = json_decode($pull->recv());
            $packet = [
                'packet_type'   => 2,
                'order' => $order,
                'total' => $rowCount,
                'type'  => $row['type'],
                'query'   => $row['query'],
                'old_hash'  => $row['old_hash'],
                'new_hash'  => $row['new_hash'],
                'created_at'    => $row['created_at']
            ];

            // insertToSendingLog(json_encode($packet));

            $sender->send(json_encode($packet));
            array_push($packetSent, $packet);
            echo $order, PHP_EOL;
            $updateQuery = "UPDATE tb_log SET status = 1 WHERE id = " . $row['id'];
            if($conn->query($updateQuery)){
                echo $order, PHP_EOL;
            }
            else{
                // echo "ERROR!", PHP_EOL;
            }

            #if its the last packet, wait for client to send vetification
            #any missing packet will be resend
            if($order == $rowCount){
                echo "stopping...", PHP_EOL;
                echo $pull->recv();
                manageWorker(0);
                exit;
            }

            $order++;
        }
        
    }else{
        #destroy all worker
        $endTime = microtime(true);

        $packet = [
            'order' => 0,
            'msg'   => ""
        ];
        
        $sender->send(json_encode($packet));

        echo "START TIME: " . $startTime, PHP_EOL;
        echo 'Timelapse: ' . (($endTime - $startTime) * 1000), PHP_EOL;
        manageWorker(0);
        exit;
        sleep(1);
    }
    exit;
}

function manageWorker($targetProcess){
    $proc = file('process.id', FILE_IGNORE_NEW_LINES);
    $procCount = count($proc);

    #add new worker if needed, else destroy worker
    if($targetProcess == 0){
        while($procCount > $targetProcess){
            exec('taskkill /F /PID ' . $proc[$procCount-1]);
            $procCount--;
        }
        $fp = fopen('process.id', 'w');
        fclose($fp);
    }
    else if($procCount < $targetProcess){
        for($procCount; $procCount < $targetProcess; $procCount++){
            pclose(popen('start /B php worker.php 2>nul >nul', "r"));
        }
    }
    else if($procCount > $targetProcess){
        while($procCount > $targetProcess){
            exec('taskkill /F /PID ' . $proc[$procCount-1]);
            $procCount--;
        }

        #update process.id file
        $fp = fopen('process.id', 'w');
        for($i = 0; $i < $procCount; $i++){
            fwrite($fp, $proc[$i]."\n");
        }
        fclose($fp);
    }
}

function prepareLogFile(){
    $logFile = fopen("sending.log", "w");
    fclose($logFile);
}

function insertToSendingLog($row){
    $sendingLog = fopen('sending.log', 'a');
    fwrite($sendingLog, $row ."\n");
    fclose($sendingLog);
}