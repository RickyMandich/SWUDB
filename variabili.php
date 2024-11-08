<?php
    $conn = new mysqli(hostname: "localhost",username: "swudb", database:"my_swudb", port:3306);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    $resultSet = $conn-> query("select * from carte");
    while ($line = $resultSet -> fetch_assoc()){
        $numeri[$line["espansione"]] = preg_match("/.*?[p;P][R;r]$/", $line["espansione"]) ? 3 : strlen((string) $line["numero"]);
    }
    $resultSet = $conn -> query("select distinct espansione from carte order by uscita");
    $espansioni = [];
    while ($line = $resultSet -> fetch_assoc()){
        array_push($espansioni, $line["espansione"]);
    }
    $tratti = [];
    $resultSet = $conn ->query("select distinct tratti from carte");
    while ($line = $resultSet -> fetch_assoc()){
        $card = explode(" * ", $line);
        foreach ($card as $value) {
            if(!in_array($value, $tratti)) array_push($tratti, $value);
        }
    }
    foreach($tratti as $t){
        echo $t;
        ?> <br> <?php
    }
    unset($resultSet);
?>