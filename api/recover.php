<?php session_start();
include '../logger.php';
include '../connect.php';

include '../src/utils.php';
use ReVival\utils;

$table = Utils::getTableName($start, $end);

// required headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

$raw_input = file_get_contents('php://input');
$json = json_decode($raw_input);
// request params
$start = (int)$json -> {'start'};
$end = (int)$json -> {'end'};
$norm = (int)$json -> {'norm'};
$ground_thruth = (bool)$json -> {'ground'};
$threshold = (float)$json -> {'threshold'};
$series_ids = $json -> {'series'};

// has the cached series with drop values from /api/drop.php
$explore_object = clone $_SESSION['drop'];

// $data = get_serie_data($conn, $table, $series_ids[0], $start, $end, 1);
// var_dump($data);
http_response_code(200);
// echo json_encode($explore_object);
echo "TODO";
monetdb_disconnect();

?>