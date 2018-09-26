<?php

//////////////////////////////////////////////////////////////////////////////
//                                                                          //
//           RETRIEVAL OF PARAMETERS AND RESOURCES + PREPARATIONS           //
//                                                                          //
//////////////////////////////////////////////////////////////////////////////


// Get required parameters from the URL
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

$range = @$_GET['range'];

if ($range == 7) {
    $end = $start + 7 * 24 * 3600 * 1000;
} elseif ($range == 30) {
    $end = $start + 30 * 24 * 3600 * 1000;
} elseif ($range == 365) {
    $end = $start + 365 * 24 * 3600 * 1000;
}

$startTime = gmstrftime('%Y-%m-%d %H:%M:%S', $start / 1000);
$endTime = gmstrftime('%Y-%m-%d %H:%M:%S', $end / 1000);

$mode = @$_GET['mode'];

$base_serie_id = pg_escape_string($_GET['base_serie']);

$comparison_serie_id = pg_escape_string($_GET['comparison_serie']);
if ($comparison_serie_id == "") {
    $comparison_serie_id = null;
}

$threshold = @$_GET['threshold'];

// Instantiate the object that eventually be returned in JSON format
$retrieve_object = new stdClass();

// Include path to global functions and helpers
//include '../functions.php';
include '../funct.php';

// Prepare the database connection
include '../connect.php';
pg_query("SET TIME ZONE '+00:00'");
$table = 'hourly'; //final


//////////////////////////////////////////////////////////////////////////////
//                                                                          //
//                  RETRIEVAL + PRE-PROCESSING OF DATA FROM DB              //
//                                                                          //
//////////////////////////////////////////////////////////////////////////////

// get relevant values for normalization from the database

$stat = array();

$query = "
        SELECT
            avg(cast(data.$table.value as float)) as mean,
            stddev_samp(cast(data.$table.value as float)) as stddev,
            min(cast(data.$table.value as float)) as min,
            max(cast(data.$table.value as float)) as max
        FROM 
            data.$table 
        WHERE 
            data.$table.value <> 'null' AND data.$table.series_id = $base_serie_id
    ";
$result = pg_query($conn, $query);

if (!$result) {
    exit;
}

while ($row = pg_fetch_assoc($result)) {
    $stat[$base_serie_id] = array();
    $stat[$base_serie_id]['mean'] = $row["mean"];
    $stat[$base_serie_id]['stddev'] = $row["stddev"];
    $stat[$base_serie_id]['min'] = $row["min"];
    $stat[$base_serie_id]['max'] = $row["max"];
}

// Prepare the SQL query for the base serie
$query = "
        SELECT
            extract(epoch FROM data.$table.datetime)*1000 as datetime,
            data.$table.value as value,
            data.series.title
        FROM
            data.series
        LEFT JOIN
            data.$table
        ON
            data.series.id = data.$table.series_id
        WHERE 
            data.series.id = $base_serie_id AND datetime between '$startTime' and '$endTime'
        ORDER BY
           datetime
    ";

// Extract data from result, store in arrays
$datetime_values = array(); // needed to merge with the retrieved values after CD (Highcharts requires points [x,y], not values...)
$base_serie_points = array();

$result = pg_query($conn, $query);

if (!$result) {
    exit;
}

while ($row = pg_fetch_assoc($result)) {
    extract($row);
    $datetime_values[] = floatval($datetime);
    if ($value == "null") {
        $base_serie_points[] = array(floatval($datetime), NULL);
    } else {
        $base_serie_points[] = array(floatval($datetime), floatval($value));
    }
}

//$base_serie_points = z_norm($base_serie_points, $stat[$base_serie_id]['mean'], $stat[$base_serie_id]['stddev']);

$info_object = new stdClass();

