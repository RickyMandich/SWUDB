<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>input deck from json</title>
    </head>
    <?php
        session_start();
        if(!isset($_SESSION["user"])){
            ?><meta http-equiv="refresh" content="0; url=./login"><?php
        }
        if(isset($_FILES["json"])) {
            // Verifica che il file sia stato caricato correttamente
            if($_FILES["json"]["error"] === UPLOAD_ERR_OK) {
                // Legge il contenuto del file JSON
                $jsonContent = file_get_contents($_FILES["json"]["tmp_name"]);
                
                // Verifica che sia un JSON valido
                if($jsonData = json_decode($jsonContent, true)) {
                    require_once("classi/Deck.php");
                    require_once("classi/Utente.php");
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
                echo "Errore nel caricamento del file: " . $_FILES["json"]["error"];
                ?><meta http-equiv="refresh" content="3; url=inputJsonDeck"><?php
            }
        } else {
            // Form per il caricamento del file
            ?>
            <form action="inputJsonDeck" method="post" enctype="multipart/form-data">
                <input type="file" name="json" id="inputJson" accept=".json">
                <input type="submit" value="Carica">
            </form>
            <?php
        }
    ?>
    <script>
        function toggleJsonData(){
            if(document.getElementById("checkJsonData").checked){
                document.getElementById("jsonData").style.display = "inline-block";
            }else{
                document.getElementById("jsonData").style.display = "none";
            }
        }
    </script>
</html>