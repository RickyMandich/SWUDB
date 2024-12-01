<?php
    $GLOBALS["conn"] = new mysqli(hostname: "localhost",username: "swudb", database:"my_swudb", port:3306);
    if ($GLOBALS["conn"]->connect_error) {
        die("Connection failed: " . $GLOBALS["conn"]->connect_error);
    }
    $resultSet = $GLOBALS["conn"]-> query("select * from carte");
    while ($line = $resultSet -> fetch_assoc()){
        $numeri[$line["espansione"]] = preg_match("/.*?[p;P][R;r]$/", $line["espansione"]) ? 3 : strlen((string) $line["numero"]);
    }
    $resultSet = $GLOBALS["conn"] -> query("select distinct espansione from carte order by uscita");
    $espansioni = [];
    while ($line = $resultSet -> fetch_assoc()){
        array_push($espansioni, $line["espansione"]);
    }
    $tratti = [];
    $resultSet = $GLOBALS["conn"] ->query("select distinct tratti from carte");
    while ($line = $resultSet -> fetch_assoc()){
        $card = explode(" * ", $line["tratti"]);
        foreach ($card as $value) {
            if(!in_array($value, $tratti)) array_push($tratti, $value);
        }
    }
    sort($tratti);
    unset($resultSet);
    $file = basename($_SERVER['PHP_SELF']);
    $file = preg_replace('/\?.*/', '', $file);
    $file = preg_replace('/\.php$/', '', $file);
?>