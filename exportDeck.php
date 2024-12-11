<?php
if(!isset($_GET["mazzo"])){
    if(isset($_GET["from"])){
        ?><meta http-equiv="refresh" content="0; url=<?php echo $_GET["from"]?>"><?
    }else{
        ?><meta http-equiv="refresh" content="0; url=mazzi"><?php
    }
}
require_once "header.php";
require_once "orderCard.php";
header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="' . $_GET["mazzo"].".txt" . '"');
$query = "select ca.espansione, ca.numero, ca.aspettoPrimario, ca.aspettoSecondario, m.nome as mazzo, ca.tipo, ca.nome, ca.titolo from composizione co, carte ca, mazzi m where co.espansione = ca.espansione and co.numero = ca.numero and m.nome = '".$_GET["mazzo"]."' and m.id = co.idMazzo order by uscita, numero";
$resultSet = $GLOBALS["conn"] -> query($query);
$result = $_GET["mazzo"]."\n";
$rs = [];
while($line = $resultSet->fetch_assoc()){
    array_push($rs, $line);
}
mergeSort($rs);
foreach($rs as $line){
    $result = $result.$line["espansione"]."_".$line["numero"]."\t\t".strtoupper($line["nome"]).($line["titolo"]!=='0'?" ".$line["titolo"]:"")."\n";
}
echo $result;