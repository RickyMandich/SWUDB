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
    require_once("header.php");
    if (!isset($_SESSION["user"])){
        ?>
        <meta http-equiv="refresh" content="0; url=./login">
        <?php
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
                <div class="info-group">
                    <label>ID:</label>
                    <span>
                        <?php echo unserialize($_SESSION["user"])->getID(); ?>
                    </span>
                </div>
            </div>

            <!-- Sezione mazzi -->
            <?php require("Mazzi.php"); ?>

            <form action="./logout" class="logout-form">
                <button type="submit" class="submit-btn">logout</button>
            </form>
        </div>
    </div>
</body>
</html>
