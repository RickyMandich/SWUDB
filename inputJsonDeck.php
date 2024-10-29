<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>input deck from json</title>
    </head>
    <?php
        if(isset($_POST["json"])):
            //elaborazione file json
            echo $_POST["json"];
        else:
            //elaborazione richiesta file json?>
            <form action="inputJsonDeck" method="post">
                <input type="file" name="json" id="inputJson">
                <input type="image" src="img/rimuovi.png">
            </form>
            <?php
        endif;
    ?>
</html>