<?php

//////////////////////////////////////////////////////////////////////////////
//                                                                          //
//           RETRIEVAL OF PARAMETERS AND RESOURCES + PREPARATIONS           //
//                                                                          //
//////////////////////////////////////////////////////////////////////////////

// Prepare the database connection
include '../connect.php';

// Include path to global functions and helpers
include '../algebra.php';

// Get required parameters from the URL
$callback = $_GET['callback'];
if (!preg_match('/^[a-zA-Z0-9_]+$/', $callback)) {
    die('Invalid callback name');
}
$dataset = @$_GET['dataset'];
if ($dataset && !preg_match('/^[0-9]+$/', $dataset)) {
    die("Invalid dataset parameter: $dataset");
}
$start = @$_GET['start'];
if ($start && !preg_match('/^[0-9]+$/', $start)) {
    die("Invalid start parameter: $start");
}
$end = @$_GET['end'];
if ($end && !preg_match('/^[0-9]+$/', $end)) {
    die("Invalid end parameter: $end");
}

if (!$end) $end = time() * 1000;

$startTime = gmstrftime('%Y-%m-%d %H:%M:%S', $start / 1000);
$endTime = gmstrftime('%Y-%m-%d %H:%M:%S', $end / 1000);

// Instantiate the object that eventually be returned in JSON format
$retrieve_object = new stdClass();

$range = $end - $start;
// find the right table
// up to 8 month range loads hourly data
if ($range < 8 * 31 * 24 * 3600 * 1000) {
    $table = 'hourly';
    // up to 6 years range loads daily data
} elseif ($range < 6 * 12 * 31 * 24 * 3600 * 1000) {
    $table = 'daily';
    // greater range loads weekls data
} else {
    $table = 'weekly';
}

$pred_percent = intval($_GET['pred_percent']);

//////////////////////////////////////////////////////////////////////////////
//                                                                          //
//                  RETRIEVAL + PRE-PROCESSING OF DATA FROM DB              //
//                                                                          //
//////////////////////////////////////////////////////////////////////////////

// get relevant values for normalization from the database

$stat = array();
$info_object = new stdClass();

// Since there are multiple reference series, create an array of reference series arrays
$reference_series = array();
$reference_series_ids = $_GET['reference_serie'];

// Query the data of each reference serie from the database
foreach ($reference_series_ids as $reference_series_id)
{
    $query = "
            SELECT
                avg($table.value) as mean,
                sys.stddev_samp($table.value) as stddev,
                min($table.value) as min,
                max($table.value) as max
            FROM 
                $table 
            WHERE 
                $table.value IS NOT NULL AND $table.series_id = $reference_series_id
            GROUP BY
                $table.series_id
        ";

    $result = monetdb_query($conn, $query);

    if (!$result) {
        die(monetdb_last_error());
    }

    while ($row = monetdb_fetch_assoc($result)) {
        $stat[$reference_series_id] = array();
        $stat[$reference_series_id]['mean'] = $row["mean"];
        $stat[$reference_series_id]['stddev'] = $row["stddev"];
        $stat[$reference_series_id]['min'] = $row["min"];
        $stat[$reference_series_id]['max'] = $row["max"];
    }

    $query = "
            SELECT
                sys.epoch(datetime) * 1000 as datetime,
                $table.value as value,
                series.title
            FROM
                series
            LEFT JOIN
                $table
            ON
                series.id = $table.series_id
            WHERE 
                series.id = $reference_series_id AND datetime between '$startTime' and '$endTime'
            ORDER BY
               datetime
        ";

    $result = monetdb_query($conn, $query);

    if (!$result) {
        die(monetdb_last_error());
    }

    $reference_series_points = array();

    while ($row = monetdb_fetch_assoc($result)) {
        extract($row);
        if (is_null($value)) {
            $reference_series_points[] = array(floatval($datetime), NULL);
        } else {
            $reference_series_points[] = array(floatval($datetime), floatval($value));
        }
    }

    $reference_series[$reference_series_id]['id'] = $reference_series_id;
    $reference_series[$reference_series_id]['title'] = $title;

    $reference_series[$reference_series_id]['points'] = array();
    $reference_series[$reference_series_id]['points']['raw'] = array_values($reference_series_points);
    $reference_series[$reference_series_id]['points']['znorm'] =
        z_norm($reference_series_points, $stat[$reference_series_id]['mean'], $stat[$reference_series_id]['stddev']);
    $reference_series[$reference_series_id]['points']['minmax'] =
        minmax_norm($reference_series_points, $stat[$reference_series_id]['min'], $stat[$reference_series_id]['max']);
}

// Add all reference series to the object

//////////////////////////////////////////////////////////////////////////////
//                                                                          //
//                 CENTROID DECOMPOSITION + POST-PROCESSING                 //
//                                                                          //
//////////////////////////////////////////////////////////////////////////////


// Push all series (base series + reference series) to matrix X (an array of arrays)
$x = array();

$m = count($reference_series[$reference_series_ids[0]]['points']['raw']);    // number of values per series: rows in the matrix
$n = count($reference_series);   // number of series: columns in the matrix

$granularity = $reference_series[$reference_series_ids[0]]['points']['raw'][1][0] - $reference_series[$reference_series_ids[0]]['points']['raw'][0][0];
//$pred_start = floor($end - (($end - $start) * $pred_percent) / 100);
$pred_start = $end - $granularity * $pred_percent;

