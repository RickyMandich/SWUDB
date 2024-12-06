<?php require_once "header.php";?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $file?></title>
    </head>
    <body>
    <?php
        function getQueryCartaFromCollezione(DeckCard $c){
            return "
    select m.nome as mazzo, ca.nome, ca.titolo, ca.espansione, ca.numero
    from composizione c, mazzi m, carte ca
    where (
        c.idMazzo = m.id
        and m.codUtente = 0
        and ca.nome = (
            select ca.nome 
            from carte ca 
            where (
                ca.numero = $c->numero 
                and ca.espansione = '$c->espansione')
            limit 1) 
        and ca.titolo = (
            select ca.titolo 
            from carte ca 
            where (
                ca.numero = $c->numero 
                and ca.espansione = '$c->espansione')
            limit 1)
        and m.id = c.idMazzo
        and ca.espansione = c.espansione
        and ca.numero = c.numero)
        order by espansione, numero;
    ";
        }

        if($file == 'findCard'){
            if(!isset($_SESSION["user"])){
                ?>
                    <meta http-equiv="refresh" content="0; url=login?from=findCard">
                <?php
            }
            if(isset($_GET["espansione"]) and isset($_GET["numero"])){
                echo str_replace("  ", "&nbsp;&nbsp;", str_replace("\n", "<br>", getQueryCartaFromCollezione(new DeckCard($_GET["espansione"]."_".sprintf("%0".$numeri[$_GET["espansione"]]."d", $_GET["numero"])))));
                $result = $GLOBALS["conn"]->query(getQueryCartaFromCollezione(new DeckCard($_GET["espansione"]."_".sprintf("%0".$numeri[$_GET["espansione"]]."d", $_GET["numero"]))));
                ?>
                    <table>
                        <?php
                        $first = true;
                            while($rs = $result->fetch_assoc()){
                                if($first){
                                    $first = false;
                                    ?>
                                    <thead>
                                        <tr>
                                            <?php
                                            foreach($rs as $c=>$v){
                                                ?>
                                                    <td>
                                                        <?php echo $c;?>
                                                    </td>
                                                <?php
                                            }
                                            ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                <?php }?>
                                        <tr>
                                            <?php
                                            foreach($rs as $v){
                                                ?>
                                                <td>
                                                    <?php echo $v?>
                                                </td>
                                                <?php
                                            }
                                            ?>
                                        </tr>
                                        <?php
                                        }
                                        ?>
                                    </tbody>
                    </table>
                <?php
            }
            ?>
            <form action="findCard">
                espansione
                <input type="text" name="espansione" id="espansione" placeholder="espansione" value="<?php echo isset($_GET["espansione"])?$_GET["espansione"]:""?>">
                <br>
                numero
                <input type="number" name="numero" id="numero" placeholder="numero" value="<?php echo isset($_GET["numero"])?$_GET["numero"]:""?>">
                <br>
                <input type="submit" value="cerca carta">
            </form>
            <?php
        }
    ?> 
    </body>
</html>