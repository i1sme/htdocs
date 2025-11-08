<?php
function dbConnect(){
   $servername = "127.0.0.1";
   $username = "root";
   $password = "";

   $conn = new mysqli($servername, $username, $password);

   if (!$conn) {
   die("Connection failed: " . mysqli_connect_error());
   }
   echo "Connected successfully";
}

// took most of piece of this code here https://www.w3schools.com/php/php_mysql_connect.asp
?>