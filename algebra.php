<?php
/**
 * Created by PhpStorm.
 * User: zakhar
 * Date: 04/09/18
 * Time: 10:57
 */

include_once 'connect.php';

//////////////////////////////////////////////////////////////////////////////
//                                                                          //
//                        Algebra core functions                            //
//                                                                          //
//////////////////////////////////////////////////////////////////////////////

/** Performs the recovery of missing values on input X, missing values are only present in base series.
 *  Recovery is performed until 100 iterations or a threshold of mean square difference is reached.
 *  Uses truncated CD with a truncation factor of k.
 * @param $x : array of arrays | matrix to be recovered
 * @param $base_series_index : Int | index of base series
 * @param $threshold : Double | limit of meansquarediff between iteration
 * @param $k : Int | truncation factor for CD
 * @return array : array | where [0] = X with recovered values; [1] = Int, number of iterations recovery took
 */
function RMV($x, $base_series_index, $threshold, $k, $normalize = false, $mean = NULL, $stddev = NULL)
{
    $n = count($x); // number of rows
    $m = count($x[0]); // number of columns

    if ($k >= $m) $k = 0;

    if ($k == 0)
    {
        if ($m == 2 || $m == 3)
        {
            $k = 1;
        }
        else if ($m == 4 || $m == 5)
        {
            $k = 2;
        }
        else
        {
            $k = 3;
        }
    }

    // write out all indexes of missing values in the base series
    $missing_value_indices = array();
    for ($i = 0; $i < $n; $i++)
    {
        if (is_null($x[$i][$base_series_index]))
        {
            $missing_value_indices[] = $i;
        }
    }

    if (count($missing_value_indices) == 0)
    {
        return array($x, 0);
    }

    // initialize missing values with linear interpolation (nearest neighbour for edge values)
    linear_interpolated_base_series_values($x, $base_series_index);

    $l = init_array($n, $k, 0);
    $r = init_array($m, $k, 0);

    $z_all = array();
    for ($j = 0; $j < $k; $j++)
    {
        $z = array();
        for ($i = 0; $i < $n; $i++)
        {
            $z[] = 1.0;
        }
        $z_all[] = $z;
    }

    // normalize
    if ($normalize)
    {
        // normalization
        if (is_null($mean) || is_null($stddev))
        {
            $mean = init_vector($m, 0);
            $stddev = init_vector($m, 0);
            //print_array_csv($mean);
            //print_array_csv($stddev);
            getmeanstddev($x, $mean, $stddev, $n, $m);
        }

        for ($i = 0; $i < $n; ++$i)
        {
            for ($j = 0; $j < $m; ++$j)
            {
                $x[$i][$j] = ($x[$i][$j] - $mean[$j]) / $stddev[$j];
            }
        }
    }

    $diff = 99.0;
    $iters = 0;

    while ($diff >= $threshold && $iters < 100)
    {
        cachedTCD($x, $z_all, $l, $r, $k);

        $x_reconstruction = matmult_A_BT($l, $r);

        // update the "missing values" in X with those of x_truncated & calculate meansquarediff
        $diff = 0.0;

        for ($mv = 0; $mv < count($missing_value_indices); $mv++)
        {
            $idx = $missing_value_indices[$mv];
            $oldval = $x[$idx][$base_series_index];
            $newval = $x_reconstruction[$idx][$base_series_index];

            $val = $oldval - $newval;
            $diff += $val * $val;
            $x[$idx][$base_series_index] = $newval;
        }

        $diff = sqrt($diff / count($missing_value_indices));
        $iters++;
    }

    // denormalize back
    if ($normalize)
    {
        for ($i = 0; $i < $n; ++$i)
        {
            for ($j = 0; $j < $m; ++$j)
            {
                $x[$i][$j] = ($x[$i][$j] * $stddev[$j]) + $mean[$j];
            }
        }
    }

    return array($x, $iters);
}


