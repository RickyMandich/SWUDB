<?php require_once("header.php");?>
<!DOCTYPE html>
<html lang="it" class="<?php echo $file;?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $file?></title>
    </head>
    <?php
        function elaborazioneJson($jsonData){
            $deck = new Deck($jsonData);
            $deck->createDeck(isset($_GET["public"]) ? ($_GET["public"] =="on"?"1":"0") : "0");
            foreach($deck->carte as $c){
                $query = getQueryCartaFromCollezione($c, "Collezione");
                echo str_replace("\n", "<br>", str_replace("    ", "&nbsp;&nbsp;&nbsp;&nbsp;", $query)."<br>");
                $query = $GLOBALS["conn"]->query($query)->fetch_assoc();
                var_dump($query);
                echo "<br>";
                if($query){
                    echo "sposto ".$query["nome"]." ".$query["titolo"]." da ".$query["mazzo"]." a $deck->nome<br>"; 
                    moveTo($deck->nome, $query["espansione"], $query["numero"], $query["mazzo"]);
                }else{
                    $query = $GLOBALS["conn"]->query("select nome, titolo from carte where espansione = '$c->espansione' and numero = $c->numero")->fetch_assoc();
                    echo "inserisco ".$query["nome"]." ".$query["titolo"]."in mancanti di $deck->nome<br>";
                    insertTo($c->espansione, $c->numero, "mancanti di ".$deck->nome, 0);
                }
            }
        }
        if(!isset($_SESSION["user"])){
            ?><meta http-equiv="refresh" content="0; url=./login?from=<?php echo $file; ?>"><?php
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
                        <form action="inputJsonDeck"><input type="submit" value="torna al caricamento"></form>
                        <!-- <meta http-equiv="refresh" content="3; url=inputJsonDeck"> -->
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
                    <form action="inputJsonDeck"><input type="submit" value="torna al caricamento"></form>
                    <!-- <meta http-equiv="refresh" content="3; url=inputJsonDeck"> -->
                <?php
            } else {
                echo "Errore: Il file non contiene un JSON valido";
                ?><meta http-equiv="refresh" content="3; url=inputJsonDeck"><?php
            }
        } else {
            // Form per il caricamento del file
            ?>
            <form action="inputJsonDeck" method="post" enctype="multipart/form-data">
                <input type="file" name="fileJson" id="fileJson" accept=".json">
                <textarea name="textJson" id="textJson"></textarea>
                public <input type="checkbox" name="public" id="public">
                <input type="submit" value="Carica">
            </form>
            <?php
        }
    ?>
</html>