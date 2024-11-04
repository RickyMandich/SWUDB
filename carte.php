<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Carte</title>
        <link rel="stylesheet" href="./css/cartaPopUp.css">
        <link rel="stylesheet" href="css/mazzi.css">
    </head>
    <?php
        $conn = new mysqli(hostname: "localhost",username: "swudb", database:"my_swudb", port:3306);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        $resultSet = $conn->query("select * from carte where nome like '%" . ($_GET["nome"] ?? "") . "%' order by uscita, espansione, numero");
        $numeri = [];
        $rs = [];
        if( $resultSet->num_rows > 0) {
            $primo = true;
            while($row = $resultSet->fetch_assoc()){
                $line = [];
                $header = [];
                if($primo){
                    $primo = false;
                    foreach($row as $key => $value){
                        $line[$key] = $value;
                        array_push( $header, $key);
                    }
                    $rs[0] = $header;
                    $line = [];
                }
                $numeri[$row["espansione"]] = preg_match("/.*?[p;P][R;r]$/", $row["espansione"]) ? 3 : strlen((string) $row["numero"]);
                foreach($row as $key => $value) $line[$key]=$value;
                array_push($rs, $line);
            }
        }
        $conn->close();
    ?>
    <body>
        <div class="container">
            <form action="carte.php" method="get">
                <input type="text" name="nome">
                <input type="submit" value="cerca">
            </form>
            <div class="decks-section">
                <div class="decks-container">
                    <table>
                        <thead>
                        <tr class="deck-header">
                            <?php foreach($rs[0] as $column): ?>
                                <td>
                                    <?php echo $column; ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for($i = 1;$i < count($rs); $i++): ?>
                            <tr class="deck-card">
                                <?php foreach($rs[$i] as $value): ?>
                                <td>
                                    <a href="<?php echo "https://www.swudb.com/card/" . $rs[$i]["espansione"] . "/" . sprintf("%0" . $numeri[$rs[$i]["espansione"]] . "d", $rs[$i]["numero"]);?>" target="_blank">
                                        <?php echo $value;
                                            if($value === $rs[$i]["nome"]){
                                                ?><img class="card-hover" src="https://www.swudb.com/cards/<?php echo $rs[$i]["espansione"] . "/" . sprintf("%0" . $numeri[$rs[$i]["espansione"]] . "d", $rs[$i]["numero"]);?>.png"><?php
                                            }; ?>
                                    </a>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </body>
</html>