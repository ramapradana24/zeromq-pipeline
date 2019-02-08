<?php

$maxRowPerWorker = 20;

include('db.php');
$context = new ZMQContext();

//  Socket to send messages on
$sender = new ZMQSocket($context, ZMQ::SOCKET_PUSH);
$sender->bind("tcp://*:5557");
while(true){
    $query = "SELECT * FROM tb_log WHERE status = 0 ORDER BY created_at ASC LIMIT 130";
    $result = $conn->query($query);

    $rowCount = mysqli_num_rows($result);
    if($rowCount > 0){
        $targetProcess = round($rowCount/$maxRowPerWorker);
        echo 'worker: ' . $targetProcess, PHP_EOL;
        manageWorker($targetProcess);
        while($row = $result->fetch_assoc()){
            // echo $row['id'] . " ";

            $sender->send($row['query']);
            $updateQuery = "UPDATE tb_log SET status = 1 WHERE id = " . $row['id'];
            if($conn->query($updateQuery)){
                // echo "OK!", PHP_EOL;
            }
            else{
                // echo "ERROR!", PHP_EOL;
            }
        }
    }else{
        #destroy all worker
        manageWorker(0);
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