/** Performs the recovery of all missing values in all time series stored in the session object.
 * Takes stats information to do normalization during recovery.
 * Returns recovery and its technical data (#iters, recovery runtime).
 * @param $conn : Object | monetdb connection object
 * @param $sessionobject : Object | object containing all time series with their metadata
 * @param $threshold : Double | limit of meansquarediff between iteration
 * @param $normtype : int | 0 to indicate the data is not normalized
 * @param $table : string | table where the data was taken from
 * @param $visible : array | list of ids with their indication whether they are to be used as reference in recovery
 * @return object : object | same structure as session object, but adapted to visualise it in the chart
 */
function recover_all($conn, $sessionobject, $threshold, $normtype, $table, $visible)
{
    $x = array();

    $m = count($sessionobject->{"series"}[0]["points"]);    // number of values per series: rows in the matrix
    $n = count($sessionobject->{"series"}); // number of series

    $visibility_idx = array();

    for ($j = 0; $j < $n; $j++)
    {
        $sid = $sessionobject->{"series"}[$j]["id"];

        for ($s = 0; $s < count($visible); $s++)
        {
            if ($visible[$s]["id"] == $sid)
            {
                $visibility_idx[] = $visible[$s]["visible"];
                break;
            }
        }

        if (!isset($visibility_idx[$j]))
        {
            $visibility_idx[] = false;
        }

        $col = array();

        for ($i = 0; $i < $m; $i++)
        {
            $col[] = $visibility_idx[$j] ? $sessionobject->{"series"}[$j]["points"][$i][1] : 0.0;
        }

        $x[] = $col;
    }


    if ($normtype == 0 && !is_null($conn))
    {
        $mean = array();
        $stddev = array();

        for ($j = 0; $j < $n; $j++)
        {
            if (!$visibility_idx[$j])
            {
                $mean[] = 0.0;
                $stddev[] = 1.0;
                continue;
            }

            $stat = get_statistics($conn, $table, $sessionobject->{"series"}[$j]["id"]);
            $mean[] = $stat->{"mean"};
            $stddev[] = $stat->{"stddev"};
        }

        $start_compute = microtime(true);
        $x = RMV_all(trsp($x), $threshold, $n, true, $mean, $stddev);
    }
    else
    {
        $start_compute = microtime(true);
        $x = RMV_all(trsp($x), $threshold, $n, false);
    }
    $time_elapsed = (microtime(true) - $start_compute) * 1000;
    $x = $x[0];

    $RMSE = 0.0;
    $RMSE_norm = 0.0;
    $MAE = 0.0;
    $MAE_norm = 0.0;
    $counter = 0;
    for ($j = 0; $j < $n; $j++)
    {
        if (!$visibility_idx[$j]) continue;

        for ($i = 0; $i < $m; $i++)
        {
            if (is_null($sessionobject->{"series"}[$j]["points"][$i][1]))
            {
                $gr = $sessionobject->{"series"}[$j]["ground"][$i][1];

                if (!isset($gr) || is_null($gr))
                {
                    continue;
                }

                $delta = $x[$i][$j] - $gr;

                $RMSE += $delta * $delta;
                $MAE += abs($delta);

                if ($normtype == 0 && !is_null($conn))
                {
                    $RMSE_norm += ($delta * $delta) / ($stddev[$j] * $stddev[$j]);
                    $MAE_norm += abs($delta) / $stddev[$j];
                }

                $counter++;
            }
        }
    }

    $recov_response = new stdClass();
    $recov_response->{"series"} = array();

    for ($j = 0; $j < $n; $j++)
    {
        if (!isset($sessionobject->{"series"}[$j]["ground"]))
        {
            //continue;
        }

        $newseries = array();
        $newseries["id"] = $sessionobject->{"series"}[$j]["id"];
        $newseries["title"] = $sessionobject->{"series"}[$j]["title"] . " (recovery)";


        $oldseries = $sessionobject->{"series"}[$j]["points"];
        $newpoints = array();

        for ($i = 0; $i < $m; $i++)
        {
            $newpoints[] = $oldseries[$i];
        }

        for ($i = 0; $i < $m; $i++)
        {
            if (is_null($oldseries[$i][1]))
            {
                $newpoints[$i][1] = $x[$i][$j];
            }
            else if ( ( ($i > 0) && is_null($oldseries[$i - 1][1]) )
                          ||
                      ( ($i < $m - 1) && is_null($oldseries[$i + 1][1]) )
            ) // check if next OR previous element is a null originally. additionally prevent out of bounds
            { }
            else
            {
                $newpoints[$i][1] = null;
            }
        }

        $newseries["recovered"] = $newpoints;
        $recov_response->{"series"}[] = $newseries;
    }

    $recov_response->{"runtime"} = $time_elapsed;
    if ($normtype == 0 && !is_null($conn))
    {
        $recov_response->{"rmse"} = sqrt($RMSE / $counter);
        $recov_response->{"mae"} = $MAE / $counter;

        $recov_response->{"rmse_norm"} = sqrt($RMSE_norm / $counter);
        $recov_response->{"mae_norm"} = $MAE_norm / $counter;
    }
    else
    {
        $recov_response->{"rmse_norm"} = sqrt($RMSE / $counter);
        $recov_response->{"mae_norm"} = $MAE / $counter;
    }

    return $recov_response;
}

