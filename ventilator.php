<?php

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


while(true){
    $query = "SELECT * FROM tb_log WHERE status = 0 ORDER BY created_at";
    $result = $conn->query($query);

    $rowCount = mysqli_num_rows($result);
    if($rowCount > 0){
        
        $startTime = microtime(true);

        $targetProcess = round($rowCount/$maxRowPerWorker);
        echo 'worker: ' . $targetProcess, PHP_EOL;
        manageWorker($targetProcess);
        // manageWorker(1);

        $order = 1;
        while($row = $result->fetch_assoc()){
            // echo $row['id'] . " ";

            $packet = [
                'order' => $order,
                'total' => $rowCount,
                'type'  => $row['type'],
                'msg'   => $row['query'],
                'created_at'    => $row['created_at']
            ];

            $sender->send(json_encode($packet));

            $updateQuery = "UPDATE tb_log SET status = 1 WHERE id = " . $row['id'];
            if($conn->query($updateQuery)){
                // echo "OK!", PHP_EOL;
            }
            else{
                // echo "ERROR!", PHP_EOL;
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
    // exit;
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


// echo "Press Enter when the workers are ready: ";
// $fp = fopen('php://stdin', 'r');
// $line = fgets($fp, 512);
// fclose($fp);
// echo "Sending tasks to workers…", PHP_EOL;

// //  The first message is "0" and signals start of batch
// $sender->send(0);

// //  Send 100 tasks
// $total_msec = 0;     //  Total expected cost in msecs
// for ($task_nbr = 0; $task_nbr < 100; $task_nbr++) {
//     //  Random workload from 1 to 100msecs
//     $workload = mt_rand(1, 100);
//     $total_msec += $workload;
//     $sender->send($workload);

// }

// printf ("Total expected cost: %d msec\n", $total_msec);
// sleep (1);