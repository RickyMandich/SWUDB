<?php
    require_once "header.php";
    $getNumero = function($el){
        $query = "select numero from carte where espansione = '".$el["espansione"]."' and nome like '".str_replace("'", "\'", $el["nome"])."' and titolo like '".str_replace("'", "\'", $el["titolo"])."' order by numero";
        $result = $GLOBALS["conn"]->query($query)->fetch_assoc();
        $numero = $result["numero"];
        return $numero ?? $el["numero"];
    };

    