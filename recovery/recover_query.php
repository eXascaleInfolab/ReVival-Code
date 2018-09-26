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
$start = @$_GET['start'];
if ($start && !preg_match('/^[0-9]+$/', $start)) {
    die("Invalid start parameter: $start");
}
$end = @$_GET['end'];
if ($end && !preg_match('/^[0-9]+$/', $end)) {
    die("Invalid end parameter: $end");
}
$truncation = @$_GET['truncation'];
if ($truncation && !preg_match('/^[0-9]+$/', $truncation)) {
    die("Invalid truncation parameter: $truncation");
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


$base_series_id = monetdb_escape_string($_GET['base_serie']);

$comparison_series_id = monetdb_escape_string($_GET['comparison_serie']);
if ($comparison_series_id == "") {
    $comparison_series_id = null;
}

$threshold = @$_GET['threshold'];

// Instantiate the object that eventually be returned in JSON format
$retrieve_object = new stdClass();

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
            avg($table.value) as mean,
            sys.stddev_samp($table.value) as stddev,
            min($table.value) as min,
            max($table.value) as max
        FROM 
            $table 
        WHERE 
            $table.value IS NOT NULL AND $table.series_id = $base_series_id
        GROUP BY
            $table.series_id
    ";
$result = monetdb_query($conn, $query);

if (!$result) {
    die(monetdb_last_error());
}

while ($row = monetdb_fetch_assoc($result)) {
    $stat[$base_series_id] = array();
    $stat[$base_series_id]['mean'] = $row["mean"];
    $stat[$base_series_id]['stddev'] = $row["stddev"];
    $stat[$base_series_id]['min'] = $row["min"];
    $stat[$base_series_id]['max'] = $row["max"];
}

// Prepare the SQL query for the base series
$query = "
        SELECT
            CONVERT(sys.timestamp_to_str($table.datetime, '%s'), int) * 1000 as datetime,
            $table.value as value,
            series.title
        FROM
            series
        LEFT JOIN
            $table
        ON
            series.id = $table.series_id
        WHERE 
            series.id = $base_series_id AND datetime between '$startTime' and '$endTime'
        ORDER BY
           datetime
    ";

// Extract data from result, store in arrays
$datetime_values = array(); // needed to merge with the retrieved values after CD (Highcharts requires points [x,y], not values...)
$base_series_points = array();

$result = monetdb_query($conn, $query);

if (!$result) {
    die(monetdb_last_error());
}

while ($row = monetdb_fetch_assoc($result)) {
    extract($row);
    $datetime_values[] = floatval($datetime);
    if (is_null($value)) {
        $base_series_points[] = array(floatval($datetime), NULL);
    } else {
        $base_series_points[] = array(floatval($datetime), floatval($value));
    }
}

//$base_series_points = z_norm($base_series_points, $stat[$base_series_id]['mean'], $stat[$base_series_id]['stddev']);

$info_object = new stdClass();

// Create and fill arrays containing various series
$base_series = array();      // Series containing just the existing points in the base serie
$base_series['id'] = $base_series_id;
$base_series['title'] = $title;
$base_series['points'] = array();
$base_series['points']['raw'] = array_values($base_series_points);
$base_series['points']['znorm'] = z_norm($base_series_points, $stat[$base_series_id]['mean'], $stat[$base_series_id]['stddev']);
//$base_series['points']['raw'] = z_norm($base_serie_points, $stat[$base_serie_id]['mean'], $stat[$base_serie_id]['stddev']);
//$base_series['points']['minmax'] = z_norm($base_serie_points, $stat[$base_serie_id]['mean'], $stat[$base_serie_id]['stddev']);
$base_series['points']['minmax'] = minmax_norm($base_series_points, $stat[$base_series_id]['min'], $stat[$base_series_id]['max']);

$info_object->base_serie_title = $title;
$non_null_values = count_non_null_values($base_series_points);
$total_values = count($base_series_points);
$info_object->base_serie_existing = $non_null_values;
$info_object->base_serie_missing = ($total_values - $non_null_values);

$missing_series = array();   // Series to highlight location of missing values in the Highchart range selector
$missing_series['id'] = -1;
$missing_series['title'] = $title;
$missing_series['points'] = array_values(inverse_missing($base_series_points));

$linear_series_points = linear_interpolated_base_series_values_mod($base_series_points);

$linear_series = array();    // Series containing linear interpolation of missing values
$linear_series['id'] = -1;
$linear_series['title'] = $title;
$linear_series['points'] = array();
$linear_series['points']['raw'] = array_values($linear_series_points);
$linear_series['points']['znorm'] = z_norm($linear_series_points, $stat[$base_series_id]['mean'], $stat[$base_series_id]['stddev']);
$linear_series['points']['minmax'] = minmax_norm($linear_series_points, $stat[$base_series_id]['min'], $stat[$base_series_id]['max']);

$retrieved_series = array(); // Series containing centroid decomoposition of missing values
$retrieved_series['id'] = 0;
$retrieved_series['title'] = $title;
$retrieved_series['points'] = array();
// $retrieved_series['points'] => added later, after Centroid Decomposition

// Add all series as arrays to an object that will be returned to requester in JSON format
$retrieve_object->original = $base_series;
$retrieve_object->missing = $missing_series;
$retrieve_object->linear = $linear_series;
// $retrieve_object->retrieved_serie => added later, after Centroid Decomposition

// Since there are multiple reference series, create an array of reference series arrays
$reference_series = array();
$reference_series_ids = array();
$all_reference_series_points = array();

// Depending on the selected mode of reference series selection, the ids are either already available or not
if ($mode == 'manual') { // Reference series ids can be fetched from the URL
    $reference_series_ids = $_GET['reference_serie'];
} elseif ($mode == 'correlated') { // Reference series ids must be queried from the database
    $amount = monetdb_escape_string($_GET['amount']);
    $query = "
            SELECT
                corr.value as value, corr.series_id_2 as reference_series_id
            FROM
                corr
            WHERE 
                corr.series_id_1 = $base_series_id
            ORDER BY
               value DESC
            LIMIT $amount
        ";


    $result = monetdb_query($conn, $query);

    if (!$result) {
        die(monetdb_last_error());
    }

    while ($row = monetdb_fetch_assoc($result)) {
        extract($row);
        array_push($reference_series_ids, $reference_series_id);
    }
}


// Query the data of each reference serie from the database
foreach ($reference_series_ids as $reference_series_id) {
    //if normalization is required, get relevant values from the database

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
                CONVERT(sys.timestamp_to_str($table.datetime, '%s'), int) * 1000 as datetime,
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

    // Eliminate missing/null values in the reference series by applying linear interpolation
    $reference_series_points = linear_interpolated_base_series_values_mod($reference_series_points);

    array_push($all_reference_series_points, $reference_series_points);
    //array_push($all_reference_series_points, z_norm($reference_series_points, $stat[$reference_series_id]['mean'], $stat[$reference_series_id]['stddev']));

    $reference_series[$reference_series_id]['id'] = $reference_series_id;
    $reference_series[$reference_series_id]['title'] = $title;
    $reference_series[$reference_series_id]['points'] = array();
    $reference_series[$reference_series_id]['points']['raw'] = array_values($reference_series_points);
    $reference_series[$reference_series_id]['points']['znorm'] =
        z_norm($reference_series_points, $stat[$reference_series_id]['mean'], $stat[$reference_series_id]['stddev']);
    //$reference_series[$reference_series_id]['points']['raw'] =
    //    z_norm($reference_series_points, $stat[$reference_series_id]['mean'], $stat[$reference_series_id]['stddev']);
    //$reference_series[$reference_series_id]['points']['minmax'] =
    //    z_norm($reference_series_points, $stat[$reference_series_id]['mean'], $stat[$reference_series_id]['stddev']);
    $reference_series[$reference_series_id]['points']['minmax'] =
        minmax_norm($reference_series_points, $stat[$reference_series_id]['min'], $stat[$reference_series_id]['max']);
}

// Add all reference series to the object
$retrieve_object->reference_series = array_values($reference_series);

if (isset($comparison_series_id)) {

    $query = "
            SELECT
                avg($table.value) as mean,
                sys.stddev_samp($table.value) as stddev,
                min($table.value) as min,
                max($table.value) as max
            FROM 
                $table 
            WHERE 
                $table.value IS NOT NULL AND $table.series_id = $comparison_series_id
            GROUP BY
                $table.series_id
        ";
    $result = monetdb_query($conn, $query);

    if (!$result) {
        die(monetdb_last_error());
    }

    while ($row = monetdb_fetch_assoc($result)) {
        $stat[$comparison_series_id] = array();
        $stat[$comparison_series_id]['mean'] = $row["mean"];
        $stat[$comparison_series_id]['stddev'] = $row["stddev"];
        $stat[$comparison_series_id]['min'] = $row["min"];
        $stat[$comparison_series_id]['max'] = $row["max"];
    }

    // Prepare the SQL query for the comparison series
    $query = "
            SELECT
                CONVERT(sys.timestamp_to_str($table.datetime, '%s'), int) * 1000 as datetime,
                $table.value as value,
                series.title
            FROM
                series
            LEFT JOIN
                $table
            ON
                series.id = $table.series_id
            WHERE 
                series.id = $comparison_series_id AND datetime between '$startTime' and '$endTime'
            ORDER BY
               datetime
        ";

    // Extract data from result, store in arrays
    $datetime_values = array(); // needed to merge with the retrieved values after CD (Highcharts requires points [x,y], not values...)
    $comparison_series_points = array();

    $result = monetdb_query($conn, $query);

    if (!$result) {
        die(monetdb_last_error());
    }

    while ($row = monetdb_fetch_assoc($result)) {
        extract($row);
        $datetime_values[] = floatval($datetime);
        if (is_null($value)) {
            $comparison_series_points[] = array(floatval($datetime), NULL);
        } else {
            $comparison_series_points[] = array(floatval($datetime), floatval($value));
        }
    }

    // Create and fill arrays containing various series
    $comparison_series = array();      // Series containing just the existing points in the base series
    $comparison_series['id'] = $comparison_series_id;
    $comparison_series['title'] = $title;
    $comparison_series['points'] = array();
    $comparison_series['points']['raw'] = array_values($comparison_series_points);
    $comparison_series['points']['znorm'] =
        z_norm($comparison_series_points, $stat[$comparison_series_id]['mean'], $stat[$comparison_series_id]['stddev']);
    $comparison_series['points']['minmax'] =
        minmax_norm($comparison_series_points, $stat[$comparison_series_id]['min'], $stat[$comparison_series_id]['max']);
    $retrieve_object->comparison = $comparison_series;
} else {
    $retrieve_object->comparison = null;
}


//////////////////////////////////////////////////////////////////////////////
//                                                                          //
//                 CENTROID DECOMPOSITION + POST-PROCESSING                 //
//                                                                          //
//////////////////////////////////////////////////////////////////////////////


// Push all series (base series + reference series) to matrix X (an array of arrays)
$x = array();

$m = count($datetime_values);    // number of values per series: rows in the matrix
$n = count($reference_series) + 1; // number of series (reference series + base series): columns in the matrix

//$base_series_points = z_norm($base_series_points, $stat[$base_series_id]['mean'], $stat[$base_series_id]['stddev']);

for ($i = 0; $i < $m; $i++) {
    $tmp_array = array($base_series_points[$i][1]);
    for ($j = 0; $j < ($n - 1); $j++) {
        array_push($tmp_array, $all_reference_series_points[$j][$i][1]);
    }
    array_push($x, $tmp_array);
}

$base_series_index = 0; // the base series is always pushed to X before the reference series

//todo: DEFAULT OR REMOVE
$mean = NULL;
$stddev = NULL;

$mean = array($stat[$base_series_id]['mean']);
$stddev = array($stat[$base_series_id]['stddev']);

foreach ($reference_series_ids as $reference_series_id) {
    $mean[] = $stat[$reference_series_id]['mean'];
    $stddev[] = $stat[$reference_series_id]['stddev'];
}

$start_compute = microtime(true);

$rec_array = RMV($x, $base_series_index, $threshold, $truncation, true, $mean, $stddev);
//$rec_array = cached_CD(trsp($x), $base_series_index, $threshold);

$time_elapsed = (microtime(true) - $start_compute) * 1000;


//$x = trsp($rec_array[0]);
$x = $rec_array[0];
$counter = $rec_array[1];

$info_object->iterations = $counter;
$info_object->threshold = $threshold;
$info_object->start_time = $startTime;
$info_object->end_time = $endTime;

// merge values of each series with datetimes to achieve [x,y] points. required by highcharts...
$retrieved_series_points = array();
for ($i = 0; $i < $m; $i++) {
    $retrieved_series_points[] = array(floatval($datetime_values[$i]), floatval($x[$i][$base_series_index]));
}

// remove the points that are already in the base series from the retrieved series (mainly for visibility reasons in Highchart)
$retrieved_series_points = remove_matches($retrieved_series_points, $base_series_points);


$retrieved_series['points']['raw'] = $retrieved_series_points;
$retrieved_series['points']['znorm'] = z_norm($retrieved_series_points, $stat[$base_series_id]['mean'], $stat[$base_series_id]['stddev']);
$retrieved_series['points']['minmax'] = minmax_norm($retrieved_series_points, $stat[$base_series_id]['min'], $stat[$base_series_id]['max']);

$retrieve_object->retrieved = $retrieved_series;

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
monetdb_disconnect();


//////////////////////////////////////////////////////////////////////////////
//                                                                          //
//                        LOCAL FUNCTIONS + HELPERS                         //
//                                                                          //
//////////////////////////////////////////////////////////////////////////////


// Function that takes an array of points [datetime, values] (not values!) and applies Linear Interpolation (LI) (for interior points)
// and Nearest Neighbour (NN) (for exterior points) to eliminate 'null points' (points with missing values).
function linear_interpolated_points($points)
{
    $increment = 0;
    $points = array_values($points);
    $j = 0;
    $prev_value = NULL;
    $first_non_null_reached = false;
    for ($i = 0; $i < count($points); ++$i) {
        if (!$first_non_null_reached) {
            if ($points[$i][1] !== NULL) {
                for ($q = $i; $q >= 0; $q--) {
                    $points[$q][1] = $points[$i][1];
                }
                $first_non_null_reached = true;
            }
        } else {
            if ($points[$i][1] == NULL) { // this point is a null point => must apply LI or NN
                if ($j == 0) { // previous point was not null
                    $j = $i;

                    while ($j < count($points) && ($points[$j][0] !== NULL) && ($points[$j][1] == NULL)) {
                        $j++;
                    }
                    $j--;

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
                    if ($points[$i + 1][0] !== NULL) { // this isn't the last point => continue LI
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

function linear_interpolated_base_series_values_mod($matrix, $base_series_index = 1)
{
    $rows = count($matrix);
    $mb_start = -1;
    $prev_value = NULL;
    $step = 0;//init

    for ($i = 0; $i < $rows; $i++) {
        if (is_null($matrix[$i][$base_series_index])) {
            // current value is missing - we either start a new block, or we are in the middle of one

            if ($mb_start == -1) { // new missing block
                $mb_start = $i;
                $mb_end = $mb_start + 1;

                //lookahead to find the end
                // INDEX IS NEXT NON-NULL ELEMENT, NOT THE LAST NULL
                // INCLUDING OUT OF BOUNDS IF THE BLOCK ENDS AT THE END OF TS
                while (($mb_end < $rows) && is_null($matrix[$mb_end][$base_series_index])) {
                    $mb_end++;
                }

                $next_value = $mb_end == $rows ? NULL : $matrix[$mb_end][$base_series_index];

                if ($mb_start == 0) { // special case #1: block starts with array
                    $prev_value = $next_value;
                }
                if ($mb_end == $rows) { // special case #2: block ends with array
                    $next_value = $prev_value;
                }
                $step = ($next_value - $prev_value) / ($mb_end - $mb_start + 1);
            }
            $matrix[$i][$base_series_index] = $prev_value + $step * ($i - $mb_start + 1);
        } else {
            // missing block either ended just new or we're traversing normal data
            $prev_value = $matrix[$i][$base_series_index];
            $mb_start = -1;
        }
    }

    return $matrix;
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
    if ($points[$points_length - 1][1] == NULL) {
        $points[$points_length - 1][1] = 1;
    }

    return $points;
}

function remove_matches($points_A, $points_B)
{
    $points_length = count($points_A); //must be equal to count($points_B)

    if ($points_B[0][1] !== NULL && $points_B[1][1] !== NULL) {
        $points_A[0][1] = NULL;
    }
    // iterate trough interior points
    for ($i = 1; $i < $points_length - 1; $i++) {
        if ($points_B[$i][1] !== NULL && $points_B[$i - 1][1] !== NULL && $points_B[$i + 1][1] !== NULL) {
            $points_A[$i][1] = NULL;
        }
    }
    if ($points_B[$points_length - 1][1] !== NULL && $points_B[$points_length - 2][1] !== NULL) {
        $points_A[$points_length - 1][1] = NULL;
    }

    return $points_A;
}

function count_non_null_values($points)
{
    $points_length = count($points);

    $count = 0;

    for ($i = 0; $i < $points_length; $i++) {
        if ($points[$i][1] !== NULL) {
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
