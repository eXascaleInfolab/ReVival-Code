<?php

$callback = $_GET['callback'];
if (!preg_match('/^[a-zA-Z0-9_]+$/', $callback)) {
    die('Invalid callback name');
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

$norm = @$_GET['norm'];
$dataset = @$_GET['dataset'];

include '../connect.php';

// set some utility variables
$range = $end - $start;
$start_time = gmstrftime('%Y-%m-%d %H:%M:%S', $start / 1000);
$end_time = gmstrftime('%Y-%m-%d %H:%M:%S', $end / 1000);

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

$explore_object = new stdClass();
$series_object = new stdClass();

// Prepare a query for execution
$result = monetdb_prepare($conn, "my_query",
    'SELECT
                                series.id as id,
                                series.title as title  
                            FROM
                                sets_series
                            LEFT JOIN 
                                series
                            ON
                                sets_series.serie_id = series.id
                            WHERE 
                                sets_series.set_id = $1
                            ORDER BY
                               title');

$result = monetdb_execute($conn, "my_query", array($dataset));

/*$query = "
    SELECT
        series.id as id,
        series.title as title
    FROM
        sets_series
    LEFT JOIN
        series
    ON
        sets_series.serie_id = series.id
    WHERE
        sets_series.set_id = '$dataset'
    ORDER BY
       title
";

$result = monetdb_query($conn, $query);*/

if (!$result) {
    die(monetdb_last_error());
}

$points = array();
$series = array();

while ($row = monetdb_fetch_assoc($result)) {
    extract($row);
    $series[$id]['id'] = $id;
    $series[$id]['title'] = $title;
}

foreach ($series as &$serie) {
    $id = $serie['id'];

    //if normalization is required, get relevant values from the database
    if ($norm != 0) {

        $query = "
                SELECT
                    avg($table.value) as mean,
                    sys.stddev_samp($table.value) as stddev,
                    min($table.value) as min,
                    max($table.value) as max
                FROM 
                    $table 
                WHERE 
                    $table.value IS NOT NULL AND $table.series_id = $id
                GROUP BY
                    $table.series_id
            ";
        $result = monetdb_query($conn, $query);

        if (!$result) {
            die(monetdb_last_error());
        }

        while ($row = monetdb_fetch_assoc($result)) {
            $mean = $row["mean"];
            $stddev = $row["stddev"];
            $min = $row["min"];
            $max = $row["max"];
        }
    }

    $query = "
            SELECT
                CONVERT(sys.timestamp_to_str($table.datetime, '%s'), int) * 1000 as datetime,
                $table.value as value         
            FROM
                $table
            WHERE 
                $table.series_id = $id AND datetime between '$start_time' and '$end_time'
            ORDER BY
               datetime
        ";
    $result = monetdb_query($conn, $query);

    if (!$result) {
        die(monetdb_last_error());
    }

    $points = array();

    while ($row = monetdb_fetch_assoc($result)) {
        extract($row);
        if (is_null($value)) {
            $points[] = array(floatval($datetime), NULL);
        } else {
            if ($norm == 0) {
                $points[] = array(floatval($datetime), floatval($value));
            } elseif ($norm == 1) {
                $points[] = array(floatval($datetime), (floatval($value) - $mean) / $stddev);
            } elseif ($norm == 2) {
                $points[] = array(floatval($datetime), (floatval($value) - $min) / ($max - $min));
            }
        }
    }
    $serie['points'] = array_values($points);
}

$explore_object->series = array_values($series);

// print it
header('Content-Type: text/javascript');

echo $callback . "([\n" . json_encode($explore_object) . "\n]);";

monetdb_disconnect();
?>
