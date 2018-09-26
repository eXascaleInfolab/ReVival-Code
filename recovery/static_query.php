<?php

// Get required parameters from the URL
$callback = $_GET['callback'];
if (!preg_match('/^[a-zA-Z0-9_]+$/', $callback)) {
    die('Invalid callback name');
}

include '../algebra.php';

$threshold = 0.01;

$cd_object = new stdClass();

$x = array();

$x[] = array(-12, 0, -48, null, null, null, null, null, null, null, 12, null, null, -12, 24, null, null, null, 48, 12);
$x[] = array(8, 0, 32, 64, 24, 64, 16, 8, -32, 8, -8, -24, 16, 8, -16, -8, -12, -24, -32, -8);
$x[] = array(-4, 0, -16, -32, -12, -32, -8, -4, 16, -4, 4, 12, -8, -4, 8, 4, 6, 12, 16, 4);
$x[] = array(-8, 0, -32, -64, -24, -64, -16, -8, 32, -8, 8, 24, -16, -8, 16, 8, 12, 24, 32, 8);


$original = array(-12, 0, -48, -96, -36, -96, -24, -12, 48, -12, 12, 36, -24, -12, 24, 12, 18, 36, 48, 12);

$z_sequence = array();

$base_series_index = 0;

$iteration_object = new stdClass();
$iteration_object->z = null;
$iteration_object->id = null;
$iteration_object->local_ztv = null;
$iteration_object->x = $x;
$iteration_object->original = $original;
$iteration_object->ztv = null;
$iteration_object->diff = null;
$iteration_object->log = null;

$iterations[] = $iteration_object;

$m = count($x[0]); // number of rows
$n = count($x); // number of columns
$k = $n - 1; // "truncate" factor

// write out all indexes of missing values in the base series
$missing_value_indexes = array();
for ($i = 0; $i < $m; $i++) {
    if (is_null($x[$base_series_index][$i])) {
        $missing_value_indexes[] = $i;
    }
}

// initialize missing values with linear interpolation (nearest neighbour for edge values)
$x = linear_interpolated_base_series_values_old($x, $base_series_index);

$iteration_object = new stdClass();
$iteration_object->z = null;
$iteration_object->id = null;
$iteration_object->jd = null;
$iteration_object->local_ztv = null;
$iteration_object->x = $x;
$iteration_object->original = $original;
$iteration_object->ztv = null;
$iteration_object->diff = null;
$iteration_object->log = "Linearly interpolated missing values in BASE series.";

$iterations[] = $iteration_object;

// Create and initialize all used matrices (as arrays of arrays) to save space hopefully

$x_star = init_array($n, $m); //(double[m][n])
$l = init_array($m, $n); //(double[m][n])
$r = init_array($n, $n); //(double[n][n])
//$z = init_array($m,1); //(double[m][1])
$z_values = array();

$c_column = init_vector($m); //(double[m][1])
$s = init_vector($n); //(double[n][1])
$v = init_vector($m); //(double[m][1])
$l_truncated = init_array($m, $n); //(double[m][n])
$x_truncated = init_array($n, $m); //(double[m][n])


for ($j = 0; $j < $n; $j++) {
    $z_values[] = null;
}

$jd = 0;

