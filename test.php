<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$connection = pg_connect("host=localhost dbname=revival user=oliver password=R0J-K9wj");
if ($connection) {
    echo 'connected';
} else {
    echo 'there has been an error connecting';
}
/*
    $query = "
        SELECT
            data.sets.title as title,
            data.sets.id as id,
            data.sets.desc as desc,
            data.sets.source_title as source_title,
            data.sets.source_url as source_url
        FROM
            data.sets 
        WHERE data.sets.modified = FALSE
    ";
  */
$query = "SELECT datetime, value FROM data.hourly WHERE value IS NULL LIMIT 10";
$result = pg_query($connection, $query);

if (!$result) {
    exit;
}
$original_datasets = array();
while ($row = pg_fetch_assoc($result)) {
    /* $dataset_object = new stdClass();
     $dataset_object->id = $row["id"];
     $dataset_object->title = $row["title"];
     $dataset_object->desc = $row["desc"];
     $dataset_object->source_title = $row["source_title"];
     $dataset_object->source_url = $row["source_url"];
     $original_datasets[] = $dataset_object;*/
    echo '@' . $row["datetime"] . ' - ' . $row["value"] . ' (type=' . gettype($row["value"]) . ')\n';
}

?>
