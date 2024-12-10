<?php
    require_once "header.php";
    if(isset($_GET["from"]) and $file != 'moveTo'){
        moveTo($_GET["into"], $_GET["espansione"], $_GET["numero"], $_GET["foil"], $_GET["mazzo"]);
        ?><meta http-equiv="refresh" content="0; url=./<?php echo $_GET["from"]?>"><?
    }else if(count($_GET)>0){
        moveTo($_GET["into"], $_GET["espansione"], $_GET["numero"], $_GET["foil"], $_GET["mazzo"]);
        ?><meta http-equiv="refresh" content="0; url=./<?php echo $file?>"><?
    }else{
        ?>
        <form action="moveTo">
            <label for="into">
                mazzo in cui inserire la carta
                <input type="text" name="into" id="into">
            </label>
            <br>
            <label for="espansione">
                espansione della carta
                <input type="text" name="espansione" id="espansione">
            </label>
            <br>
            <label for="numero">
                numero della carta
                <input type="number" name="numero" id="numero">
            </label>
            <br>
            <label for="foil">
                <input type="checkbox" name="foil" id="foil">
                la carta è foil
            </label>
            <br>
            <label for="mazzo">
                mazzo da cui prelevare la carta
                <input type="text" name="mazzo" id="mazzo">
            </label>
            <br>
        </form>
        <?php
    }