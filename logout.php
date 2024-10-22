<?php
echo "ciao";
session_start();
if(isset($_SESSION["user"])){
    unset($_SESSION["user"]);
}
?>
<meta http-equiv="refresh" content="0; url=./logIn">