do {

    $ztv_max = 0;

    //Update X_star, set equal to X
    for ($i = 0; $i < $m; $i++) {
        for ($j = 0; $j < $n; $j++) {
            $x_star[$j][$i] = $x[$j][$i];
        }
    }

    //(Re)set X_truncated to zero
    for ($i = 0; $i < $m; $i++) {
        for ($j = 0; $j < $n; $j++) {
            $x_truncated[$j][$i] = 0;
        }
    }

    for ($j = 0; $j < $n; $j++) {
        $id = $j;
        //$z = $z_values[$j];

        $pos = 0;
        do {
            // change sign
            if ($pos == 0) {
                $z = $z_values[$j];
                if ($z == null) {
                    for ($i = 0; $i < $m; $i++) {
                        $z[] = 1;
                    }
                }
            } else {
                $z[$pos - 1] = $z[$pos - 1] * (-1);
            }


            // determine s and v
            for ($y = 0; $y < $n; $y++) {
                $s[$y] = 0;
            }

            for ($i = 0; $i < $m; $i++) {
                for ($y = 0; $y < $n; $y++) {
                    $s[$y] += $z[$i] * $x[$y][$i];
                }
            }

            for ($i = 0; $i < $m; $i++) {
                $tmp1 = 0;
                $tmp2 = 0;
                for ($y = 0; $y < $n; $y++) {
                    $tmp1 += $z[$i] * ($x[$y][$i] * $s[$y]);
                    $tmp2 += $x[$y][$i] * $x[$y][$i];
                }
                $v[$i] = $z[$i] * ($tmp1 - $tmp2);
            }

            $local_ztv = 0;
            for ($y = 0; $y < $m; $y++) {
                $local_ztv += $z[$y] * $v[$y];
            }

            $iteration_object = new stdClass();
            $iteration_object->z = $z;
            $iteration_object->id = $id;
            $iteration_object->jd = $jd;
            $iteration_object->local_ztv = $local_ztv;
            $iteration_object->x = null;
            $iteration_object->ztv = null;
            $iteration_object->diff = null;
            if ($pos == 0) {
                $iteration_object->log = "Z^(" . ($j + 1) . "): read from cache";
            } else {
                $iteration_object->log = "Z^(" . ($j + 1) . "): sign at position " . $pos . " changed to " . $z[$pos - 1];
            }

            $iterations[] = $iteration_object;

            // search next element
            $val = 0;
            $pos = 0;
            for ($i = 0; $i < $m; $i++) {
                if ($z[$i] * $v[$i] < 0) {
                    if (abs($v[$i]) > $val) {
                        $val = abs($v[$i]);
                        $pos = $i + 1;
                    }
                }
            }

        } while ($pos != 0);

        for ($i = 0; $i < $m; $i++) {
            $z_values[$j] = $z;
        }

        $ztv = 0;
        for ($y = 0; $y < $m; $y++) {
            $ztv += $z[$y] * $v[$y];
        }

        if ($ztv_max < $ztv) {
            $ztv_max = $ztv;
        }

        $iteration_object = new stdClass();
        $iteration_object->z = $z;
        $iteration_object->id = $id;
        $iteration_object->jd = $jd;
        $iteration_object->local_ztv = null;
        $iteration_object->x = null;
        $iteration_object->ztv = $ztv;
        $iteration_object->diff = null;
        $iteration_object->log = "Z^(" . ($j + 1) . "): maximum sign vector computed";

        array_pop($iterations); // remove last element in array
        $iterations[] = $iteration_object; // and replace with 'more complete' element

        $sum_squared = 0;
        for ($y = 0; $y < $n; $y++) {
            $tmp = 0;
            for ($i = 0; $i < $m; $i++) {
                $tmp += $x[$y][$i] * $z[$i];
            }
            $c_column[$y] = $tmp;
            $sum_squared += pow($tmp, 2);
        }

        for ($y = 0; $y < $n; $y++) {
            $r[$y][$j] = $c_column[$y] / sqrt($sum_squared);
        }

        for ($i = 0; $i < $m; $i++) {
            $l[$i][$j] = 0;
            for ($y = 0; $y < $n; $y++) {
                $l[$i][$j] += $x[$y][$i] * $r[$y][$j];
            }
        }

        for ($i = 0; $i < $m; $i++) {
            for ($y = 0; $y < $n; $y++) {
                $x[$y][$i] -= ($l[$i][$j] * $r[$y][$j]);
            }
        }

    }

    for ($i = 0; $i < $m; $i++) {
        for ($j = 0; $j < ($n - $k); $j++) {
            $l_truncated[$i][$j] = $l[$i][$j];
        }
        for ($j = ($n - $k); $j < $n; $j++) {
            $l_truncated[$i][$j] = 0;
        }
    }

    for ($i = 0; $i < $m; $i++) {
        for ($y = 0; $y < $n; $y++) {
            $x_truncated[$y][$i] = 0;
        }
    }

    for ($i = 0; $i < $m; $i++) {
        for ($j = 0; $j < $n; $j++) {
            for ($y = 0; $y < $n; $y++) {
                $x_truncated[$j][$i] += $l_truncated[$i][$y] * $r[$j][$y]; // R trans!
            }
        }
    }

    // because X was altered during iteration, set back to initial values (before this iteration)
    for ($i = 0; $i < $m; $i++) {
        for ($y = 0; $y < $n; $y++) {
            $x[$y][$i] = $x_star[$y][$i];
        }
    }

    // update the "missing values" in X with those of x_truncated
    for ($y = 0; $y < count($missing_value_indexes); $y++) {
        $x[$base_series_index][$missing_value_indexes[$y]] = $x_truncated[$base_series_index][$missing_value_indexes[$y]];
    }


    // Calculate Frobenius difference between X before (stored in X_star) and after (stored in X) the current iteration
    $diff = rootmeansquare_distance($x, $x_star, count($missing_value_indexes));

    $iteration_object = new stdClass();
    $iteration_object->z = $z;
    $iteration_object->id = $id;
    $iteration_object->jd = $jd;
    $iteration_object->local_ztv = null;
    $iteration_object->original = $original;
    $iteration_object->x = $x;
    $iteration_object->ztv = $ztv;
    $iteration_object->diff = $diff;
    $iteration_object->log = "Cached Centroid Decomposition started";

    array_pop($iterations); // remove last element in array
    $iterations[] = $iteration_object; // and replace with 'more complete' element

    $jd++;
} while ($diff > $threshold);

$cd_object->iterations = $iterations;

// Return data (the cd object) in a file in JSON notation
header('Content-Type: text/javascript');
echo $callback . "([\n" . json_encode($cd_object) . "\n]);";

// Function analog to linear_interpolated_points, except for values and only applied in one column (the base series),
// which is indicated by the base_series_index. See other function for comments (is in retrieve_query.php).
function linear_interpolated_base_series_values_old($values, $base_series_index)
{
    $rows = count($values[0]);
    $j = 0;
    $prev_value = NULL;
    $increment = 0;
    for ($i = 0; $i < $rows; $i++) {
        if (is_null($values[$base_series_index][$i])) {
            if ($j == 0) {
                $j = $i;
                while (($values[$base_series_index][$j] == NULL) && ($j < $rows)) {
                    $j++;
                }
                if ($j == $rows) {
                    $values[$base_series_index][$i] = $prev_value;
                } else {
                    $next_value = $values[$base_series_index][$j];
                    if ($prev_value == NULL) {
                        $values[$base_series_index][$i] = $next_value;
                    } else {
                        $increment = ($next_value - $prev_value) / ($j - $i + 1);
                        $values[$base_series_index][$i] = $prev_value + $increment;
                        $prev_value = $values[$base_series_index][$i];
                    }
                }
            } else {
                if ($i != $rows - 1) {
                    $values[$base_series_index][$i] = $prev_value + $increment;
                    $prev_value = $values[$base_series_index][$i];
                } else {
                    $values[$base_series_index][$i] = $prev_value;
                }
            }
        } else {
            $prev_value = $values[$base_series_index][$i];
            $j = 0;
        }
    }
    return $values;
}
