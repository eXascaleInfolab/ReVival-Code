<?php

include '../connect.php';

$query = "
        SELECT
            sets.title as title,
            sets.id as id,
            sets.\"desc\" as \"desc\",
            sets.source_title as source_title,
            sets.source_url as source_url
        FROM
            sets 
        WHERE sets.modified = FALSE AND sets.id < 5
    ";

$result = monetdb_query($conn, $query);

if (!$result) {
    die(monetdb_last_error());
}
$original_datasets = array();
while ($row = monetdb_fetch_assoc($result)) {
    $dataset_object = new stdClass();
    $dataset_object->id = $row["id"];
    $dataset_object->title = $row["title"];
    $dataset_object->desc = $row["desc"];
    $dataset_object->source_title = $row["source_title"];
    $dataset_object->source_url = $row["source_url"];
    $original_datasets[] = $dataset_object;
}

foreach ($original_datasets as $dataset_object) {
    $query = "
        SELECT
            COUNT(*) as count
        FROM
            sets_series
        WHERE
            set_id = " . $dataset_object->id;

    $result = monetdb_query($conn, $query);

    if (!$result) {
        die(monetdb_last_error());
    }
    while ($row = monetdb_fetch_assoc($result)) {
        $dataset_object->amount = $row["count"];
    }

    $query = "
        SELECT
            COUNT(*) as \"values\"
        FROM
            sets_series
        LEFT JOIN
            series
        ON
            sets_series.serie_id = series.id
        LEFT JOIN
            hourly
        ON
            series.id = hourly.series_id
        WHERE
            sets_series.set_id = " . $dataset_object->id . "
        AND 
            hourly.value IS NOT NULL";

    $result = monetdb_query($conn, $query);

    if (!$result) {
        die(monetdb_last_error());
    }
    while ($row = monetdb_fetch_assoc($result)) {
        $dataset_object->values = $row["values"];
    }

    $query = "
        SELECT
            COUNT(*) as missing
        FROM
            sets_series
        LEFT JOIN
            series
        ON
            sets_series.serie_id = series.id
        LEFT JOIN
            hourly
        ON
            series.id = hourly.series_id
        WHERE
            sets_series.set_id = " . $dataset_object->id . "
        AND 
            hourly.value IS NULL";

    $result = monetdb_query($conn, $query);

    if (!$result) {
        die(monetdb_last_error());
    }
    while ($row = monetdb_fetch_assoc($result)) {
        $dataset_object->missing = $row["missing"];
    }
}

$query = "
        SELECT
            sets.title as title,
            sets.id as id,
            sets.\"desc\" as \"desc\",
            sets.source_title as source_title,
            sets.source_url as source_url
        FROM
            sets 
        WHERE sets.modified = TRUE
    ";

$result = monetdb_query($conn, $query);

if (!$result) {
    die(monetdb_last_error());
}
$modified_datasets = array();
while ($row = monetdb_fetch_assoc($result)) {
    $dataset_object = new stdClass();
    $dataset_object->id = $row["id"];
    $dataset_object->title = $row["title"];
    $dataset_object->desc = $row["desc"];
    $dataset_object->source_title = $row["source_title"];
    $dataset_object->source_url = $row["source_url"];
    $modified_datasets[] = $dataset_object;
}

foreach ($modified_datasets as $dataset_object) {
    $query = "
        SELECT
            COUNT(*) as count
        FROM
            sets_series
        WHERE
            set_id = " . $dataset_object->id;

    $result = monetdb_query($conn, $query);

    if (!$result) {
        die(monetdb_last_error());
    }
    while ($row = monetdb_fetch_assoc($result)) {
        $dataset_object->amount = $row["count"];
    }

    $query = "
        SELECT
            COUNT(*) as \"values\"
        FROM
            sets_series
        LEFT JOIN
            series
        ON
            sets_series.serie_id = series.id
        LEFT JOIN
            hourly
        ON
            series.id = hourly.series_id
        WHERE
            sets_series.set_id = " . $dataset_object->id . "
        AND 
            hourly.value IS NOT NULL";

    $result = monetdb_query($conn, $query);

    if (!$result) {
        die(monetdb_last_error());
    }
    while ($row = monetdb_fetch_assoc($result)) {
        $dataset_object->values = $row["values"];
    }

    $query = "
        SELECT
            COUNT(*) as missing
        FROM
            sets_series
        LEFT JOIN
            series
        ON
            sets_series.serie_id = series.id
        LEFT JOIN
            hourly
        ON
            series.id = hourly.series_id
        WHERE
            sets_series.set_id = " . $dataset_object->id . "
        AND 
            hourly.value IS NULL";

    $result = monetdb_query($conn, $query);

    if (!$result) {
        die(monetdb_last_error());
    }
    while ($row = monetdb_fetch_assoc($result)) {
        $dataset_object->missing = $row["missing"];
    }
}

$query = "
        SELECT
            sets.title as title,
            sets.id as id,
            sets.\"desc\" as \"desc\",
            sets.source_title as source_title,
            sets.source_url as source_url
        FROM
            sets 
        WHERE sets.modified = FALSE AND sets.id > 4
    ";

$result = monetdb_query($conn, $query);

if (!$result) {
    die(monetdb_last_error());
}

$complete_datasets = array();
while ($row = monetdb_fetch_assoc($result)) {
    $dataset_object = new stdClass();
    $dataset_object->id = $row["id"];
    $dataset_object->title = $row["title"];
    $dataset_object->desc = $row["desc"];
    $dataset_object->source_title = $row["source_title"];
    $dataset_object->source_url = $row["source_url"];
    $complete_datasets[] = $dataset_object;
}

