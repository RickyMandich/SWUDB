<!DOCTYPE html>
<html lang="it" class="<?php echo $file;?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>input deck from json</title>
    </head>
    <?php
        require_once("header.php");
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
                    $deck = new Deck($jsonData);
                    $conn = new mysqli(hostname: "localhost",username: "swudb", database:"my_swudb", port:3306);
                    if ($conn->connect_error) {
                        die("Connection failed: " . $conn->connect_error);
                    }
                    echo $deck->getInsertSql();
                    $conn->query($deck->getInsertSql());
                    echo "caricamento riuscito, ho inserito ".$conn->affected_rows." carte";
                    ?><meta http-equiv="refresh" content="3; url=inputJsonDeck"><?php
                } else {
                    echo "Errore: Il file non contiene un JSON valido";
                    ?><meta http-equiv="refresh" content="3; url=inputJsonDeck"><?php
                }
            } else {
                echo "Errore nel caricamento del file: " . $_FILES["fileJson"]["error"];
                ?><meta http-equiv="refresh" content="3; url=inputJsonDeck"><?php
            }
        }else if(isset($_POST["textJson"])){
            if($jsonData = json_decode($_POST["textJson"], true)) {
                $deck = new Deck($jsonData);
                $conn = new mysqli(hostname: "localhost",username: "swudb", database:"my_swudb", port:3306);
                if ($conn->connect_error) {
                    die("Connection failed: " . $conn->connect_error);
                }
                $conn->query($deck->getInsertSql());
                echo "caricamento riuscito, ho inserito ".$conn->affected_rows." carte";
                ?><meta http-equiv="refresh" content="3; url=inputJsonDeck"><?php
            } else {
                echo "Errore: Il file non contiene un JSON valido";
                ?><meta http-equiv="refresh" content="3; url=inputJsonDeck"><?php
            }
        } else {
            // Form per il caricamento del file
            ?>
            <form action="inputJsonDeck" method="post" enctype="multipart/form-data">
                <input type="file" name="fileJson" id="fileJson" accept=".json">
                <input type="text" name="textJson" id="textJson">
                <input type="submit" value="Carica">
            </form>
            <?php
        }
    ?>
</html>