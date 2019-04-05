<?php
require 'monetdb/php_monetdb.php';
require 'monetdb/php_monetdb_ext.php';
$conn = monetdb_connect(
    $lang = "sql",
    $host = "192.168.64.1", $port = "50000",
    $username = "oliver", $password = "R0J-K9wj",
    $database = "revival");
if (!$conn) {
    echo 'DB connection has failed to be established';
    die(monetdb_last_error());
}
monetdb_query($conn, "SET SCHEMA data");
monetdb_query($conn, "SET TIME ZONE INTERVAL '+00:00' HOUR TO MINUTE");


function get_serie_data($conn, $table, $serie_id, $start, $end, $norm = 0) {
    $qry = "
        SELECT
            sys.epoch($table.datetime) * 1000 AS datetime,
            $table.value as value,
            series.title as title
        FROM
            series
        LEFT JOIN
            $table
        ON
            series.id = $table.series_id
        WHERE 
            series_id = $serie_id AND datetime between sys.epoch($start) and sys.epoch($end)
        ORDER BY
            datetime
        ";
    $res = monetdb_query($conn, monetdb_escape_string($qry)) or trigger_error(monetdb_last_error());
    $obj = new stdClass();
    $ponts = array();
    $serie_name = NULL;
    if ($norm != 0) {
        $statistics = get_statistics($conn, $table, $serie_id);
    }
    while ($row = monetdb_fetch_assoc($res)) {
        if ($serie_name === NULL) {
            $serie_name = $row['title'];
        } 
        $datetime = (int)($row['datetime']);
        $value = is_null($row['value']) ? NULL : (float)($row['value']);
        if ($value === NULL) {
            $points[] = array($datetime, NULL);
        } else {
            if ($norm === 0) {
                $points[] = array($datetime, $value);
            } elseif ($norm === 1) {
                $mean = $statistics -> {'mean'};
                $stddev = $statistics -> {'stddev'};
                $points[] = array($datetime, ($value - $mean) / $stddev);
            } elseif ($norm === 2) {
                $min = $statistics -> {'min'};
                $max = $statistics -> {'max'};
                $points[] = array($datetime, ($value - $min) / ($max - $min));
            }
        }
    }
    $obj -> {'id'} = $serie_id;
    $obj -> {'title'} = $serie_name;
    $obj -> {'points'} = $points;
    return $obj;
}

function get_timestamps($conn, $table, $serie_id) {
    $qry = "
    select
        sys.epoch(datetime) * 1000 as tick
    from $table 
    where series_id=$serie_id and datetime>=sys.epoch($start) and datetime<=sys.epoch($end)
    ";
    $res = monetdb_query($conn, monetdb_escape_string($qry)) or trigger_error(monetdb_last_error());
    $time_stamps = array();
    while ( $row = monetdb_fetch_assoc($res) )
    {
        array_push($time_stamps, (int)$row['tick']);
    }
    return $time_stamps;
}

function get_statistics($conn, $table, $serie_id) {
    $qry = "
    SELECT
        avg($table.value) as mean,
        sys.stddev_samp($table.value) as stddev,
        min($table.value) as min,
        max($table.value) as max
    FROM 
        $table 
    WHERE 
        $table.value IS NOT NULL AND $table.series_id=$serie_id
    GROUP BY
        $table.series_id
    ";
    $res = monetdb_query($conn, monetdb_escape_string($qry)) or trigger_error(monetdb_last_error());
    
    $statistics = new stdClass();
    $row = monetdb_fetch_assoc($res);
    $statistics -> mean = (float)$row["mean"];
    $statistics -> stddev = (float)$row["stddev"];
    $statistics -> min = (float)$row["min"];
    $statistics -> max = (float)$row["max"];
    
    return $statistics;
}

function get_data_out_range($conn, $table, $serie_id, $start, $end, $start_drop_ts, $end_drop_ts, $norm) {
    $qry = "
    SELECT
        sys.epoch($table.datetime) * 1000 AS datetime,
        CASE
        WHEN datetime BETWEEN sys.epoch($start_drop_ts) AND sys.epoch($end_drop_ts)
            THEN NULL 
        ELSE $table.value
        END AS value
    FROM
        $table
    WHERE 
        series_id = $serie_id AND datetime between sys.epoch($start) and sys.epoch($end)
    ORDER BY
        datetime
    ";
    $res = monetdb_query($conn, monetdb_escape_string($qry)) or trigger_error(monetdb_last_error());
    if ($norm != 0) {
        $statistics = get_statistics($conn, $table, $serie_id);
    }
    $points = array();
    while ($row = monetdb_fetch_assoc($res)) {
        $datetime = (int)($row['datetime']);
        $value = is_null($row['value']) ? NULL : (float)($row['value']);
        if ($value === NULL) {
            $points[] = array($datetime, NULL);
        } else {
            if ($norm === 0) {
                $points[] = array($datetime, $value);
            } elseif ($norm === 1) {
                $mean = $statistics -> {'mean'};
                $stddev = $statistics -> {'stddev'};
                $points[] = array($datetime, ($value - $mean) / $stddev);
            } elseif ($norm === 2) {
                $min = $statistics -> {'min'};
                $max = $statistics -> {'max'};
                $points[] = array($datetime, ($value - $min) / ($max - $min));
            }
        }
    }
    return $points;
}

function get_data_in_range($conn, $table, $serie_id, $start, $end, $start_drop_ts, $end_drop_ts, $norm) {
    $qry = "
    SELECT
        sys.epoch($table.datetime) * 1000 AS datetime,
        CASE
        WHEN datetime BETWEEN sys.epoch($start_drop_ts) AND sys.epoch($end_drop_ts)
            THEN $table.value
        ELSE NULL
        END AS value
    FROM
        $table
    WHERE 
        series_id = $serie_id AND datetime between sys.epoch($start) and sys.epoch($end)
    ORDER BY
        datetime
    ";
    $res = monetdb_query($conn, monetdb_escape_string($qry)) or trigger_error(monetdb_last_error());
    if ($norm != 0) {
        $statistics = get_statistics($conn, $table, $serie_id);
    }
    $points = array();
    while ($row = monetdb_fetch_assoc($res)) {
        $datetime = (int)($row['datetime']);
        $value = is_null($row['value']) ? NULL : (float)($row['value']);
        if ($value === NULL) {
            $points[] = array($datetime, NULL);
        } else {
            if ($norm === 0) {
                $points[] = array($datetime, $value);
            } elseif ($norm === 1) {
                $mean = $statistics -> {'mean'};
                $stddev = $statistics -> {'stddev'};
                $points[] = array($datetime, ($value - $mean) / $stddev);
            } elseif ($norm === 2) {
                $min = $statistics -> {'min'};
                $max = $statistics -> {'max'};
                $points[] = array($datetime, ($value - $min) / ($max - $min));
            }
        }
    }
    return $points;
}
?>