// Create and fill arrays containing various series
$base_serie = array();      // Series containing just the existing points in the base serie
$base_serie['id'] = $base_serie_id;
$base_serie['title'] = $title;
$base_serie['points'] = array();
$base_serie['points']['raw'] = array_values($base_serie_points);
$base_serie['points']['znorm'] = z_norm($base_serie_points, $stat[$base_serie_id]['mean'], $stat[$base_serie_id]['stddev']);
//$base_serie['points']['raw'] = z_norm($base_serie_points, $stat[$base_serie_id]['mean'], $stat[$base_serie_id]['stddev']);
//$base_serie['points']['minmax'] = z_norm($base_serie_points, $stat[$base_serie_id]['mean'], $stat[$base_serie_id]['stddev']);
$base_serie['points']['minmax'] = minmax_norm($base_serie_points, $stat[$base_serie_id]['min'], $stat[$base_serie_id]['max']);

$info_object->base_serie_title = $title;
$non_null_values = count_non_null_values($base_serie_points);
$total_values = count($base_serie_points);
$info_object->base_serie_existing = $non_null_values;
$info_object->base_serie_missing = ($total_values - $non_null_values);

$missing_serie = array();   // Serie to highlight location of missing values in the Highchart range selector
$missing_serie['id'] = -1;
$missing_serie['title'] = $title;
$missing_serie['points'] = array_values(inverse_missing($base_serie_points));

$linear_serie_points = linear_interpolated_points($base_serie_points);

$linear_serie = array();    // Serie containing linear interpolation of missing values
$linear_serie['id'] = -1;
$linear_serie['title'] = $title;
$linear_serie['points'] = array();
$linear_serie['points']['raw'] = array_values($linear_serie_points);
$linear_serie['points']['znorm'] = z_norm($linear_serie_points, $stat[$base_serie_id]['mean'], $stat[$base_serie_id]['stddev']);
$linear_serie['points']['minmax'] = minmax_norm($linear_serie_points, $stat[$base_serie_id]['min'], $stat[$base_serie_id]['max']);

$retrieved_serie = array(); // Serie containing centroid decomoposition of missing values
$retrieved_serie['id'] = 0;
$retrieved_serie['title'] = $title;
$retrieved_serie['points'] = array();
// $retrieved_serie['points'] => added later, after Centroid Decomposition

// Add all series as arrays to an object that will be returned to requester in JSON format
$retrieve_object->original = $base_serie;
$retrieve_object->missing = $missing_serie;
$retrieve_object->linear = $linear_serie;
// $retrieve_object->retrieved_serie => added later, after Centroid Decomposition

// Since there are multiple reference series, create an array of reference serie arrays
$reference_series = array();
$reference_serie_ids = array();
$all_reference_series_points = array();

// Depending on the selected mode of reference series selection, the ids are either already available or not
if ($mode == 'manual') { // Reference serie ids can be fetched from the URL
    $reference_serie_ids = pg_escape_string($_GET['reference_serie']);
} elseif ($mode == 'correlated') { // Reference serie ids must be queried from the database
    $amount = pg_escape_string($_GET['amount']);
    $query = "
            SELECT
                data.corr.value as value, data.corr.series_id_2 as reference_serie_id
            FROM
                data.corr
            WHERE 
                data.corr.series_id_1 = '$base_serie_id'
            ORDER BY
               value DESC
            LIMIT $amount
        ";


    $result = pg_query($conn, $query);

    if (!$result) {
        exit;
    }

    while ($row = pg_fetch_assoc($result)) {
        extract($row);
        array_push($reference_serie_ids, $reference_serie_id);
    }
}


