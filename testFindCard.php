<?php
require_once "header.php";
    function getQueryCartaFromCollezione(DeckCard $c){
        return "
            select m.nome as mazzo, ca.espansione, ca.numero
            from composizione c, mazzi m, carte ca
            where c.idMazzo = (
                select id 
                from mazzi 
                where codUtente = ".unserialize($_SESSION["user"])->getID()." 
                and nome = 'collezione') 
            and ca.nome = (
                select ca.nome 
                from mazzi m, composizione c, carte ca 
                where c.idMazzo = (
                    select id 
                    from mazzi 
                    where codUtente = ".unserialize($_SESSION["user"])->getID()." 
                    and nome = 'collezione') 
                and (
                    ca.numero = c.numero 
                    and ca.espansione = c.espansione
                    and m.id = c.idMazzo) 
                and ca.numero = $c->numero 
                and ca.espansione = '$c->espansione'
                limit 1) 
            and ca.titolo = (
                select ca.titolo 
                from mazzi m, composizione c, carte ca 
                where c.idMazzo = (
                    select id 
                    from mazzi 
                    where codUtente = ".unserialize($_SESSION["user"])->getID()." 
                    and nome = 'collezione') 
                and (
                    ca.numero = c.numero 
                    and ca.espansione = c.espansione
                    and m.id = c.idMazzo) 
                and ca.numero = $c->numero 
                and ca.espansione = '$c->espansione'
                limit 1);
            ";
    }

    if($file == 'testFindCard'){
        if(!isset($_SESSION["user"])){
            ?>
                <meta http-equiv="refresh" content="0; url=logIn?from=testFindCard">
            <?php
        }
        if(isset($_GET["espansione"]) and isset($_GET["numero"])){
            echo getQueryCartaFromCollezione(new DeckCard($_GET["espansione"]."_".sprintf("%0".$numeri[$_GET["espansione"]]."3d", $_GET["numero"])));
            $result = $GLOBALS["conn"]->query(getQueryCartaFromCollezione(new DeckCard($_GET["espansione"]."_".sprintf("%0".$numeri[$_GET["espansione"]]."3d", $_GET["numero"]))));
            ?>
                <table>
                    <?php
                        while($rs = $result->fetch_assoc()){
                            if(!$first){
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
        <form action="testFindCard">
            espansione
            <input type="text" name="espansione" id="espansione" placeholder="espansione">
            <br>
            numero
            <input type="number" name="numero" id="numero" placeholder="numero">
            <br>
            <input type="submit" value="cerca carta">
        </form>
        <?php
    }