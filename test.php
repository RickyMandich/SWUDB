<?php
echo "|".$_SERVER['SERVER_ADDR']."|<br>";
echo gethostbyname($_SERVER['SERVER_ADDR'])."<br>";
echo dns_get_record("swudb.altervista.org")."<br>";
echo gethostbyname(gethostname())."<br>";