<!DOCTYPE html>
<html lang="it" class="<?php echo $file;?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="./css/login.css">
        <title>Sign In</title>
    </head>
    <?php
        if (isset($_SESSION["user"])){
            ?>
            <meta http-equiv="refresh" content="0; url=./profilo">
            <?php
        }
        $resultText = "";
        $resultClass = "hidden";
        if(isset($_GET["nome"])){
            try{
                $insert = $GLOBALS["conn"]->query("insert into utenti (nome, email, password) values('".$_GET["nome"]."', '".$_GET["email"]. "', '". $_GET["password"]."')");
                $resultText = "registrazione avvenuta con successo, ora accedi";
                $resultClass = "success";
                mail("ricky.mandich@gmail.com", "nuovo utente", "to ricky.mandich@gmail.com un nuovo utente si è registrato a SWUDB.altervista.org \n\tnome:\t".$_GET["nome"]."\n\temail:\t".$_GET["email"], 'From: swudb@altervista.org' . "\r\n");
                mail("swudb@altervista.org", "nuovo utente", "to swudb@altervista.org un nuovo utente si è registrato a SWUDB.altervista.org \n\tnome:\t".$_GET["nome"]."\n\temail:\t".$_GET["email"], 'From: swudb@altervista.org' . "\r\n");
                mail($_GET["email"], "iscrizione a SWUDB", "congratulazioni per esserti registrato a www.swudb.altervista.org", 'From: swudb@altervista.org' . "\r\n");
                ?><meta http-equiv="refresh" content="3; url=./login?from=<?php echo $_GET["from"] ?? "home"; ?>"><?php
            }catch(mysqli_sql_exception $e){
                $resultText = $e->getMessage();
                $resultClass = "failed";
            }
            ?>
            <?php
        }
    ?>
    <body>
        <div class="container">
            <div class="form-container">
                <h1>Sign up</h1>
                <form action="./signIn">
                    <div class="form-group">
                        <input type="text" name="nome" placeholder="Nome" required>
                    </div>
                    <div class="form-group">
                        <input type="email" name="email" placeholder="Email" required>
                    </div>
                    <div class="form-group">
                        <input type="password" name="password" placeholder="Password" required>
                    </div>
                    <div class="form-group">
                        <span id="result" class="<?php echo $resultClass?>">
                            <?php echo $resultText ?>
                        </span>
                    </div>
                    <button type="submit" class="submit-btn">Sign up</button>
                </form>
                <div class="alternate-action">
                    <span>or </span>
                    <a href="./login">Log in</a>
                </div>
            </div>
        </div>
    </body>
</html>