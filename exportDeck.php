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
echo "select c.espansione, c.numero, c.nome, c.titolo from carte c, mazzi m where m.espansione = c.espansione and m.numero = c.numero and m.mazzo = '".$_GET["mazzo"]."'";
$resultSet = $conn -> query("select c.espansione, c.numero, c.nome, c.titolo from carte c, mazzi m where m.espansione = c.espansione and m.numero = c.numero and m.mazzo = '".$_GET["mazzo"]."'");
$result = $_GET["mazzo"]."\n";
var_dump($result);
while($line = $resultSet->fetch_assoc()){
    $result = $result.$line["espansione"]."_".$line["numero"]."\t".strtoupper($line["nome"])." ".$line["titolo"]."\n";
}
//download_file($result, $_GET["mazzo"].".txt");