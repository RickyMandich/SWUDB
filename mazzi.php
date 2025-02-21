<!DOCTYPE html>
<html lang="it" class="<?php echo $file;?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>mazzi</title>
        <link rel="stylesheet" href="css/profilo.css">
        <!-- <link rel="stylesheet" href="css/cartaPopUp.css"> -->
    </head>
    <?php
        require_once "header.php";
        require_once "orderCard.php";
    ?>
    <body>
        <div class="container">
            <?php
            session_start();
            if (!isset($_SESSION['user'])) {
                echo '<meta http-equiv="refresh" content="0;url=login?from=mazzi">';
                exit();
            }

            $user = unserialize($_SESSION['user']);
            $conn = $GLOBALS['conn'];
            
            $mazziQuery = "SELECT * FROM mazzi WHERE codUtente = ? OR public = 1";
            $params = [$user->getId()];
            
            if (isset($_GET['mazzi'])) {
                $mazziQuery .= " AND id = ?";
                $params[] = $_GET['mazzi'];
            }
            
            $stmt = $conn->prepare($mazziQuery);
            $stmt->bind_param(str_repeat('s', count($params)), ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $mazzi = $result->fetch_all(MYSQLI_ASSOC);
            $template = $conn->query("SELECT m.nome as mazzo, c.* FROM carte c, mazzi m LIMIT 1")->fetch_assoc();
            
            echo '<table>';
            echo '<thead><tr><th>Elimina</th><th>Aggiungi alla collezione</th><th>Esporta mazzo/mancante</th>';
            foreach($template as $key => $value){
                echo '<th>' . htmlspecialchars($key) . '</th>';
            }
            echo '</tr></thead>';
            echo '<tbody>';
            
            foreach ($mazzi as $mazzo) {
                $composizioneQuery = "SELECT m.nome as mazzo, ca.* FROM composizione co, carte ca, mazzi m WHERE idMazzo = ? and ca.espansione = co.espansione and ca.numero = co.numero and m.id = co.idMazzo";
                $stmtComposizione = $conn->prepare($composizioneQuery);
                $stmtComposizione->bind_param('s', $mazzo['id']);
                $stmtComposizione->execute();
                $resultComposizione = $stmtComposizione->get_result();
                $carte = $resultComposizione->fetch_all(MYSQLI_ASSOC);
                
                $orderCard = mergeSort($carte);
                $firstCard = $orderCard[0];
                $expansion = $firstCard['espansione'];
                $number = str_pad($firstCard['numero'], $numeri[$firstCard["espansione"]], '0', STR_PAD_LEFT);

                if(count($orderCard) > 1){
                    echo '<tr class="mazzo-header" data-mazzo-id="' . $mazzo['id'] . '">';
                    echo '<td class="image"><im-g src="https://swudb.com/images/cards/' . $expansion . '/' . $number . '-portrait.png" alt="Card Portrait"></td>';
                    echo '<td class="image"><im-g src="https://swudb.com/images/cards/' . $expansion . '/' . $number . '.png" alt="Card"></td>';
                    echo '<td class="image"><im-g src="https://imgs.search.brave.com/8lh3CqznYphqQs7SYu1sy98oK3cOR-SqnP2fN0vs8UQ/rs:fit:500:0:0:0/g:ce/aHR0cHM6Ly9pY29u/cy52ZXJ5aWNvbi5j/b20vcG5nLzEyOC9t/aXNjZWxsYW5lb3Vz/L2Vhc2Vtb2ItaWNv/bi9leHBvcnQtZmls/ZS0xLnBuZw" alt="Icon"></td>';
                    echo '<td>' . htmlspecialchars($mazzo['nome']) . '</td>';
                    foreach($orderCard[0] as $value){
                        echo '<td></td>';
                    }
                    echo '</tr>';
                }
                
                foreach ($orderCard as $carta) {
                    echo '<tr class="mazzo-carta" data-mazzo-id="' . $mazzo['id'] . '">';
                    echo '<td class="image"><im-g src="./img/rimuovi.png" alt="Rimuovi"></td>';
                    echo '<td class="image"><im-g src="./img/collezione.png" alt="Collezione"></td>';
                    echo '<td class="image"><im-g src="https://imgs.search.brave.com/8lh3CqznYphqQs7SYu1sy98oK3cOR-SqnP2fN0vs8UQ/rs:fit:500:0:0:0/g:ce/aHR0cHM6Ly9pY29u/cy52ZXJ5aWNvbi5j/b20vcG5nLzEyOC9t/aXNjZWxsYW5lb3Vz/L2Vhc2Vtb2ItaWNv/bi9leHBvcnQtZmls/ZS0xLnBuZw" alt="Icon"></td>';
                    // echo '<td class="image">'.'<im-g src="https://swudb.com/images/cards/' . $carta["espansione"] . '/' . $carta["numero"] . '.png" alt="Card">'."</td>";
                    foreach ($carta as $value) {
                        $value = str_replace("\n", "<br>", $value);
                        echo "<td>$value</td>";
                    }
                    echo '</tr>';
                }
            }
            
            echo '</tbody>';
            echo '</table>';
            ?>
        </div>
    </body>
    <style>
        .mazzo-header {
            background-color: #6b98c1;
            cursor: pointer;
        }
        
        .mazzo-carta {
            background-color: lightgray;
            display: none;
        }
        
        img {
            width: 5vw;
            height: auto;
        }
        
        table {
            color: black;
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            border: 1px solid black;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: darkblue;
            color: white;
        }

        img{
            width: 5vw;
            height: auto;
        }
    </style>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            document.querySelectorAll(".image").forEach(img=>{
                img.innerHTML = img.innerHTML.replaceAll("im-g", "img");
            });
        });
        document.querySelectorAll('.mazzo-header').forEach(header => {
            header.addEventListener('click', () => {
                let mazzoId = header.getAttribute('data-mazzo-id');
                document.querySelectorAll(`.mazzo-carta[data-mazzo-id="${mazzoId}"]`).forEach(carta => {
                carta.style.display = carta.style.display === 'table-row' ? 'none' : 'table-row';
                });
            });
        });
    </script>
</html>