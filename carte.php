<!DOCTYPE html>
<?php $file = basename($_SERVER['PHP_SELF']) ?>
<?php $file = preg_replace('/\?.*/', '', $file) ?>
<?php $file = preg_replace('/\.php$/', '', $file) ?>
<html lang="it" class="<?php echo $file;?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $file; unset($file) ?></title>
        <link rel="stylesheet" href="./css/cartaPopUp.css">
        <link rel="stylesheet" href="css/mazzi.css">
    </head>
    <?php
        function f($resultSet, &$rs){
            if( $resultSet->num_rows > 0) {
                while($row = $resultSet->fetch_assoc()){
                    $line = [];
                    $header = [];
                    if($rs===[]){
                        foreach($row as $key => $value){
                            $line[$key] = $value;
                            array_push( $header, $key);
                        }
                        $rs[0] = $header;
                        $line = [];
                    }
                    foreach($row as $key => $value) $line[$key]=$value;
                    array_push($rs, $line);
                }
            }
        }
        require_once "header.php";
        var_dump($_GET);
        if(count($_GET)>0 and $_GET["nome"] === 0 and $_GET["espansione"] === "tutte"): ?><meta http-equiv="refresh" content="0; url=carte"><?php endif;
        $leader = $conn->query("select * from carte where nome like '%" . ($_GET["nome"] ?? "") . "%' and espansione = '".$_GET["espansione"]."' and tipo = 'leader' order by uscita, espansione, numero");
        $basi = $conn->query("select * from carte where nome like '%" . ($_GET["nome"] ?? "") . "%' and espansione = '".$_GET["espansione"]."' and tipo = 'base' order by uscita, espansione, numero");
        $altro = $conn->query("select * from carte where nome like '%" . ($_GET["nome"] ?? "") . "%' and espansione = '".$_GET["espansione"]."' and tipo <> 'leader' and tipo <> 'base' order by uscita, espansione, numero");
        $rs = [];
        f($leader, $rs);
        f($basi, $rs);
        f($altro, $rs);
        $conn->close();
    ?>
    <body>
        <div class="container">
            <form action="carte">
                <input type="text" name="nome" value="<?php echo $_GET["nome"] ?? ""?>">
                <select name="espansione" id="espansione">
                    <?php foreach($espansioni as $set) : ?>
                        <option selected>all</option>
                        <option>
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
    </body>
</html>