/** Performs the recovery of missing values on input X, missing values can be present in all series.
 *  Recovery is performed until threshold of mean square different is reached or 100 iterations.
 *  Uses truncated CD with a truncation factor of k.
 * @param $x : array of arrays | matrix to be recovered
 * @param $threshold : Double | limit of meansquarediff between iteration
 * @param $k : Int | truncation factor for CD
 * @return array : array | where [0] = X with recovered values; [1] = Int, number of iterations recovery took
 */
function RMV_all($x, $threshold, $k, $normalize = false, $mean = NULL, $stddev = NULL)
{
    $n = count($x); // number of rows
    $m = count($x[0]); // number of columns

    $kinda_m = 0;
    for ($j = 0; $j < $m; $j++)
    {
        $any = false;
        for ($i = 0; $i < $n; $i++)
        {
            if (is_null($x[$i][$j]) || $x[$i][$j] === 0.0)
            { }
            else
            {
                $any = true;
                break;
            }
        }
        if ($any) $kinda_m++;
    }

    if ($k >= $m) $k = 0;

    if ($k == 0)
    {
        if ($kinda_m == 2 || $kinda_m == 3)
        {
            $k = 1;
        }
        else if ($kinda_m == 4 || $kinda_m == 5)
        {
            $k = 2;
        }
        else
        {
            $k = 3;
        }
    }

    // write out all indexes of missing values in the base series
    $missing_value_indices = array();
    for ($i = 0; $i < $n; $i++)
    {
        for ($j = 0; $j < $m; $j++)
        {
            if (is_null($x[$i][$j]))
            {
                $missing_value_indices[] = array($i, $j);
            }
        }
    }

    if (count($missing_value_indices) == 0)
    {
        return array($x, 0);
    }

    // initialize missing values with linear interpolation (nearest neighbour for edge values)
    for ($j = 0; $j < $m; $j++)
    {
        linear_interpolated_base_series_values($x, $j);
    }

    $l = init_array($n, $k, 0);
    $r = init_array($m, $k, 0);

    $z_all = array();
    for ($j = 0; $j < $k; $j++)
    {
        $z = array();
        for ($i = 0; $i < $n; $i++)
        {
            $z[] = 1.0;
        }
        $z_all[] = $z;
    }

    // normalize
    if ($normalize)
    {
        // normalization
        if (is_null($mean) || is_null($stddev))
        {
            $mean = init_vector($m, 0);
            $stddev = init_vector($m, 0);
            //print_array_csv($mean);
            //print_array_csv($stddev);
            getmeanstddev($x, $mean, $stddev, $n, $m);
        }

        for ($i = 0; $i < $n; ++$i)
        {
            for ($j = 0; $j < $m; ++$j)
            {
                $x[$i][$j] = ($x[$i][$j] - $mean[$j]) / $stddev[$j];
            }
        }
    }

    $diff = 99.0;
    $iters = 0;

    while ($diff >= $threshold && $iters < 100)
    {
        cachedTCD($x, $z_all, $l, $r, $k);

        $x_reconstruction = matmult_A_BT($l, $r);

        // update the "missing values" in X with those of x_truncated & calculate meansquarediff
        $diff = 0.0;

        for ($mv = 0; $mv < count($missing_value_indices); $mv++)
        {
            $base_series_index = $missing_value_indices[$mv][1];
            $idx = $missing_value_indices[$mv][0];

            $oldval = $x[$idx][$base_series_index];
            $newval = $x_reconstruction[$idx][$base_series_index];

            $val = $oldval - $newval;
            $diff += $val * $val;
            $x[$idx][$base_series_index] = $newval;
        }

        $diff = sqrt($diff / count($missing_value_indices));
        $iters++;
    }

    // denormalize back
    if ($normalize)
    {
        for ($i = 0; $i < $n; ++$i)
        {
            for ($j = 0; $j < $m; ++$j)
            {
                $x[$i][$j] = ($x[$i][$j] * $stddev[$j]) + $mean[$j];
            }
        }
    }

    return array($x, $iters);
}


