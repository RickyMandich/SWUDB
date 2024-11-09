<!DOCTYPE html>
<html lang="it" class="<?php echo $file;?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>input DB from json</title>
    </head>
    <?php
        require_once "header.php";
        if(!isset($_SESSION["user"])){
            ?><meta http-equiv="refresh" content="0; url=./logIn?from=<?php echo $file; ?>"><?php
        }
        if(!empty($_FILES["fileJson"]["tmp_name"])) {
            // Verifica che il file sia stato caricato correttamente
            if($_FILES["fileJson"]["error"] === UPLOAD_ERR_OK) {
                // Legge il contenuto del file JSON
                $jsonContent = file_get_contents($_FILES["fileJson"]["tmp_name"]);
                
                // Verifica che sia un JSON valido
                if($jsonData = json_decode($jsonContent, true)) {
                    //elaborazione json==>magic==>Card[]
                    $collezione = new Cards();
                    foreach($jsonData as $key=> $value){
                        var_dump($value);
                        require_once "./classi/Card.php";
                        $collezione->add(new Card($value));
                    }
                    $conn = new mysqli(hostname: "localhost",username: "swudb", database:"my_swudb", port:3306);
                    if ($conn->connect_error) {
                        die("Connection failed: " . $conn->connect_error);
                    }
                    foreach($collezione->collezione as $value){
                        $conn->query("delete from carte where espansione = '".$value->espansione."' and numero = ".$value->numero);
                        $conn->query($value->getInsertSql());
                    }
                    echo "caricamento riuscito, ho inserito ".$conn->affected_rows." carte";
                    ?><meta http-equiv="refresh" content="3; url=inputJsonDB"><?php
                } else {
                    echo "Errore: Il file non contiene un JSON valido";
                    ?><meta http-equiv="refresh" content="3; url=inputJsonDB"><?php
                }
            } else {
                echo "Errore nel caricamento del file: " . $_FILES["fileJson"]["error"];
                ?><meta http-equiv="refresh" content="3; url=inputJsonDB"><?php
            }
        }else {
            // Form per il caricamento del file
            ?>
            <form action="inputJsonDB" method="post" enctype="multipart/form-data">
                <input type="file" name="fileJson" id="fileJson" accept=".json">
                <input type="textarea" name="textJson" id="textJson">
                <input type="submit" value="Carica">
            </form>
            <?php
        }
    ?>
</html>


<?php
/*$local = new mysqli("192.168.1.29", "swudb", "", "my_swudb", 3306);
if ($local->connect_error) {
    die("Connection failed: " . $local->connect_error);
}
$query = [];
$carte = $local->query("SHOW CREATE TABLE carte");
//printTableStructure($carte, "carte");
while($line=$carte->fetch_assoc()){
    echo $line["Create Table"];
    array_push($query, $line["Create Table"]);
    echo "<hr>";
}
$mazzi = $local->query("SHOW CREATE TABLE mazzi");
//printTableStructure($mazzi, "mazzi");
while($line=$mazzi->fetch_assoc()){
    echo $line["Create Table"];
    array_push($query, $line["Create Table"]);
    echo "<hr>";
}

$utenti = $local->query("SHOW CREATE TABLE utenti");
//printTableStructure($utenti, "utenti");
while($line=$utenti->fetch_assoc()){
    echo $line["Create Table"];
    array_push($query, $line["Create Table"]);
    echo "<hr>";
}
?><hr><hr><hr><?php
foreach($query as $key => $value){
    echo "$key=>$value<hr>";
}

$server = new mysqli("localhost", "swudb", "", "my_swudb", 3306);
if ($server->connect_error) {
    die("Connection failed: " . $server->connect_error);
}
$server->query("DROP table carte");
foreach($query as $key => $value){
    $server->query($value);
}*/