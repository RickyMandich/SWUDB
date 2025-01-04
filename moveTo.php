<!DOCTYPE html>
<html lang="it" class="<?php echo $file;?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Move to deck</title>
        <link rel="stylesheet" href="./css/insertTo.css">
    </head>
    <?php
        require_once("header.php");
        if(!isset($_SESSION["user"])){
            ?><meta http-equiv="refresh" content="0; url=./login?from=<?php echo $file; ?>"><?php
        }

        if(isset($_GET["from"])){
            moveTo($_GET["into"], $_GET["espansione"], $_GET["numero"], $_GET["mazzo"]);
            ?><meta http-equiv="refresh" content="0; url=./<?php echo $_GET["from"]?>"><?php
        } else if(isset($_GET["eseguito"])){
            $query = 'espansione=' . $_GET['espansione'] . '&numero=' . $_GET['numero'];
            moveTo($_GET["into"], $_GET["espansione"], $_GET["numero"], $_GET["mazzo"]);
            ?><meta http-equiv="refresh" content="0; url=./moveTo?<?php echo $query ?>"><?php
        }
    ?>
    <body>
        <form action="moveTo">
            <label for="into">mazzo in cui inserire la carta</label>
            <input list="mazzi" type="text" name="into" id="into" value="<?php if(isset($_GET["into"])) echo $_GET["into"];?>">
            <datalist id="mazzi">
                <?php 
                    $rs = $GLOBALS["conn"]->query("select distinct m.id, m.nome as mazzo from mazzi m, composizione c where m.id = c.idMazzo order by m.nome");
                    $mazzi = [];
                    while($line = $rs->fetch_assoc()){
                        array_push($mazzi, $line["mazzo"]);
                    }
                ?>
                <?php foreach($mazzi as $m):?>
                    <option value="<?php echo $m?>">
                <?php endforeach;?>
            </datalist>
            <br>
            <label for="espansione">espansione della carta</label>
            <input type="text" name="espansione" id="espansione" value="<?php if(isset($_GET["espansione"])) echo $_GET["espansione"];?>">
            <br>
            <label for="numero">numero della carta</label>
            <input type="number" name="numero" id="numero" value="<?php if(isset($_GET["numero"])) echo $_GET["numero"];?>">
            <br>
            <label for="mazzo">mazzo da cui prelevare la carta</label>
            <input type="text" name="mazzo" id="mazzo" value="<?php if(isset($_GET["mazzo"])) echo $_GET["mazzo"];?>">
            <br>
            <input type="hidden" name="from" value="<?php echo $_GET["from"] ?? '';?>">
            <input type="hidden" name="eseguito" value="false">
            <input type="submit" value="sposta carta">
        </form>
        <span id="result" class="<?php echo isset($_GET["resultClass"]) ? $_GET["resultClass"] : "hidden"?>">
            <?php echo isset($_GET["resultText"]) ? $_GET["resultText"] : ""?>
        </span>
        <hr>
        <?php if($_GET["foil"]){?>
            <span class="foil"><?php
        }?>
        <img src="https://swudb.com/cards/<?php echo $_GET["espansione"]."/".sprintf("%0".$numeri[$_GET["espansione"]]."d",$_GET["numero"]);?>.png">
        <?php if($_GET["foil"]){?>
            </span><?php
        }?>
    </body>
    <style>
        body{
            align-items: center;
        }

        body img{
            max-width: 35vw;
            max-height: 35vw;
        }
    </style>
    <script>
        window.onload = function() {
            var input = document.getElementById('selected');
            if (input) {
                input.focus();
                input.select();
            }
        };
    </script>
</html>