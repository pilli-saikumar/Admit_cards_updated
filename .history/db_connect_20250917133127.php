<?php 
//  $servername = "localhost";
//  $username = "root";    
//  $password = "";
//  $dbname = "admit_cards_data";
//  $port = 3306;
 $servername = "localhost";
 $username = "root";    
 $password = "";
 $dbname = "admit_cards_data";
 $port = 3306;
 $conn = new mysqli($servername, $username, $password, $dbname, $port);

 if ($conn->connect_error) {
     die("Connection failed: " . $conn->connect_error);
 }  

?>