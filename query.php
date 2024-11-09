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
            <div class="container">
                <?php
                $conn = new mysqli("localhost","swudb","", "my_swudb", 3306);
                if ($conn->connect_error) {
                    die("Connection failed: " . $conn->connect_error);
                }
                if(str_contains($_GET["query"], "carte") and (!str_contains($_GET["query"], "leader") and !str_contains($_GET["query"], "base"))){
                    $carte = true;
                    echo "ora divido leader base e resto<br>";
                    if(str_contains($_GET["query"], "order by")){
                        $queryLeader = str_replace("order by", "and tipo='leader' order by", $_GET["query"]);
                        $queryBasi = str_replace("order by", "and tipo='base' order by", $_GET["query"]);
                        $queryAltro = str_replace("order by", "and tipo='altro' order by", $_GET["query"]);
                    }else{
                        $queryLeader = $_GET["query"]." and tipo='leader'";
                        $queryBasi = $_GET["query"]." and tipo='base'";
                        $queryAltro = $_GET["query"]." and tipo<>'leader' and tipo<>'base'";
                    }
                    $leader = $conn -> query($queryLeader);
                    $basi = $conn -> query($queryBasi);
                    $altro = $conn -> query($queryAltro);
                }
                $rs = $conn->query($_GET["query"]);
                if($resultSet = $rs->fetch_assoc()):?>
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
                                    <?php $rs->$conn->query($_GET["query"]); ?>
                                </thead>
                                <tbody>
                                    <?php if($carte):?>
                                        <?php while($resultSet = $leader->fetch_assoc()): ?>
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
                                        <?php endwhile; ?>
                                        <?php while($resultSet = $basi->fetch_assoc()): ?>
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
                                        <?php endwhile; ?>
                                        <?php while($resultSet = $altro->fetch_assoc()): ?>
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
                                        <?php endwhile; ?>
                                    <?php else:?>
                                        <?php while($resultSet = $rs->fetch_assoc()): ?>
                                            <tr class="card-in-deck-row deck-card">
                                                <?php foreach($resultSet as $value): ?>
                                                <td>
                                                    <?php echo $value; ?>
                                                </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php endif;?>
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