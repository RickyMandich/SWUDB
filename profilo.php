<!DOCTYPE html>
<html lang="it" class="<?php echo $match[1];?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profilo Utente</title>
    <link rel="stylesheet" href="./css/profilo.css">
    <script src="./js/profilo.js"></script>
</head>
<?php
    require_once("header.php");
    if (!isset($_SESSION["user"])){
        ?>
        <meta http-equiv="refresh" content="0; url=./login">
        <?php
    }
    $result = $conn->query("select distinct mazzo from mazzi");
    $mazzi = [];
    while( $row = $result->fetch_assoc() ){
        array_push($mazzi, $row["mazzo"]);
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
                    </span>
                </div>
                <div class="info-group">
                    <label>Email:</label>
                    <span>
                        <?php echo unserialize($_SESSION["user"])->getEmail(); ?>
                    </span>
                </div>
            </div>
            <table border="">
                <tbody>
                    <tr class="deck-header">
                        <td>
                            nome mazzo
                        </td>
                        <td>
                            leader
                        </td>
                        <td>
                            base
                        </td>
                    </tr>
                    <?php foreach($mazzi as $value): ?>
                        <tr class="deck-card">
                            <td>
                                <?php echo $value; ?>
                            </td>
                            <td>
                                <?php $leaderQuery = "select c.nome, c.espansione, c.numero from carte c, mazzi m where c.tipo='leader' and m.espansione = c.espansione and m.numero = c.numero and m.codUtente = ".unserialize($_SESSION["user"])->getID()." and m.mazzo = '".$value."'"; ?>
                                <?php $leader = $conn->query($leaderQuery)->fetch_assoc();?>
                                <?php echo $leaderQuery; ?>

                                <img src="https://swudb.com/cards/<?php echo $leader["espansione"]."/".sprintf("%0". $numeri[$leader["numero"]]."d", $leader["numero"]).".png"?>" alt="<?php echo $leader["nome"];?>">
                            </td>
                            <?php $base = $conn->query("select c.espansione from carte c, mazzi m where c.tipo='base' and m.espansione = c.espansione and m.numero = m.numero and m.codUtente = ".unserialize($_SESSION["user"])->getID()." and m.mazzo = '".$value."'")->fetch_assoc();
                            var_dump($base)?>
                            <td>
                            <img src="https://swudb.com/cards/<?php echo $base["espansione"]."/".sprintf("%0". $numeri[$base["numero"]]."d", $base["numero"]).".png"?>" alt="<?php echo $base["nome"];?>">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if(count($mazzi) === 0): ?>
                        <tr class="deck-card">
                            <td colspan="3">
                                non hai nessun mazzo
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <form action="./logout" class="logout-form">
                <button type="submit" class="submit-btn">logout</button>
            </form>
        </div>
    </div>
</body>
</html>
