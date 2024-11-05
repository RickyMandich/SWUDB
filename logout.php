<?php
echo "ciao";
require_once("header.php");
if(isset($_SESSION["user"])){
    unset($_SESSION["user"]);
}
?>
<meta http-equiv="refresh" content="0; url=./login">