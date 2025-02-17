<!DOCTYPE html>
<html lang="it" class="<?php echo $file;?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>mazzi</title>
        <!-- <link rel="stylesheet" href="css/mazzi.css"> -->
        <!-- <link rel="stylesheet" href="css/cartaPopUp.css"> -->
    </head>
    <?php
        require_once "header.php";
        require_once "orderCard.php";
    ?>
    <?php
    session_start();
    if (!isset($_SESSION['user'])) {
        echo '<meta http-equiv="refresh" content="0;url=login.php">';
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

    echo '<table>';
    echo '<thead><tr><th>Immagine 1</th><th>Immagine 2</th><th>Icona</th><th>Nome Mazzo</th></tr></thead>';
    echo '<tbody>';

    foreach ($mazzi as $mazzo) {
        $composizioneQuery = "SELECT * FROM composizione WHERE idMazzo = ?";
        $stmtComposizione = $conn->prepare($composizioneQuery);
        $stmtComposizione->bind_param('s', $mazzo['id']);
        $stmtComposizione->execute();
        $resultComposizione = $stmtComposizione->get_result();
        $carte = $resultComposizione->fetch_all(MYSQLI_ASSOC);

        $orderCard = mergeSort($carte);
        $firstCard = $orderCard[0];
        $expansion = $firstCard['espansione'];
        $number = str_pad($firstCard['numero'], 3, '0', STR_PAD_LEFT);

        echo '<tr class="mazzo-header" data-mazzo-id="' . $mazzo['id'] . '">';
        echo '<td><img src="https://swudb.com/images/cards/' . $expansion . '/' . $number . '-portrait.png" alt="Card Portrait"></td>';
        echo '<td><img src="https://swudb.com/images/cards/' . $expansion . '/' . $number . '.png" alt="Card"></td>';
        echo '<td><img src="https://imgs.search.brave.com/8lh3CqznYphqQs7SYu1sy98oK3cOR-SqnP2fN0vs8UQ/rs:fit:500:0:0:0/g:ce/aHR0cHM6Ly9pY29ucy52ZXJ5aWNvbi5jb20vcG5nLzEyOC9taXNjZWxsYW5lb3VzL2Vhc2Vtb2ItaWNvbi9leHBvcnQtZmlsZS0xLnBuZw" alt="Icon"></td>';
        echo '<td>' . htmlspecialchars($mazzo['nome']) . '</td>';
        echo '</tr>';

        $composizioneQuery = "SELECT * FROM composizione WHERE idMazzo = ?";
        $stmtComposizione = $conn->prepare($composizioneQuery);
        $stmtComposizione->bind_param('s', $mazzo['id']);
        $stmtComposizione->execute();
        $resultComposizione = $stmtComposizione->get_result();
        $carte = $resultComposizione->fetch_all(MYSQLI_ASSOC);

        foreach ($carte as $carta) {
            echo '<tr class="mazzo-carta" data-mazzo-id="' . $mazzo['id'] . '">';
            echo '<td><img src="./img/rimuovi.png" alt="Rimuovi"></td>';
            echo '<td><img src="./img/collezione.png" alt="Collezione"></td>';
            echo '<td><img src="https://imgs.search.brave.com/8lh3CqznYphqQs7SYu1sy98oK3cOR-SqnP2fN0vs8UQ/rs:fit:500:0:0:0/g:ce/aHR0cHM6Ly9pY29ucy52ZXJ5aWNvbi5jb20vcG5nLzEyOC9taXNjZWxsYW5lb3VzL2Vhc2Vtb2ItaWNvbi9leHBvcnQtZmlsZS0xLnBuZw" alt="Icon"></td>';
            echo '<td>' . htmlspecialchars($mazzo['nome']) . '</td>';
            echo '<td>' . htmlspecialchars($carta['espansione']) . '</td>';
            echo '<td>' . htmlspecialchars($carta['numero']) . '</td>';
            // Add other card attributes here
            echo '</tr>';
        }
    }

    echo '</tbody>';
    echo '</table>';
    ?>
    <script>
    document.querySelectorAll('.mazzo-header').forEach(header => {
        header.addEventListener('click', () => {
            const mazzoId = header.getAttribute('data-mazzo-id');
            document.querySelectorAll(`.mazzo-carta[data-mazzo-id="${mazzoId}"]`).forEach(carta => {
            carta.style.display = carta.style.display === 'none' ? 'table-row' : 'none';
            });
        });
    });
    
    // Initially hide all card rows
    const mazzoId = header.getAttribute('data-mazzo-id');
    document.querySelectorAll(`.mazzo-carta[data-mazzo-id="${mazzoId}"]`).forEach(carta => {
        carta.style.display = 'none';
    });
    </script>
</html>