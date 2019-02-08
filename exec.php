<?php

#insert pid to process.id
$fp = fopen('process.id', 'a');
fwrite($fp, getmypid()."\n");
fclose($fp);

while(true){
    sleep(1);
}