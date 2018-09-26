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
$start = 848102400 * 1000;
$end = 956448000 * 1000;

$startTime = gmstrftime('%Y-%m-%d %H:%M:%S', $start / 1000);
$endTime = gmstrftime('%Y-%m-%d %H:%M:%S', $end / 1000);

// Instantiate the object that eventually be returned in JSON format
$retrieve_object = new stdClass();

// Prepare the database connection
include '../connect.php';
$table = 'daily'; //final

$reference_series_ids = array(2181, 2303, 2202);

//////////////////////////////////////////////////////////////////////////////
//                                                                          //
//                  RETRIEVAL + PRE-PROCESSING OF DATA FROM DB              //
//                                                                          //
//////////////////////////////////////////////////////////////////////////////

// get relevant values for normalization from the database

$stat = array();
$counter = 1;

// Query the data of each reference series from the database
foreach ($reference_series_ids as $reference_series_id) {
    //if normalization is required, get relevant values from the database

    $query = "
            SELECT
                avg($table.value) as mean,
                sys.stddev_samp($table.value) as stddev
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
            $reference_series_points[] = array(floatval($datetime), floatval($value) - 0.15);
        }
    }

    // Eliminate missing/null values in the reference series by applying linear interpolation
    $reference_series_points = linear_interpolated_points($reference_series_points);
    $all_reference_series_points = array();
    array_push($all_reference_series_points, $reference_series_points);

    $reference_series[$reference_series_id]['id'] = $reference_series_id;
    $reference_series[$reference_series_id]['title'] = 'Time series ' . $counter++;
    $reference_series[$reference_series_id]['points'] =
        z_norm($reference_series_points, $stat[$reference_series_id]['mean'], $stat[$reference_series_id]['stddev']);
}

// Add all reference series to the object
$retrieve_object->reference_series = array_values($reference_series);


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
    $points = array_values($points);
    $j = 0;
    $prev_value = NULL;
    $increment=0;
    for ($i = 0; $i < count($points); ++$i) {
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
    return $points;
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

?>
