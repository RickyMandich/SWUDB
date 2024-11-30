<?php
function download_file($stringa, $nome_file) {
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . $nome_file . '"');
    echo $stringa;
    exit;
}

if(!isset($_GET["mazzo"])){
    if(isset($_GET["from"])){
        ?><meta http-equiv="refresh" content="0; url=<?php echo $_GET["from"]?>"><?
    }else{
        ?><meta http-equiv="refresh" content="0; url=mazzi"><?php
    }
}
require_once "header.php";
$resultSet = $conn -> query("select ca.espansione, ca.numero, ca.nome, ca.titolo from composizione co carte ca, mazzi m where m.espansione = ca.espansione and m.numero = ca.numero and m.nome = '".$_GET["mazzo"]."' and m.id = co.idMazzo order by uscita, numero");
$result = $_GET["mazzo"]."\n";
while($line = $resultSet->fetch_assoc()){
    $result = $result.$line["espansione"]."_".$line["numero"]."\t\t".strtoupper($line["nome"]).($line["titolo"]!=='0'?" ".$line["titolo"]:"")."\n";
}
download_file($result, $_GET["mazzo"].".txt");