// Query the data of each reference serie from the database
foreach ($reference_serie_ids as $reference_serie_id) {
    //if normalization is required, get relevant values from the database

    $query = "
            SELECT
                avg(cast(data.$table.value as float)) as mean,
                stddev_samp(cast(data.$table.value as float)) as stddev,
                min(cast(data.$table.value as float)) as min,
                max(cast(data.$table.value as float)) as max
            FROM 
                data.$table 
            WHERE 
                data.$table.value <> 'null' AND data.$table.series_id = $reference_serie_id
        ";
    $result = pg_query($conn, $query);

    if (!$result) {
        exit;
    }

    while ($row = pg_fetch_assoc($result)) {
        $stat[$reference_serie_id] = array();
        $stat[$reference_serie_id]['mean'] = $row["mean"];
        $stat[$reference_serie_id]['stddev'] = $row["stddev"];
        $stat[$reference_serie_id]['min'] = $row["min"];
        $stat[$reference_serie_id]['max'] = $row["max"];
    }

    $query = "
            SELECT
                extract(epoch FROM data.$table.datetime)*1000 as datetime,
                data.$table.value as value,
                data.series.title
            FROM
                data.series
            LEFT JOIN
                data.$table
            ON
                data.series.id = data.$table.series_id
            WHERE 
                data.series.id = $reference_serie_id AND datetime between '$startTime' and '$endTime'
            ORDER BY
               datetime
        ";

    $result = pg_query($conn, $query);

    if (!$result) {
        exit;
    }

    $reference_serie_points = array();

    while ($row = pg_fetch_assoc($result)) {
        extract($row);
        if ($value == "null") {
            $reference_serie_points[] = array(floatval($datetime), NULL);
        } else {
            $reference_serie_points[] = array(floatval($datetime), floatval($value));
        }
    }

    // Eliminate missing/null values in the reference serie by applying linear interpolation
    $reference_serie_points = linear_interpolated_points($reference_serie_points);

    array_push($all_reference_series_points, $reference_serie_points);
    //array_push($all_reference_series_points, z_norm($reference_serie_points, $stat[$reference_serie_id]['mean'], $stat[$reference_serie_id]['stddev']));

    $reference_series[$reference_serie_id]['id'] = $reference_serie_id;
    $reference_series[$reference_serie_id]['title'] = $title;
    $reference_series[$reference_serie_id]['points'] = array();
    $reference_series[$reference_serie_id]['points']['raw'] = array_values($reference_serie_points);
    $reference_series[$reference_serie_id]['points']['znorm'] =
        z_norm($reference_serie_points, $stat[$reference_serie_id]['mean'], $stat[$reference_serie_id]['stddev']);
    //$reference_series[$reference_serie_id]['points']['raw'] =
    //    z_norm($reference_serie_points, $stat[$reference_serie_id]['mean'], $stat[$reference_serie_id]['stddev']);
    //$reference_series[$reference_serie_id]['points']['minmax'] =
    //    z_norm($reference_serie_points, $stat[$reference_serie_id]['mean'], $stat[$reference_serie_id]['stddev']);
    $reference_series[$reference_serie_id]['points']['minmax'] =
        minmax_norm($reference_serie_points, $stat[$reference_serie_id]['min'], $stat[$reference_serie_id]['max']);
}

// Add all reference series to the object
$retrieve_object->reference_series = array_values($reference_series);

if (isset($comparison_serie_id)) {

    $query = "
            SELECT
                avg(cast(data.$table.value as float)) as mean,
                stddev_samp(cast(data.$table.value as float)) as stddev,
                min(cast(data.$table.value as float)) as min,
                max(cast(data.$table.value as float)) as max
            FROM 
                data.$table 
            WHERE 
                data.$table.value <> 'null' AND data.$table.series_id = $comparison_serie_id
        ";
    $result = pg_query($conn, $query);

    if (!$result) {
        exit;
    }

    while ($row = pg_fetch_assoc($result)) {
        $stat[$comparison_serie_id] = array();
        $stat[$comparison_serie_id]['mean'] = $row["mean"];
        $stat[$comparison_serie_id]['stddev'] = $row["stddev"];
        $stat[$comparison_serie_id]['min'] = $row["min"];
        $stat[$comparison_serie_id]['max'] = $row["max"];
    }

    // Prepare the SQL query for the comparison serie
    $query = "
            SELECT
                extract(epoch FROM data.$table.datetime)*1000 as datetime,
                data.$table.value as value,
                data.series.title
            FROM
                data.series
            LEFT JOIN
                data.$table
            ON
                data.series.id = data.$table.series_id
            WHERE 
                data.series.id = $comparison_serie_id AND datetime between '$startTime' and '$endTime'
            ORDER BY
               datetime
        ";

    // Extract data from result, store in arrays
    $datetime_values = array(); // needed to merge with the retrieved values after CD (Highcharts requires points [x,y], not values...)
    $comparison_serie_points = array();

    $result = pg_query($conn, $query);

    if (!$result) {
        exit;
    }

    while ($row = pg_fetch_assoc($result)) {
        extract($row);
        $datetime_values[] = floatval($datetime);
        if ($value == "null") {
            $comparison_serie_points[] = array(floatval($datetime), NULL);
        } else {
            $comparison_serie_points[] = array(floatval($datetime), floatval($value));
        }
    }

    // Create and fill arrays containing various series
    $comparison_serie = array();      // Series containing just the existing points in the base serie
    $comparison_serie['id'] = $comparison_serie_id;
    $comparison_serie['title'] = $title;
    $comparison_serie['points'] = array();
    $comparison_serie['points']['raw'] = array_values($comparison_serie_points);
    $comparison_serie['points']['znorm'] =
        z_norm($comparison_serie_points, $stat[$comparison_serie_id]['mean'], $stat[$comparison_serie_id]['stddev']);
    $comparison_serie['points']['minmax'] =
        minmax_norm($comparison_serie_points, $stat[$comparison_serie_id]['min'], $stat[$comparison_serie_id]['max']);
    $retrieve_object->comparison = $comparison_serie;
} else {
    $retrieve_object->comparison = null;
}


