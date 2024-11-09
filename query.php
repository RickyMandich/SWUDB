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
        <?php if(isset($_SESSION["user"]) && unserialize($_SESSION["user"])->getID() === 0):?>
            <?php
            $conn = new mysqli("localhost","swudb","", "my_swudb", 3306);
            if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
            }
            $rs = $conn->query($_GET["query"]);
            if($resultSet = $rs->fetch_assoc()):?>
                <div class="container">
                    <form action="./query" method="get">
                        <input type="text" name="query" id="query" value="<?php if(isset($_GET["query"])) echo $_GET["query"]; else echo "select * from "; ?>">
                    </form>
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
                                </thead>
                                <tbody>
                                    <?php do{ ?>
                                        <tr class="card-in-deck-row deck-card">
                                            <?php foreach($resultSet as $value): ?>
                                            <td>
                                                <a href="<?php echo "https://swudb.com/card/" . $resultSet["espansione"] . "/" . sprintf("%0" . $numeri[$resultSet["espansione"]] . "d", $resultSet["numero"]);?>" target="_blank">
                                                    <?php echo $value;
                                                        if($value === $resultSet["nome"]){
                                                            ?><img class="card-hover" src="https://swudb.com/cards/<?php echo $resultSet["espansione"] . "/" . sprintf("%0" . $numeri[$resultSet["espansione"]] . "d", $resultSet["numero"]);?>.png"><?php
                                                        }; ?>
                                                </a>
                                            </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php }while($resultSet = $rs->fetch_assoc()); ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php else:
                echo "ho fatto ".$conn->affected_rows." modifiche";
                endif;
            ?>
        <?php elseif(isset($_SESSION["user"])):?>
            <meta http-equiv="refresh" content="0; url=./home">
            <?php else: ?>
            <meta http-equiv="refresh" content="0; url=./logIn?from=<?php echo $file; ?>">
            <?php endif;?>
    </body>
</html>