/** Performs Batch Centroid decomposition of a matrix x.
 * @param $x : array of arrays | a matrix to be decomposed
 * @return array : array | where [0] = L; [1] = R matrices s.t. L*R^T=X; [2] = Z[] sign vectors matrix
 */
function CD($x)
{
    $n = count($x);
    $m = count($x[0]);

    $z_all = array();

    for ($j = 0; $j < $m; $j++)
    {
        $z = array();

        for ($i = 0; $i < $n; $i++)
        {
            $z[] = 1.0;
        }

        $z_all[] = $z;
    }

    $l = init_array($n, $m);
    $r = init_array($m, $m);

    cachedTCD($x, $z_all, $l, $r, $m);

    return array($l, $r, trsp($z_all));
}


/** Performs Centroid decomposition of a matrix x.
 * @param $x : array of arrays | a matrix to be decomposed
 * @param $z_all : array& of arrays | a list of vectors containing all maximizing sign vectors, used as starting point for ISSV+ and updated after the process
 * @param $l : array& of arrays | loading matrix to be overwritten with the decomposition
 * @param $r : array& of arrays | relevance matrix to be overwritten with the decomposition
 * @param $truncation : Int | limit of the amount of columns which are calculated
 * @return void
 */
function cachedTCD($x, &$z_all, &$l, &$r, $truncation)
{
    $n = count($x); // number of rows
    $m = count($x[0]); // number of columns

    if ($m < $truncation) die("Incorrect truncation value ($truncation) for matrix X { n=$n, m=$m } in function cachedTCD");

    $c_column = init_array($m, 1, 0);
    $s = init_array($m, 1, 0);
    $v = init_array($n, 1, 0);

    for ($col = 0; $col < $truncation; $col++)
    {
        // fetch z to pass inside ISSV+
        $z = $z_all[$col];
        //incremental_scalable_sign_vector_plus($x, $n, $m, $s, $v, $z);
        local_sign_vector($x, $n, $m, $s, $v, $z);
        $z_all[$col] = $z;

        $sum_squared = 0;
        for ($j = 0; $j < $m; $j++)
        {
            $tmp = 0;
            for ($i = 0; $i < $n; $i++)
            {
                $tmp += $x[$i][$j] * $z[$i];
            }
            $c_column[$j] = $tmp;
            $sum_squared += $tmp * $tmp;
        }

        $sum_squared = sqrt($sum_squared);

        for ($j = 0; $j < $m; $j++)
        {
            $r[$j][$col] = $c_column[$j] / $sum_squared;
        }

        for ($i = 0; $i < $n; $i++)
        {
            $l[$i][$col] = 0;
            for ($j = 0; $j < $m; $j++)
            {
                $l[$i][$col] += $x[$i][$j] * $r[$j][$col];
            }
        }

        for ($i = 0; $i < $n; $i++)
        {
            for ($j = 0; $j < $m; $j++)
            {
                $x[$i][$j] -= ($l[$i][$col] * $r[$j][$col]);
            }
        }
    }
}


/**
 * Helper function for CD to find the maximizing sign vector using ISSV+ method
 * @param $x : array& of arrays | matrix being currently decomposed
 * @param $n : Int | matrix rows
 * @param $m : Int | matrix cols
 * @param $s : array& | service vector
 * @param $v : array& | service vector
 * @param $z : array& | sign vector to be used as a basis
 * @return void
 */
