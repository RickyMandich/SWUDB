<?php
    require_once "header.php";
    ?><title><?php echo $file?></title><?php
    if(isset($_GET["from"]) and $file != 'moveTo'){
        moveTo($_GET["into"], $_GET["espansione"], $_GET["numero"], $_GET["mazzo"]);
        ?><meta http-equiv="refresh" content="0; url=./<?php echo $_GET["from"]?>"><?
    }else if(count($_GET)>0){
        moveTo($_GET["into"], $_GET["espansione"], $_GET["numero"], $_GET["mazzo"]);
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
            <?php
                $optionMazzi = [];
                $result = $GLOBALS["conn"]->query("select distinct m.id, m.nome as mazzo from mazzi m, composizione c where m.id = c.idMazzo order by m.nome");
                while($line = $result->fetch_assoc()){
                    $optionMazzi[$line["id"]] = $line["mazzo"];
                }
            ?>
            <label for="mazzo">
                mazzo da cui prelevare la carta
                <select name="mazzo" id="mazzo">
                    <?php foreach($optionMazzi as $option):?>
                        <option>
                            <?php echo $option; ?>
                        </option>
                    <?php endforeach;?>
                </select>
            </label>
            <br>
            <input type="submit" value="sposta carta">
        </form>
        <?php
    }