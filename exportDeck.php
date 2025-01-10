<?php
require_once "header.php";
$mazzo=$conn->query("select nome from mazzi where id = '".$_GET["idMazzo"]."'")->fetch_assoc()["nome"];
require_once "orderCard.php";
header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="' . $mazzo.".txt" . '"');
$query = "select ca.espansione, ca.numero, ca.aspettoPrimario, ca.aspettoSecondario, m.nome as mazzo, ca.tipo, ca.nome, ca.titolo from composizione co, carte ca, mazzi m where co.espansione = ca.espansione and co.numero = ca.numero and m.id = '".$_GET["idMazzo"]."' and m.id = co.idMazzo order by uscita, numero";
$resultSet = $GLOBALS["conn"] -> query($query);
$result = $mazzo."\n";
$rs = [];
while($line = $resultSet->fetch_assoc()){
    array_push($rs, $line);
}
mergeSort($rs);
foreach($rs as $line){
    $result = $result.$line["espansione"]."_".$line["numero"]."\t\t".strtoupper($line["nome"]).($line["titolo"]!=='0'?" ".$line["titolo"]:"")."\n";
}
echo $result;