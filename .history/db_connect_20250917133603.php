<?php 
//  $servername = "localhost";
//  $username = "root";    
//  $password = "";
//  $dbname = "admit_cards_data";
//  $port = 3306;
 $servername = "appunjab.cm0bksyl0mvc.ap-south-1.rds.amazonaws.coma";
 $username = "sai_admitcardss";    
 $password = "ttipl@sai2025";
 $dbname = "admit_cards_data";
 $port = 3390;
 $conn = new mysqli($servername, $username, $password, $dbname, $port);

 if ($conn->connect_error) {
     die("Connection failed: " . $conn->connect_error);
 }  

?>