<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="css/logIn.css">
        <title>Log In</title>
    </head>
    <?php
        session_start();
        require_once("Utente.php");
        if (isset($_SESSION["user"])):
    ?>
    <meta http-equiv="refresh" content="0; ./profilo">
    <?php else:
        $resultClass = "hidden";
        $resultText = "";
        if(isset($_GET["userID"])):
            $conn = new mysqli("localhost","root","Minecraft35?", "starwarsunlimited", 3306);
            if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
            }
            $resultSet = $conn->query("select * from utenti where nome='".$_GET["userID"]."' or email = '".$_GET["userID"]."'");
            $resultSet = $resultSet->fetch_assoc();
            if($resultSet["password"] === $_GET["password"]):
                $resultText = "accesso eseguito con successo";
                $resultClass = "success";
                $_SESSION["user"] = serialize(new Utente($resultSet["nome"], $resultSet["id"], $resultSet["email"], $resultSet["password"]));
            ?>
            <meta http-equiv="refresh" content="2; url=./profilo">
            <?php
            else:
                $resultClass = "failed";
                $resultText = "email o password sbagliata";
            endif;
        endif;
        ?> 
        <body>
            <div class="container">
                <div class="form-container">
                    <h1>Log in</h1>
                    <form action="logIn">
                        <div class="form-group">
                            <input type="text" name="userID" placeholder="Email/Username" required>
                        </div>
                        <div class="form-group">
                            <input type="password" name="password" placeholder="Password" required>
                        </div>
                        <div class="form-group">
                            <span id="result" class="<?php echo $resultClass?>">
                                <?php echo $resultText ?>
                            </span>
                        </div>
                        <button type="submit" class="submit-btn">Log in</button>
                    </form>
                    <div class="alternate-action">
                        <span>or </span>
                        <a href="signIn">Sign In</a>
                    </div>
                </div>
            </div>
        </body>
    <?php endif; ?>
</html>