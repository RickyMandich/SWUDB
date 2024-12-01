<!DOCTYPE html>
<html lang="it" class="<?php echo $file;?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>input deck from json</title>
    </head>
    <?php
        require_once("header.php");
        function elaborazioneJson($jsonData){
            $carte = $jsonData["data"];
            $i=0;
            foreach($carte as $c){
                $id = $GLOBALS["conn"]->query("select id from mazzi where codUtente = ".unserialize($_SESSION["user"])->getID()." and nome = '".$c["mazzo"]."'");
                if($id = !$id->fetch_assoc()){
                    $GLOBALS["conn"]->query("insert into mazzi (nome, public, codUtente) values('".$c["mazzo"]."', ".$c["public"].", ".$c["codUtente"].");");
                }
                $id = $GLOBALS["conn"]->query("select id from mazzi where codUtente = ".unserialize($_SESSION["user"])->getID()." and nome = '".$c["mazzo"]."'")->fetch_assoc()["id"];
                insertTo($c["espansione"], $c["numero"], $c["mazzo"], $c["foil"]);
                $i++;
            }
            echo "ho fatto $i modifiche";
        }
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
                    elaborazioneJson($jsonData);
                    ?>
                        <meta http-equiv="refresh" content="3; url=inputJsonDeck">
                    <?php
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
                elaborazioneJson($jsonData);
                ?>
                    <meta http-equiv="refresh" content="3; url=inputJsonDeck">
                <?php
            } else {
                echo "Errore: Il file non contiene un JSON valido";
                ?><meta http-equiv="refresh" content="3; url=inputJsonDeck"><?php
            }
        } else {
            ?>
            <form action="importCollezione" method="post" enctype="multipart/form-data">
                <input type="file" name="fileJson" id="fileJson" accept=".json">
                <textarea name="textJson" id="textJson"></textarea>
                <input type="submit" value="Carica">
            </form>
            <?php
        }
    ?>
</html>