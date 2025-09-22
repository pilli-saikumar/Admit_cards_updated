<?php 
//  $servername = "localhost";
//  $username = "root";    
//  $password = "";
//  $dbname = "admit_cards_data";
//  $port = 3306;
 $servername = "appunjab.cm0bksyl0mvc.ap-south-1.rds.amazonaws.com";
 $username = "root";    
 $password = "";
 $dbname = "admit_cards_data";
 $port = 3306;
 $conn = new mysqli($servername, $username, $password, $dbname, $port);

 if ($conn->connect_error) {
     die("Connection failed: " . $conn->connect_error);
 }  

?>