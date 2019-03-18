<?php
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

$receivedMessage = 0;
$totalMessage = 0;

#sending ready check packet so the sender can send a message
$readyCheckPacket = [
    'packet_type'   => 1,
    'status'        => 1
];
$sender->send(json_encode($readyCheckPacket));


while(true){
    $packet = json_decode($receiver->recv());
    
    #if there is a ready check request
    if($packet->packet_type == 1){
        $sender->send(json_encode($readyCheckPacket));
    }

    #it must be a message
    else{
         # initialize start time
        if($packet->order == 1){
            $startTime = microtime(true);
            prepareLogFile();
        }

        insertToArrivalLog($packet->order);

        #if there is a update packet, insert to tb_sync so other engine will process it.
        if($packet->type == 2){
            $insertUpdate = 'insert into tb_sync(query, type, old_hash, new_hash) values("'. $packet->query .'", '.$packet->type.', "'.$packet->old_hash.'", "'.$packet->new_hash.'")';
            if($conn->query($insertUpdate)){
                echo $packet->order, PHP_EOL;
            }else echo 'ERROR!!', PHP_EOL;
        }else{
            // echo 'OK!', PHP_EOL;
            if($conn->query($packet->query)){
                echo $packet->order, PHP_EOL;
            }else{
                echo 'ERROR!!', PHP_EOL;
            }
        }
        

        # last packet is received, calculate time
        if($packet->order == $packet->total){
            $endTime = microtime(true);
            echo $endTime, PHP_EOL;
            echo $startTime, PHP_EOL;
            echo "timelapse: " . (($endTime - $startTime) * 1000), PHP_EOL;

            $arrived = file('arrival.log', FILE_IGNORE_NEW_LINES);
            echo "total arrived: " . count($arrived), PHP_EOL;
            echo "total packet: " . $packet->total, PHP_EOL;
            if(count($arrived) < $packet->total){
                #there is packet missing, time to search the missing packet
                $missingPacketNumbers = [];
                for($i = 1; $i <= $packet->total; $i++){
                    if(in_array($i, $arrived)) continue;
                    else array_push($missingPacketNumbers, $i);
                }

                $missingPacketReq = [
                    'packet_type'   => 3,
                    'packet_numbers'    => $missingPacketNumbers
                ];
                $sender->send(json_encode($missingPacketReq));
                continue;
            }
            $sender->send("ok");
            $diterima = file("arrival.log", FILE_IGNORE_NEW_LINES);
            echo "diterima:" . count($diterima), PHP_EOL;
            exit;
        }
    }
   
}

function prepareLogFile(){
    $logFile = fopen("arrival.log", "w");
    fclose($logFile);
}

function insertToArrivalLog($row){
    $sendingLog = fopen('arrival.log', 'a');
    fwrite($sendingLog, $row ."\n");
    fclose($sendingLog);
}