function incremental_scalable_sign_vector_plus(&$x, $n, $m, &$s, &$v, &$z)
{
    // determine s
    for ($j = 0; $j < $m; $j++)
    {
        $s[$j] = 0;
    }

    for ($i = 0; $i < $n; $i++)
    {
        for ($j = 0; $j < $m; $j++)
        {
            $s[$j] += $z[$i] * $x[$i][$j];
        }
    }

    // determine v
    for ($i = 0; $i < $n; $i++)
    {
        $tmp1 = 0;
        $tmp2 = 0;
        for ($j = 0; $j < $m; $j++)
        {
            $tmp1 += $z[$i] * ($x[$i][$j] * $s[$j]);
            $tmp2 += $x[$i][$j] * $x[$i][$j];
        }
        $v[$i] = $z[$i] * ($tmp1 - $tmp2);
    }

    // find 1st switch pos
    $pos = -1;
    $val = 1E-10;

    for ($i = 0; $i < $n; $i++)
    {
        if ($z[$i] * $v[$i] < 0)
        {
            if (abs($v[$i]) > $val)
            {
                $val = abs($v[$i]);
                $pos = $i;
            }
        }
    }

    while ($pos != -1)
    {
        $val = 1E-10;
        $pos = -1;

        for ($i = 0; $i < $n; $i++)
        {
            if ($z[$i] * $v[$i] < 0)
            {
                if (abs($v[$i]) > $val)
                {
                    $val = abs($v[$i]);
                    $pos = $i;

                    // flip the sign and update V

                    // change sign
                    $z[$pos] = $z[$pos] * (-1);

                    // calculate the direction of sign flip
                    $factor = $z[$pos] + $z[$pos];

                    // update V
                    for ($l = 0; $l < $n; $l++)
                    {
                        if ($l != $pos)
                        {
                            // = <x_l, x_pos>
                            $dot_xl_xpos = 0.0;

                            for ($k = 0; $k < $m; $k++)
                            {
                                $dot_xl_xpos += $x[$l][$k] * $x[$pos][$k];
                            }

                            $v[$l] = $v[$l] + $factor * $dot_xl_xpos;
                        }
                    }
                }
            }
        }
    }
}

/**
 * Helper function for CD to find the maximizing sign vector using LSV method without init
 * @param $x : array& of arrays | matrix being currently decomposed
 * @param $n : Int | matrix rows
 * @param $m : Int | matrix cols
 * @param $s : array& | service vector
 * @param $v : array& | service vector
 * @param $z : array& | sign vector to be used as a basis
 * @return void
 */
function local_sign_vector(&$x, $n, $m, &$s, &$v, &$z)
{
    $z2 = trsp(array($z));
    $direction_col = matmult_AT_B($x, $z2);
    $direction = trsp($direction_col);
    //
    // 2+ pass - update to Z
    //

    $flipped = false;
    $lastNorm = matmult($direction, $direction_col)[0][0] + 1E-10; // eps to avoid "parity flip"

    $direction = $direction[0];

    do
    {
        $flipped = false;

        for ($i = 0; $i < $n; ++$i)
        {
            $signDouble = $z[$i] * 2;
            $gradFlip = 0.0;

            for ($j = 0; $j < $m; ++$j)
            {
                $localMod = $direction[$j] - $signDouble * $x[$i][$j];
                $gradFlip += $localMod * $localMod;
            }

            if ($gradFlip > $lastNorm) // net positive from flipping
            {
                $flipped = true;
                $z[$i] *= -1;
                $lastNorm = $gradFlip + 1E-10;

                for ($j = 0; $j < $m; ++$j)
                {
                    $direction[$j] -= $signDouble * $x[$i][$j];
                }
            }
        }
    } while ($flipped);
}


/**
 * Function analog to linear_interpolated_points, except for matrix and only applied in one column (the base series),
 * which is indicated by the base_series_index. See other function for comments (is in retrieve_query.php).
 * @param $matrix : array& of arrays | a matrix where missing matrix have to interpolated
 * @param $base_series_index : Int | an index of the series to interpolate
 * @return void
 */
