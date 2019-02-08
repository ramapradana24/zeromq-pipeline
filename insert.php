<?php

include("db.php");

$i = 0;

while($i < 1000){
    $nim = 1605551011;
    $nama = "Rama Pradana " . $i;
    $telepon = 82236255233;

    $query = "INSERT INTO tb_mhs(nim, nama, telepon) VALUES (". $nim .", '". $nama ."', ". $telepon .")";
    if($conn->query($query)){
        echo "OK!";
    }
    else{
        echo "ERROR!";
    }

    $nim++;
    $i++;
    $telepon++;
}