<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Carte</title>
        <link rel="stylesheet" href="profilo.css">
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
                if($primo){
                    $primo = false;
                    foreach($row as $key => $value){
                        array_push($line, $key);
                    }
                    $rs[0] = $line;
                    $line = [];
                }
                $numeri[$row["espansione"]] = strlen((string) $row["numero"]);
                foreach($row as $key => $value) array_push($line, $value);
                array_push($rs, $line);
            }
        }
        $conn->close();
    ?>
    <body>
        <form action="carte.php" method="get">
            <input type="text" name="nome">
            <input type="submit" value="cerca">
        </form>
        <table border="">
            <thead>
                <tr>
                    <?php foreach($rs[0] as $column): ?>
                        <td>
                            <?php echo $column; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php for($i = 1;$i < count($rs); $i++): ?>
                    <tr>
                        <?php foreach($rs[$i] as $value): ?>
                            <td>
                                <a href="<?php echo "https://www.swudb.com/card/" . $rs[$i][0] . "/" . sprintf("%0" . $numeri[$rs[$i][0]] . "d", $rs[$i][1]);?>" target="_blank">
                                <?php echo $value; ?>
                                </a>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </body>
</html>