<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profilo Utente</title>
    <link rel="stylesheet" href="./css/profilo.css">
    <script src="./js/profilo.js"></script>
</head>
<?php
    require_once("Utente.php");
    session_start();
    if (!isset($_SESSION["user"])){
        ?>
        <meta http-equiv="refresh" content="0; url=./logIn">
        <?php
    }
    $conn = new mysqli("localhost","root","Minecraft35?", "starwarsunlimited", 3306);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        $resultSet = $conn->query("select m.mazzo, c.* from mazzi m, carte c where m.espansione = c.espansione and m.numero = c.numero and m.codUtente = ". unserialize($_SESSION["user"])->getID());
        $deck = [];
        $precedente;
        while($line = $resultSet->fetch_assoc()){
            $row = [];
            foreach($line as $key => $value){
                $row[$key] = $value;
            }
            if(!isset($precedente) or $precedente != $line["mazzo"]){
                $header = $row;
                foreach($header as $key => $i){
                    if($key !== "mazzo") $header[$key] = null;
                }
                array_push($deck, $header);
            }
            array_push($deck, $row);
        }
?>
<body>
    <div class="container">
        <div class="profile-container">
            <h1>Profilo Utente</h1>
            <div class="user-info">
                <div class="info-group">
                    <label>Nome Utente:</label>
                    <span>
                        <?php echo unserialize($_SESSION["user"])->getNome(); ?>
                    </span>                                     <!--
                                                                                            todo:
                                                                                            - sostituire tutti gli usi di thymaleaf con l'uso di php
                                                                                        -->
                </div>
                <div class="info-group">
                    <label>Email:</label>
                    <span>
                        <?php echo unserialize($_SESSION["user"])->getEmail(); ?>
                    </span>
                </div>
            </div>

            <!-- Sezione mazzi -->
            <div class="decks-section">
                <h2>I Tuoi Mazzi</h2>
                <div class="decks-container">
                    <table>
                        <tbody>
                            <?php 
                            $precedente;
                            foreach($deck as $row): ?>
                                <tr class="<?php if(!isset($precedente) or $row["mazzo"] !== $precedente) echo "deck-header"; else echo "deck-card";?>">
                                    <?php foreach($row as $cell): ?>
                                        <td>
                                            <?php echo $cell; ?>
                                        </td>
                                    <?php endforeach;
                                    $precedente = $row["mazzo"];?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <form action="./logout" class="logout-form">
                <button type="submit" class="submit-btn">logout</button>
            </form>
        </div>
    </div>
</body>
</html>