//////////////////////////////////////////////////////////////////////////////
//                                                                          //
//                 CENTROID DECOMPOSITION + POST-PROCESSING                 //
//                                                                          //
//////////////////////////////////////////////////////////////////////////////


// Push all series (base serie + reference series) to matrix X (an array of arrays)
$x = array();

$m = count($datetime_values);    // number of values per serie: rows in the matrix
$n = count($reference_series) + 1; // number of series (reference series + base series): columns in the matrix

//$base_serie_points = z_norm($base_serie_points, $stat[$base_serie_id]['mean'], $stat[$base_serie_id]['stddev']);

for ($i = 0; $i < $m; $i++) {
    $tmp_array = array($base_serie_points[$i][1]);
    for ($j = 0; $j < ($n - 1); $j++) {
        array_push($tmp_array, $all_reference_series_points[$j][$i][1]);
    }
    array_push($x, $tmp_array);
}

$base_serie_index = 0; // the base serie is always pushed to X before the reference series

$start_compute = microtime(true);

$rec_array = cached_CD(trsp($x), $base_serie_index, $threshold, false);
//$rec_array = truncated_CD(trsp($x), $base_serie_index, $threshold, false);

$time_elapsed = (microtime(true) - $start_compute) * 1000;


$x = trsp($rec_array[0]);
$counter = $rec_array[1];

$info_object->iterations = $counter;
$info_object->threshold = $threshold;
$info_object->start_time = $startTime;
$info_object->end_time = $endTime;

// merge values of each serie with datetimes to achieve [x,y] points. required by highcharts...
$retrieved_serie_points = array();
for ($i = 0; $i < $m; $i++) {
    $retrieved_serie_points[] = array(floatval($datetime_values[$i]), floatval($x[$i][$base_serie_index]));
}

// remove the points that are already in the base serie from the retrieved serie (mainly for visibility reasons in Highchart)
$retrieved_serie_points = remove_matches($retrieved_serie_points, $base_serie_points);


$retrieved_serie['points']['raw'] = $retrieved_serie_points;
$retrieved_serie['points']['znorm'] = z_norm($retrieved_serie_points, $stat[$base_serie_id]['mean'], $stat[$base_serie_id]['stddev']);
$retrieved_serie['points']['minmax'] = minmax_norm($retrieved_serie_points, $stat[$base_serie_id]['min'], $stat[$base_serie_id]['max']);

$retrieve_object->retrieved = $retrieved_serie;

$info_object->duration = $time_elapsed;

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
pg_close($conn);


//////////////////////////////////////////////////////////////////////////////
//                                                                          //
//                        LOCAL FUNCTIONS + HELPERS                         //
//                                                                          //
//////////////////////////////////////////////////////////////////////////////


