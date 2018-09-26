<?php
//require 'php_monetdb.php';

$php_monetdb_ext_preparedstatements_list = array();

function monetdb_prepare($conn, $prepid, $querytemplate)
{
    global $php_monetdb_ext_preparedstatements_list;

    $php_monetdb_ext_preparedstatements_list[$prepid] = $querytemplate;

    return 0;
}

function monetdb_execute($conn, $prepid, $params = NULL)
{
    global $php_monetdb_ext_preparedstatements_list;

    $querytemplate = $php_monetdb_ext_preparedstatements_list[$prepid];

    $num_args = func_num_args();

    if ($num_args < 2) {
        die('misuse of monetdb_execute() - not enough arguments (min /2)');
    } else if ($num_args == 2 || $params == NULL) {
        // non-parametric
    } else {
        for ($i = 2; $i < $num_args; $i++) {
            $j = $i - 1;
            $querytemplate = str_replace('$' . $j, $params[$j - 1], $querytemplate);
        }
    }

    return monetdb_query($conn, $querytemplate);
}

?>
