<?php
require_once("header.php");
if(isset($_SESSION["user"]) && unserialize($_SESSION["user"])->getID() === 0):?>
    <form action="./query" method="get">
        <input type="text" name="query" id="query" value="<?php if(isset($_GET["query"])) echo $_GET["query"]; else echo "select * from "; ?>">
    </form><?php
    $conn = new mysqli("localhost","swudb","", "my_swudb", 3306);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    $resultSet = $conn->query($_GET["query"]);
    try{
        $resultSet->fetch_assoc();
        $select=true;
    }catch(Error $e){
        $select=false;
    }
    if($select):
        $resultSet = $conn->query($_GET["query"]);
    ?>
    <div class="container">
        <form action="carte">
            <input type="text" name="nome" value="<?php echo $_GET["nome"] ?? ""?>">
            <select multiple name="espansione[]" id="espansione">
                <option <?php if(isset($_GET["espansione"]) and in_array("all", $_GET["espansione"])) echo "selected" ?>>all</option>
                <?php foreach($espansioni as $set) : ?>
                    <option <?php if(isset($_GET["espansione"]) and in_array($set, $_GET["espansione"])) echo "selected" ?>>
                        <?php echo $set ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="submit" value="cerca">
        </form>
        <div class="decks-section">
            <div class="decks-container">
                <?php if(count($rs)> 0): ?>
                    <table>
                        <thead>
                            <tr class="deck-header">
                            <?php if(isset($_SESSION["user"])): ?>
                                <td>aggiungi alla collezione</td>
                            <?php endif; ?>
                                <?php foreach($rs[0] as $column): ?>
                                    <td>
                                        <?php echo $column; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for($i = 1;$i < count($rs); $i++): ?>
                            <tr class="card-in-deck-row deck-card">
                                <?php if(isset($_SESSION["user"])): ?>
                                    <td style="max-width: 100vw">
                                        <form action="./insertTo" method="get">
                                            <input type="hidden" name="mazzo" value="Collezione">
                                            <input type="hidden" name="espansione" value="<?php echo $rs[$i]["espansione"]?>">
                                            <input type="hidden" name="numero" value="<?php echo $rs[$i]["numero"]?>">
                                            <input type="hidden" name="from" value="<?php echo ".".$_SERVER['REQUEST_URI'];?>">
                                            <input type="image" src='img/collezione.png' width='100px' height='auto' alt="Invia il form">
                                        </form>
                                    </td>
                                <?php endif; ?>
                                <?php foreach($rs[$i] as $value): ?>
                                <td>
                                    <a href="<?php echo "https://swudb.com/card/" . $rs[$i]["espansione"] . "/" . sprintf("%0" . $numeri[$rs[$i]["espansione"]] . "d", $rs[$i]["numero"]);?>" target="_blank">
                                        <?php echo $value;
                                            if($value === $rs[$i]["nome"]){
                                                ?><img class="card-hover" src="https://swudb.com/cards/<?php echo $rs[$i]["espansione"] . "/" . sprintf("%0" . $numeri[$rs[$i]["espansione"]] . "d", $rs[$i]["numero"]);?>.png"><?php
                                            }; ?>
                                    </a>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    nessuna carta trovata
                <?php endif; ?>
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