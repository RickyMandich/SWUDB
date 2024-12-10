<?php
    $GLOBALS["conn"] = new mysqli(hostname: "localhost",username: "swudb", database:"my_swudb", port:3306);
    if ($GLOBALS["conn"]->connect_error) {
        die("Connection failed: " . $GLOBALS["conn"]->connect_error);
    }
    $resultSet = $GLOBALS["conn"]-> query("select * from carte");
    while ($line = $resultSet -> fetch_assoc()){
        $numeri[$line["espansione"]] = preg_match("/.*?[p;P][R;r]$/", $line["espansione"]) ? 3 : strlen((string) $line["numero"]);
    }
    $resultSet = $GLOBALS["conn"] -> query("select distinct espansione, uscita from carte order by uscita");
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

    function exist($espansione, $numero){
        $resultSet = $GLOBALS["conn"]->query("select * from carte where espansione = '".$espansione."' and numero = ".$numero);
        if($resultSet->fetch_assoc()){
            return true;
        }
        return false;
    }
    function quante($mazzo, $espansione, $numero){
        $query = "select * from mazzi m, composizione c where m.id = c.idMazzo and c.espansione = '".$espansione."' and c.numero = ".$numero." and m.codUtente = ".unserialize($_SESSION["user"])->getID();
        $resultSet = $GLOBALS["conn"]->query($query);
        $quante = 0;
        while($resultSet->fetch_assoc()){
            $quante++;
        }
        return $quante;
    }

    function insertTo($espansione, $numero, $mazzo, $foil){
        $espansione = strtoupper($espansione);
        if(exist($espansione, $numero)){
            if($mazzo === "Collezione" or quante($mazzo, $espansione, $numero)<3){
                $id = $GLOBALS["conn"]->query("select id from mazzi where nome = '".$mazzo."';");
                if(!$id = $id->fetch_assoc()){
                    $GLOBALS["conn"]->query("insert into mazzi (nome, public, codUtente) values('".$mazzo."', 0, ".unserialize($_SESSION["user"])->getID().");");
                    $id = $GLOBALS["conn"]->query("select id from mazzi where nome = '".$mazzo."';")->fetch_assoc();
                }
                $result = $GLOBALS["conn"]->query("insert into composizione (idMazzo, espansione, numero, foil) values(".$id["id"].", '$espansione', $numero, ".($foil === 'on' ? "1" : "0").");");
                if($result === true){
                    $resultClass = "success";
                    $resultText = "carta aggiunta";
                }else{
                    $resultClass = "failed";
                    $resultText = $GLOBALS["conn"]->error;
                }
            }else{
                $resultClass = "failed";
                $resultText = "hai già tre copie di questa carta";
            }
        }else{
            $resultClass = "failed";
            $resultText = "questa carta non esiste";
        }
        return array("resultClass" => $resultClass, "resultText" => $resultText);
    }

    function remove($numero, $mazzo, $espansione, $foil){
        $GLOBALS["conn"]->query("DELETE FROM composizione 
              WHERE idMazzo = (SELECT id FROM mazzi WHERE nome = '".$mazzo."')
                AND espansione = '".$espansione."'
                AND numero = ".$numero.";");
        $modifiche = $GLOBALS["conn"]->affected_rows;
        while($modifiche>1){
            $GLOBALS["conn"]-> query("insert into composizione values((SELECT id FROM mazzi WHERE nome = '".$mazzo."'), '".$espansione."', ".$numero.", ".$foil.")");
            $modifiche--;
        }
    }

    function moveTo($into, $espansione, $numero, $mazzo){
        $exist = $GLOBALS["conn"]->query("select id from mazzi where nome = '".$into."';");
        if(!$exist = $exist->fetch_assoc()){
            $GLOBALS["conn"]->query("insert into mazzi (nome, public, codUtente) values('".$into."', 0, ".unserialize($_SESSION["user"])->getID().");");
            $id = $GLOBALS["conn"]->query("select id from mazzi where nome = '".$into."';")->fetch_assoc()["id"];
        }else{
            $id = $exist["id"];
        }
        $foil = $GLOBALS["conn"]->query("select c.foil from composizione c, mazzi m where m.nome = '$mazzo' and m.id = c.idMazzo and c.espansione = $espansione and c.numero = $numero")->fetch_assoc()["foil"];
        $query = "insert into composizione\n values(".$id.", '".$espansione."', ".$numero.", ".$foil.")";
        echo "<br>query:";
        var_dump($query);
        echo "<br>get:";
        var_dump($_GET);
        $GLOBALS["conn"]-> query($query);
        if($mazzo != null){
            remove($numero, $mazzo, $espansione, $foil);
        }
    }