function linear_interpolated_base_series_values(&$matrix, $base_series_index)
{
    $rows = count($matrix);
    $mb_start = -1;
    $prev_value = NULL;
    $step = 0;//init

    for ($i = 0; $i < $rows; $i++)
    {
        if (is_null($matrix[$i][$base_series_index]))
        {
            // current value is missing - we either start a new block, or we are in the middle of one

            if ($mb_start == -1)
            { // new missing block
                $mb_start = $i;
                $mb_end = $mb_start + 1;

                //lookahead to find the end
                // INDEX IS NEXT NON-NULL ELEMENT, NOT THE LAST NULL
                // INCLUDING OUT OF BOUNDS IF THE BLOCK ENDS AT THE END OF TS
                while (($mb_end < $rows) && is_null($matrix[$mb_end][$base_series_index]))
                {
                    $mb_end++;
                }

                $next_value = $mb_end == $rows ? NULL : $matrix[$mb_end][$base_series_index];

                if ($mb_start == 0)
                { // special case #1: block starts with array
                    $prev_value = $next_value;
                }
                if ($mb_end == $rows)
                { // special case #2: block ends with array
                    $next_value = $prev_value;
                }
                $step = ($next_value - $prev_value) / ($mb_end - $mb_start + 1);
            }
            $matrix[$i][$base_series_index] = $prev_value + $step * ($i - $mb_start + 1);
        }
        else
        {
            // missing block either ended just new or we're traversing normal data
            $prev_value = $matrix[$i][$base_series_index];
            $mb_start = -1;
        }
    }
}


//////////////////////////////////////////////////////////////////////////////
//                                                                          //
//                       Algebra helper functions                           //
//                                                                          //
//////////////////////////////////////////////////////////////////////////////


function getmeanstddev(&$x, &$mean, &$stddev, $n, $m)
{
    $count = init_vector($m);

    for ($i = 0; $i < $n; ++$i)
    {
        for ($j = 0; $j < $m; ++$j)
        {
            $entry = $x[$i][$j];
            if (!is_null($entry))
            {
                $mean[$j] += $entry;
                $stddev[$j] += $entry * $entry;
                $count[$j]++;
            }
        }
    }

    for ($j = 0; $j < $m; ++$j)
    {
        $stddev[$j] -= ($mean[$j] * $mean[$j]) / $count[$j];
        $stddev[$j] /= $count[$j] - 1;
        $stddev[$j] = sqrt($stddev[$j]);

        $mean[$j] /= $count[$j];
    }
}


/**
 * Calculates root of mean square distance between matrices a and b, uses custom divisor as a counter of relevant elements
 * @param $a : array& of arrays | matrix
 * @param $b : array& of arrays | matrix
 * @param $n : Int | divisor for RSME, amount of elements which can be different
 * @return float
 */
function rootmeansquare_distance(&$a, &$b, $n)
{
    $rows = count($a);
    $columns = count($a[0]);
    $diff = 0;

    for ($row_index = 0; $row_index < $rows; $row_index++)
    {
        for ($column_index = 0; $column_index < $columns; $column_index++)
        {
            $diff += pow(($a[$row_index][$column_index] - $b[$row_index][$column_index]), 2);
        }
    }

    return sqrt($diff / $n);
}


/**
 * Transposed a matrix
 * @param $array : array of arrays | a matrix to transpose
 * @return array : array of arrays | transposed matrix
 */
function trsp($array)
{
    $rows = count($array);
    $cols = count($array[0]);

    $tp = init_array($cols, $rows);

    for ($i = 0; $i < $rows; $i++)
    {
        for ($j = 0; $j < $cols; $j++)
        {
            $tp[$j][$i] = $array[$i][$j];
        }
    }

    return $tp;
}


/** Performs matrix multiplication M1 * M2
 * @param $mat1 : array& of arrays | left operand
 * @param $mat2 : array& of arrays | right operand
 * @return array : array of arrays | new matrix, result of multiplication
 */
function matmult(&$mat1, &$mat2)
{
    $n1 = count($mat1);
    $m1 = count($mat1[0]);
    $n2 = count($mat2);
    $m2 = count($mat2[0]);

    if ($m1 != $n2) die("Incompatible dimensions of matrices in matmult { n1=$n1, m1=$m1; n2=$n2, m2=$m2 } ");

    $res = init_array($n1, $m2);

    for ($i = 0; $i < $n1; $i++)
    {
        for ($j = 0; $j < $m2; $j++)
        {
            $temp = 0.0;

            for ($k = 0; $k < $n2; $k++)
            {
                $temp += $mat1[$i][$k] * $mat2[$k][$j];
            }

            $res[$i][$j] = $temp;
        }
    }

    return $res;
}


