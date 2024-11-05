<?php
    $conn = new mysqli(hostname: "localhost",username: "swudb", database:"my_swudb", port:3306);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    $resultSet = $conn-> query("select * from carte");
    while ($line = $resultSet -> fetch_assoc()){
        $numeri[$line["espansione"]] = preg_match("/.*?[p;P][R;r]$/", $line["espansione"]) ? 3 : strlen((string) $line["numero"]);
    }
    unset($resultSet);
?>