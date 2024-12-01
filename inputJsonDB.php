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
        // Legge il contenuto del file JSON
        $jsonContent = file_get_contents("collezione.json");
        //var_dump($jsonContent);
        // Verifica che sia un JSON valido
        if($jsonData = json_decode($jsonContent, true)) {
            //var_dump($jsonData);
            //elaborazione json==>magic==>Card[]
            $collezione = new Cards();
            foreach($jsonData as $key=> $value){
                //var_dump($value);
                require_once "./classi/Card.php";
                $collezione->add(new Card($value));
            }
            $i=0;
            foreach($collezione->collezione as $value){
                $GLOBALS["conn"]->query("delete from carte where espansione = '".$value->espansione."' and numero = ".$value->numero);
                $GLOBALS["conn"]->query($value->getInsertSql());
                $i++;
            }
            echo "caricamento riuscito, ho inserito $i carte";
        } else {
            echo "Errore: Il file non contiene un JSON valido";
            ?><meta http-equiv="refresh" content="3; url=inputJsonDB"><?php
        }
    ?>
</html>