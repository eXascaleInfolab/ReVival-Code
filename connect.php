<?php
require 'monetdb/php_monetdb.php';
require 'monetdb/php_monetdb_ext.php';
$conn = monetdb_connect(
    $lang = "sql",
    $host = "127.0.0.1", $port = "50000",
    $username = "oliver", $password = "R0J-K9wj",
    $database = "revival");
if (!$conn) {
    echo 'DB connection has failed to be established';
    die(monetdb_last_error());
}
monetdb_query($conn, "SET SCHEMA data");
monetdb_query($conn, "SET TIME ZONE INTERVAL '+00:00' HOUR TO MINUTE");
?>