// Function that takes an array of points [datetime, values] (not values!) and applies Linear Interpolation (LI) (for interior points)
// and Nearest Neighbour (NN) (for exterior points) to eliminate 'null points' (points with missing values).
function linear_interpolated_points($points)
{
    $points = array_values($points);
    $j = 0;
    $prev_value = NULL;
    $first_non_null_reached = false;
    for ($i = 0; $i < count($points); ++$i) {
        if (!$first_non_null_reached) {
            if ($points[$i][1] != NULL) {
                for ($q = $i; $q >= 0; $q--) {
                    $points[$q][1] = $points[$i][1];
                }
                $first_non_null_reached = true;
            }
        } else {
            if ($points[$i][1] == NULL) { // this point is a null point => must apply LI or NN
                if ($j == 0) { // previous point was not null
                    $j = $i;

                    while (($points[$j][0] != NULL) && ($points[$j][1] == NULL)) {
                        $j++;
                    }
                    if ($points[$j][0] == NULL) { // this is an exterior point => use NN
                        $points[$i][1] = $prev_value;
                    } else {
                        $next_value = $points[$j][1];
                        if ($prev_value == null) { // this is an exterior point =>use NN
                            $points[$i][1] = $next_value;
                        } else { // this point is an interior point => use LI
                            $increment = ($next_value - $prev_value) / ($j - $i + 1);
                            $points[$i][1] = $prev_value + $increment;
                            $prev_value = $points[$i][1];
                        }
                    }
                } else { // previous point was also a null point => we must be in the middle of LI or NN
                    if ($points[$i + 1][0] != NULL) { // this isn't the last point => continue LI
                        $points[$i][1] = $prev_value + $increment;
                        $prev_value = $points[$i][1];
                    } else { // this is the last point => continue NN
                        $points[$i][1] = $prev_value;
                    }
                }
            } else { // this point isn't a null point => remember value, and continue
                $prev_value = $points[$i][1];
                $j = 0;
            }
        }
    }
    return $points;
}


// Function that returns the 'inverse' of an array of points (not values!). Null points are converted to
// non-null points (with constant value 1), and non-null points are converted to null points.
function inverse_missing($points)
{
    $points_length = count($points);

    // treat first point separately
    if ($points[0][1] == NULL) {
        $points[0][1] = 1;
    } else {
        $points[0][1] = NULL;
    }

    // iterate trough interior points
    for ($i = 1; $i < ($points_length - 1); $i++) {
        if ($points[$i][1] == NULL) {
            $points[$i - 1][1] = 1;
            $points[$i][1] = 1;
        } else {
            $points[$i][1] = NULL;
        }
    }

    // treat last point separately
    if ($points[$points_length][1] == NULL) {
        $points[$points_length - 1][1] = 1;
    }

    return $points;
}

function remove_matches($points_A, $points_B)
{
    $points_length = count($points_A); //must be equal to count($points_B)

    if ($points_B[0][1] != NULL && $points_B[1][1] != NULL) {
        $points_A[0][1] = NULL;
    }
    // iterate trough interior points
    for ($i = 1; $i < $points_length - 1; $i++) {
        if ($points_B[$i][1] != NULL && $points_B[$i - 1][1] != NULL && $points_B[$i + 1][1] != NULL) {
            $points_A[$i][1] = NULL;
        }
    }
    if ($points_B[$points_length][1] != NULL && $points_B[$points_length - 1][1] != NULL) {
        $points_A[$points_length][1] = NULL;
    }

    return $points_A;
}

function count_non_null_values($points)
{
    $points_length = count($points);

    $count = 0;

    for ($i = 0; $i < $points_length; $i++) {
        if ($points[$i][1] != NULL) {
            $count++;
        }
    }
    return $count;
}


function z_norm($points, $mean, $stddev)
{
    $points = array_values($points);
    $points_length = count($points);
    for ($i = 0; $i < $points_length; $i++) {
        if ($points[$i][1] != NULL) {
            $points[$i][1] = ($points[$i][1] - $mean) / $stddev;
        }
    }
    return $points;
}

function minmax_norm($points, $min, $max)
{
    $points_length = count($points);
    for ($i = 0; $i < $points_length; $i++) {
        if ($points[$i][1] != NULL) {
            $points[$i][1] = ($points[$i][1] - $min) / ($max - $min);
        }
    }
    return $points;
}

?>