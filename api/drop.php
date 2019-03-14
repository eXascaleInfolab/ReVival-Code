<?php session_start();
include '../logger.php';

// required headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

$raw_input = file_get_contents('php://input');
$json = json_decode($raw_input);
$drop = $json -> {'drop'};
$norm = $json -> {'norm'};
// series ids for which the drop % will be applied.
$series_ids = $json -> {'series'};

if ($drop === 0 || count($series_ids) === 0) {
    http_response_code(400);
    exit;
}

$start = $json -> {'start'};
$end = $json -> {'end'};

include '../connect.php';
include '../src/utils.php';
use ReVival\utils;

$table = Utils::getTableName($start, $end);

// get first id
$s_id = $series_ids[0];
$qry = "
select
    sys.epoch(datetime) * 1000 as tick
from $table 
where series_id=$s_id and datetime>=sys.epoch($start) and datetime<=sys.epoch($end)
";
$res = monetdb_query($conn, monetdb_escape_string($qry)) or trigger_error(monetdb_last_error());
$rows = monetdb_num_rows($res);

$time_stamps = array();
while ( $row = monetdb_fetch_assoc($res) )
{
    array_push($time_stamps, (int)$row['tick']);
}

// reserve 10% threshold
$threshold = (int)(ceil(0.10 * $rows));
// drop can be up to 80%
$shift = (int)($drop * $rows);
$delta = (int)($shift / 2);
$start_index = $threshold + $delta;
$end_index = $rows - ($threshold + $delta);

// var_dump($time_stamps);
// echo "threshold -> $threshold";
// echo "shift -> $shift";
// echo "start_index -> $start_index";
// echo "end_index -> $end_index";

$indices = Utils::partition($start_index, $end_index, count($series_ids) - 1);
// var_dump($indices);

$response = new stdClass();
foreach($series_ids as $key => $value) {
    $start_drop_ts = $time_stamps[$indices[$key] - $delta];
    $end_drop_ts = $time_stamps[$indices[$key] + $delta];
    $points = drop_values($conn, $table, $value, $start, $end, $start_drop_ts, $end_drop_ts, $norm);
    $response -> $value = $points;
}

http_response_code(200);
echo json_encode($response);
monetdb_disconnect();
?>