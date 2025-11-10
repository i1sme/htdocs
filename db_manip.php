<?php
function dbConnect(){
   $servername = "127.0.0.1";
   $username = "root";
   $password = "";

   $conn = new mysqli($servername, $username, $password);

   if (!$conn) {
   die("Connection failed: " . mysqli_connect_error());
   }

   if (!empty($params)) {
      $types = '';
      $values = [];
      foreach ($params as $param) {
         if (is_int($param)) {
            $types .= 'i';
         } elseif (is_float($param)) {
            $types .= 'd';
         } else {
            $types .= 's';
         }
            $values[] = $param;
            }
            
      $stmt->bind_param($types, ...$values);
   }
        
   $stmt->execute();
   return $stmt;
    }

// took most piece of this code here https://www.w3schools.com/php/php_mysql_connect.asp
?>