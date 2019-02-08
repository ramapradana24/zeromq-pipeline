<?php

#insert pid to process.id
$fp = fopen('process.id', 'a');
fwrite($fp, getmypid()."\n");
fclose($fp);

include("db.php");
$context = new ZMQContext();

//  Socket to receive messages on
$receiver = new ZMQSocket($context, ZMQ::SOCKET_PULL);
$receiver->connect("tcp://localhost:5557");

//  Socket to send messages to main server
$sender = new ZMQSocket($context, ZMQ::SOCKET_PUSH);
$sender->connect("tcp://localhost:5558");

//  Process tasks forever
while (true) {
    $msg = $receiver->recv();
    echo $msg, PHP_EOL;
    // //  Do the work
    // usleep($string * 1000);

   //  Send results to sink
    $sender->send($msg);
}