/** Performs matrix multiplication M1^T * M2
 * @param $mat1 : array& of arrays | left operand
 * @param $mat2 : array& of arrays | right operand
 * @return array : array of arrays | new matrix, result of multiplication
 */
function matmult_AT_B(&$mat1, &$mat2)
{
    $n1 = count($mat1);
    $m1 = count($mat1[0]);
    $n2 = count($mat2);
    $m2 = count($mat2[0]);

    if ($n1 != $n2) die("Incompatible dimensions of matrices in matmult AT_B { n1=$n1, m1=$m1; n2=$n2, m2=$m2 } ");

    $res = init_array($m1, $m2);

    for ($i = 0; $i < $m1; $i++)
    {
        for ($j = 0; $j < $m2; $j++)
        {
            $temp = 0.0;

            for ($k = 0; $k < $n2; $k++)
            {
                $temp += $mat1[$k][$i] * $mat2[$k][$j];
            }

            $res[$i][$j] = $temp;
        }
    }

    return $res;
}


/** Performs matrix multiplication M1 * M2^T
 * @param $mat1 : array& of arrays | left operand
 * @param $mat2 : array& of arrays | right operand
 * @return array : array of arrays | new matrix, result of multiplication
 */
function matmult_A_BT(&$mat1, &$mat2)
{
    $n1 = count($mat1);
    $m1 = count($mat1[0]);
    $n2 = count($mat2);
    $m2 = count($mat2[0]);

    if ($m1 != $m2) die("Incompatible dimensions of matrices in matmult A_BT { n1=$n1, m1=$m1; n2=$n2, m2=$m2 } ");

    $res = init_array($n1, $n2);

    for ($i = 0; $i < $n1; $i++)
    {
        for ($j = 0; $j < $n2; $j++)
        {
            $temp = 0.0;

            for ($k = 0; $k < $m2; $k++)
            {
                $temp += $mat1[$i][$k] * $mat2[$j][$k];
            }

            $res[$i][$j] = $temp;
        }
    }

    return $res;
}


/** Put a vector into an empty matrix vertically
 * @param $vec : array | vactor to be transformed into a matrix
 * @return array : array of arrays | new matrix, containing an input in the first column
 */
function vecToMatrix(&$vec)
{
    $n = count($vec);

    $res = array();

    for ($i = 0; $i < $n; $i++)
    {
        $res[] = array($vec[$i]);
    }

    return $res;
}

//////////////////////////////////////////////////////////////////////////////
//                                                                          //
//                        Other helper functions                            //
//                                                                          //
//////////////////////////////////////////////////////////////////////////////


/**
 * Creates a data structure for the vectors of rows an array filled with NULL (or other specified value)
 * @param $rows : Int
 * @param $initwith : Float [default=NULL]
 * @return array|null : array with an empty matrix or NULL if arguments are invalid
 */
function init_vector($rows, $initwith = NULL)
{
    $array = array();
    if ($rows == 1)
    {
        return $initwith;
    }
    else
    {
        for ($i = 0; $i < $rows; $i++)
        {
            $array[] = $initwith;
        }
    }
    return $array;
}


/**
 * Creates a data structure for the matrix of rows x columns as a 2D array filled with NULL (or other specified value)
 * @param $rows : Int
 * @param $columns : Int
 * @param $initwith : Float [default=NULL]
 * @return array|null : array of arrays with an empty matrix or NULL if arguments are invalid
 */
function init_array($rows, $columns, $initwith = NULL)
{
    $array = array();
    if ($rows == 1)
    {
        if ($columns == 1)
        {
            return $initwith;
        }
        else
        {
            $array[] = array();
            for ($column_index = 0; $column_index < $columns; $column_index++)
            {
                $array[0][] = $initwith;
            }
        }
    }
    elseif ($columns == 1)
    {
        for ($row_index = 0; $row_index < $rows; $row_index++)
        {
            $array[] = array($initwith);
        }
    }
    else
    {
        for ($row_index = 0; $row_index < $rows; $row_index++)
        {
            $tmp_array = array();
            for ($column_index = 0; $column_index < $columns; $column_index++)
            {
                $tmp_array[] = $initwith;
            }
            $array[] = $tmp_array;
        }
    }
    return $array;
}


