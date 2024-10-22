<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profilo Utente</title>
    <link rel="stylesheet" href="./css/profilo.css">
</head>
<?php
    session_start();
    if (!isset($_SESSION["user"])){
        ?>
        <meta http-equiv="refresh" content="0; url=./logIn">
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
                    <span th:text="${nome}"></span>                                     <!--
                                                                                            todo:
                                                                                            - sostituire tutti gli usi di thymaleaf con l'uso di php
                                                                                        -->
                </div>
                <div class="info-group">
                    <label>Email:</label>
                    <span th:text="${email}"></span>
                </div>
            </div>

            <!-- Sezione mazzi -->
            <div class="decks-section">
                <h2>I Tuoi Mazzi</h2>
                <div class="decks-container">
                    <table>
                        <tr th:each="mazzo:${mazzi}" class="deck-header" th:utext="${mazzo}">
                        </tr>
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