foreach ($complete_datasets as $dataset_object) {
    $query = "
        SELECT
            COUNT(*) as count
        FROM
            sets_series
        WHERE
            set_id = " . $dataset_object->id;

    $result = monetdb_query($conn, $query);

    if (!$result) {
        die(monetdb_last_error());
    }
    while ($row = monetdb_fetch_assoc($result)) {
        $dataset_object->amount = $row["count"];
    }

    $query = "
        SELECT
            COUNT(*) as \"values\"
        FROM
            sets_series
        LEFT JOIN
            series
        ON
            sets_series.serie_id = series.id
        LEFT JOIN
            hourly
        ON
            series.id = hourly.series_id
        WHERE
            sets_series.set_id = " . $dataset_object->id . "
        AND 
            hourly.value IS NOT NULL";

    $result = monetdb_query($conn, $query);

    if (!$result) {
        die(monetdb_last_error());
    }
    while ($row = monetdb_fetch_assoc($result)) {
        $dataset_object->values = $row["values"];
    }

    $query = "
        SELECT
            COUNT(*) as missing
        FROM
            sets_series
        LEFT JOIN
            series
        ON
            sets_series.serie_id = series.id
        LEFT JOIN
            hourly
        ON
            series.id = hourly.series_id
        WHERE
            sets_series.set_id = " . $dataset_object->id . "
        AND 
            hourly.value IS NULL";

    $result = monetdb_query($conn, $query);

    if (!$result) {
        die(monetdb_last_error());
    }
    while ($row = monetdb_fetch_assoc($result)) {
        $dataset_object->missing = $row["missing"];
    }
}

$page_title = "Data sets";
include '../header.php';
?>
<div class="container">
    <div class="page-header">
        <h2>Data sets</h2>
    </div>
    <p>To recover a dataset, click on its title from the table below.</p>
    <h3>Original</h3>
    <p>These data sets contain only raw data and have not been modified (by removing existing values). This allows the
        recovery with CD to be tested on entirely raw, real-world data.</p>
    <table class="table">
        <tr>
            <th>Title</th>
            <th>Source</th>
            <th style="text-align: right">Time series</th>
            <th style="text-align: right">Existing values</th>
            <th style="text-align: right">Missing values</th>
        </tr>

        <?php foreach ($original_datasets as $dataset_object) { ?>

            <tr>
                <td>
                    <a href="explore.php?dataset=<?php echo $dataset_object->id; ?>"><?php echo $dataset_object->title; ?></a>
                </td>
                <td><?php echo $dataset_object->source_title; ?> <a
                            href="<?php echo $dataset_object->source_url; ?>"><span class="glyphicon glyphicon-link"
                                                                                    aria-hidden="true"></span></a></td>
                <td style="text-align: right"><?php echo number_format($dataset_object->amount, 0, '.', "'"); ?></td>
                <td style="text-align: right"><?php echo number_format($dataset_object->values, 0, '.', "'"); ?></td>
                <td style="text-align: right"><?php echo number_format($dataset_object->missing, 0, '.', "'"); ?></td>
            </tr>

        <?php } ?>

    </table>

    <h3>Modified</h3>
    <p>These data sets have been modified by removing some values in select time series. </p>
    <table class="table">
        <tr>
            <th>Title</th>
            <th>Source</th>
            <th style="text-align: right">Time series</th>
            <th style="text-align: right">Existing values</th>
            <th style="text-align: right">Missing values</th>
        </tr>

        <?php foreach ($modified_datasets as $dataset_object) { ?>

            <tr>
                <td>
                    <a href="explore.php?dataset=<?php echo $dataset_object->id; ?>"><?php echo $dataset_object->title; ?></a>
                </td>
                <td><?php echo $dataset_object->source_title; ?> <a
                            href="<?php echo $dataset_object->source_url; ?>"><span class="glyphicon glyphicon-link"
                                                                                    aria-hidden="true"></span></a></td>
                <td style="text-align: right"><?php echo number_format($dataset_object->amount, 0, '.', "'"); ?></td>
                <td style="text-align: right"><?php echo number_format($dataset_object->values, 0, '.', "'"); ?></td>
                <td style="text-align: right"><?php echo number_format($dataset_object->missing, 0, '.', "'"); ?></td>
            </tr>

        <?php } ?>

    </table>

    <h3>Complete</h3>
    <p>These data sets have no missing values. </p>
    <table class="table">
        <tr>
            <th>Title</th>
            <th>Source</th>
            <th style="text-align: right">Time series</th>
            <th style="text-align: right">Existing values</th>
        </tr>

        <?php foreach ($complete_datasets as $dataset_object) { ?>

            <tr>
                <td>
                    <a href="explore_complete.php?dataset=<?php echo $dataset_object->id; ?>"><?php echo $dataset_object->title; ?></a>
                </td>
                <td><?php echo $dataset_object->source_title; ?> <a
                            href="<?php echo $dataset_object->source_url; ?>"><span class="glyphicon glyphicon-link"
                                                                                    aria-hidden="true"></span></a></td>
                <td style="text-align: right"><?php echo number_format($dataset_object->amount, 0, '.', "'"); ?></td>
                <td style="text-align: right"><?php echo number_format($dataset_object->values, 0, '.', "'"); ?></td>
            </tr>

        <?php } ?>

    </table>

</div>
<?php
include '../footer.php';
monetdb_disconnect();
?>