/**
 * Prints an array in HTML
 * @param $array : array& | to be printed
 * @param $name : String | a name to be used as a header for printing
 * @return void
 */
function print_array(&$array, $name)
{
    $rows = count($array);
    $columns = count($array[0]);

    echo "<h3>$name :</h3>";
    echo "<table>";
    for ($row_index = 0; $row_index < $rows; $row_index++)
    {
        echo "<tr>";
        if ($columns == 1)
        {
            echo "<td>" . round($array[$row_index], 3) . "</td>";
        }
        else
        {
            for ($column_index = 0; $column_index < $columns; $column_index++)
            {
                echo "<td>" . round($array[$row_index][$column_index], 3) . "</td>";
            }
        }
        echo "</tr>";
    }
    echo "</table>";
}


/**
 * Prints an array formatted for <pre> tag
 * @param $array : array& | to be printed
 * @return void
 */
function print_array_pre(&$array)
{
    $rows = count($array);
    $columns = count($array[0]);

    for ($i = 0; $i < $rows; $i++)
    {
        for ($j = 0; $j < $columns; $j++)
        {
            if (is_null($array[$i][$j]))
            {
                echo " " . "NaN" . "\t";
            }
            else
            {
                echo ($array[$i][$j] >= 0 ? " " : "") . round($array[$i][$j], 2) . "\t";
            }
        }
        echo "\n";
    }
}


/**
 * Prints a header for <pre> tag
 * @param $name : String | a name to be used as a header for printing
 * @param $columns : Int | a number of column headers to be printed
 * @return void
 */
function print_pre_headers($name, $columns)
{
    for ($column_index = 0; $column_index < $columns; $column_index++)
    {
        echo "$name" . "[$column_index]\t";
    }
    echo "\n";
}


/**
 * Prints an array in HTML (color)
 * @param $array : array& | to be printed
 * @param $name : String | a name to be used as a header for printing
 * @return void
 */
function print_array_color(&$array, $name)
{
    $rows = count($array);
    //$columns = count($array[0]);

    echo $name . ":";
    echo "<table>";
    echo "<tr>";

    for ($row_index = 0; $row_index < $rows; $row_index++)
    {
        //for ($column_index=0; $column_index<$columns; $column_index++){
        if ($array[$row_index] == 1)
        {
            echo "<td style='color: blue;'>||||||</td>";
        }
        else
        {
            echo "<td style='color: red;'>||||||</td>";
        }
        //}

    }
    echo "</tr>";
    echo "</table>";
}


/**
 * Prints an array in csv
 * @param $array : array& | to be printed
 * @return void
 */
function print_array_csv(&$array)
{
    $rows = count($array);
    $columns = count($array[0]);

    for ($row_index = 0; $row_index < $rows; $row_index++)
    {
        for ($column_index = 0; $column_index < $columns - 1; $column_index++)
        {
            echo round($array[$row_index][$column_index], 2) . ",";
        }
        echo round($array[$row_index][$columns - 1], 2) . "\n";
    }
}


//////////////////////////////////////////////////////////////////////////////
//                                                                          //
//                       Obsolete helper functions                          //
//                                                                          //
//////////////////////////////////////////////////////////////////////////////


/**
 * Calculates frobenius between matrices a and b, the result is the same as ||a - b||_F
 * @param $a : array of arrays | matrix
 * @param $b : array of arrays | matrix
 * @return float
 */
function frobenius_distance($a, $b)
{
    $rows = count($a);
    $columns = count($a[0]);
    $diff = 0;
    for ($row_index = 0; $row_index < $rows; $row_index++)
    {
        for ($column_index = 0; $column_index < $columns; $column_index++)
        {
            $diff += pow(($a[$row_index][$column_index] - $b[$row_index][$column_index]), 2);
        }
    }
    return sqrt($diff);
}


?>