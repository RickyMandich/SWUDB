<?php
require_once("header.php");?>
<!DOCTYPE html>
<html lang="it" class="<?php echo $file;?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="css/cartaPopUp.css">
        <link rel="stylesheet" href="css/mazzi.css">
        <title>query</title>
    </head>
    <body>
        <?php
            foreach($_GET as $column => $value){
                if(!isset($_POST[$column])) $_POST[$column] = $value;
            }
        ?>
        <?php if(isset($_SESSION["user"]) && unserialize($_SESSION["user"])->isAdmin()):?>
            <div class="container">
                <form action="./query" method="post">
                    <input type="text" name="query" id="query" value="<?php if(isset($_POST["query"])) echo $_POST["query"]; else echo "select * from "; ?>">
                </form>
                <?php
                echo phpversion()."<br>";
                echo $_POST["query"]."<br>";
                var_dump($_POST["query"]);
                $rs = $GLOBALS["conn"]->query($_POST["query"]);
                if($rs):
                    $resultSet = $rs->fetch_assoc()?>
                    <div class="decks-section">
                        <div class="decks-container">
                            <table>
                                <thead>
                                    <tr class="deck-header">
                                        <?php foreach($resultSet as $column=>$value): ?>
                                            <td>
                                                <?php echo $column; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <?php $rs = $GLOBALS["conn"]->query($_POST["query"]); ?>
                                </thead>
                                <tbody>
                                    <?php while($resultSet = $rs->fetch_assoc()): ?>
                                        <tr class="card-in-deck-row deck-card">
                                            <?php foreach($resultSet as $value): ?>
                                                <td>
                                                    <?php if(isset($resultSet["nome"]) and isset($resultSet["espansione"]) and isset($resultSet["numero"])){?>
                                                    <a href="<?php echo "https://swudb.com/card/" . $resultSet["espansione"] . "/" . sprintf("%0" . $numeri[$resultSet["espansione"]] . "d", $resultSet["numero"]);?>" target="_blank">
                                                        <?php echo "$value";
                                                            if($value === $resultSet["nome"]){
                                                                ?><img class="card-hover" src="https://swudb.com/cards/<?php echo $resultSet["espansione"] . "/" . sprintf("%0" . $numeri[$resultSet["espansione"]] . "d", $resultSet["numero"]);?>.png"><?php
                                                            }; ?>
                                                    </a>
                                                    <?php }else{
                                                        echo $value;
                                                    }?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else:
                    echo "ho fatto ".$GLOBALS["conn"]->affected_rows." modifiche";
                endif;?>
            </div>
        <?php elseif(isset($_SESSION["user"])):?>
            <meta http-equiv="refresh" content="0; url=./home">
            <?php else: ?>
            <meta http-equiv="refresh" content="0; url=./login?from=<?php echo $file.(isset($_POST["query"]) ? "?query=".$_POST["query"]:"");?>">
            <?php endif;?>
    </body>
</html>