$start_compute = microtime(true);
$x = predict_udf($conn, $table, $start, $end, $dataset, $reference_series_ids, $pred_start);
$time_elapsed = (microtime(true) - $start_compute) * 1000;

$info_object->start_time = $startTime;
$info_object->end_time = $endTime;
$info_object->duration = $time_elapsed;

// merge values of each series with datetimes to achieve [x,y] points. required by highcharts...
$predicted_series = array();
$ground_series = array();
foreach ($reference_series_ids as $reference_series_id)
{
    $predicted_series_points = array();
    $ground_series_points = array();

    $lastidx = 0;

    if (!isset($x["".$reference_series_id][0]))
    {
        echo "prediction failed";
        exit(500);
    }

    for ($i = 0; $x["".$reference_series_id][$i][0] < $pred_start; $i++)
    {
        $oldts = $x["".$reference_series_id][$i][0];
        $predicted_series_points[] = array($oldts, NULL);
        $ground_series_points[] = array($oldts, NULL);
        $lastidx = $i;
    }

    //re-process

    for ($i = 0; $x["".$reference_series_id][$i][0] < $pred_start; $i++)
    {
        $oldts = $x["".$reference_series_id][$i][0];
        if (is_null($reference_series[$reference_series_id]['points']['raw'][$i][1]))
        {
            $predicted_series_points[$i] = $x["".$reference_series_id][$i];
            if ($i != 0)
            {
                $predicted_series_points[$i - 1] = $x["".$reference_series_id][$i - 1];
            }
            $predicted_series_points[$i + 1] = $x["".$reference_series_id][$i + 1];
        }
    }

    $predicted_series_points[$lastidx] = $reference_series[$reference_series_id]['points']['raw'][$lastidx];
    $ground_series_points[$lastidx] = $reference_series[$reference_series_id]['points']['raw'][$lastidx];

    for ($i = $lastidx + 1; $i < $m; $i++)
    {
        $predicted_series_points[] = $x["".$reference_series_id][$i];//should be already an array of [dt, val]
        $ground_series_points[] = $reference_series[$reference_series_id]['points']['raw'][$i];

        $oldts = $reference_series[$reference_series_id]['points']['raw'][$i][0];
        $reference_series[$reference_series_id]['points']['raw'][$i] = array($oldts, NULL);
        $reference_series[$reference_series_id]['points']['znorm'][$i] = array($oldts, NULL);
        $reference_series[$reference_series_id]['points']['minmax'][$i] = array($oldts, NULL);
    }

    $predicted_series[$reference_series_id]['id'] = $reference_series_id;
    $predicted_series[$reference_series_id]['title'] = $reference_series[$reference_series_id]['title'] . " (prediction)";

    $predicted_series[$reference_series_id]['points'] = array();
    $predicted_series[$reference_series_id]['points']['raw'] = array_values($predicted_series_points);
    $predicted_series[$reference_series_id]['points']['znorm'] =
        z_norm($predicted_series_points, $stat[$reference_series_id]['mean'], $stat[$reference_series_id]['stddev']);
    $predicted_series[$reference_series_id]['points']['minmax'] =
        minmax_norm($predicted_series_points, $stat[$reference_series_id]['min'], $stat[$reference_series_id]['max']);

    $ground_series[$reference_series_id]['id'] = $reference_series_id;
    $ground_series[$reference_series_id]['title'] = $reference_series[$reference_series_id]['title'] . " (ground)";

    $ground_series[$reference_series_id]['points'] = array();
    $ground_series[$reference_series_id]['points']['raw'] = array_values($ground_series_points);
    $ground_series[$reference_series_id]['points']['znorm'] =
        z_norm($ground_series_points, $stat[$reference_series_id]['mean'], $stat[$reference_series_id]['stddev']);
    $ground_series[$reference_series_id]['points']['minmax'] =
        minmax_norm($ground_series_points, $stat[$reference_series_id]['min'], $stat[$reference_series_id]['max']);
}

$retrieve_object->reference_series = array_values($reference_series);
$retrieve_object->predicted_series = array_values($predicted_series);
$retrieve_object->ground_series = array_values($ground_series);
$retrieve_object->info = $info_object;

//////////////////////////////////////////////////////////////////////////////
//                                                                          //
//                  PRINTING OF ALL DATA IN JSON FORMAT                     //
//                                                                          //
//////////////////////////////////////////////////////////////////////////////


// Return data (the retrieve object) in a file in JSON notation
header('Content-Type: text/javascript');
echo $callback . "([\n" . json_encode($retrieve_object) . "\n]);";
echo "// startTime: " . $startTime . ", endTime: " . $endTime;

// Close the database connection
monetdb_disconnect();

//////////////////////////////////////////////////////////////////////////////
//                                                                          //
//                        LOCAL FUNCTIONS + HELPERS                         //
//                                                                          //
//////////////////////////////////////////////////////////////////////////////

function z_norm($points, $mean, $stddev)
{
    $points = array_values($points);
    $points_length = count($points);
    for ($i = 0; $i < $points_length; $i++) {
        if ($points[$i][1] !== NULL) {
            $points[$i][1] = ($points[$i][1] - $mean) / $stddev;
        }
    }
    return $points;
}

function minmax_norm($points, $min, $max)
{
    $points_length = count($points);
    for ($i = 0; $i < $points_length; $i++) {
        if ($points[$i][1] !== NULL) {
            $points[$i][1] = ($points[$i][1] - $min) / ($max - $min);
        }
    }
    return $points;
}

?>
