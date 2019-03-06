<?php

//////////////////////////////////////////////////////////////////////////////
//                                                                          //
//           RETRIEVAL OF PARAMETERS AND RESOURCES + PREPARATIONS           //
//                                                                          //
//////////////////////////////////////////////////////////////////////////////

// Finalized parameters

//$start = @$_GET['start'];
$start = 631753200000;
if ($start && !preg_match('/^[0-9]+$/', $start)) {
    die("Invalid start parameter: $start");
}
$startTime = gmstrftime('%Y-%m-%d %H:%M:%S', $start / 1000);


// Variable parameters (read from URL)
//$range = @$_GET['range'];
$range = 2000;
$reference_series_ids = array('203', '202', '205');

// Prepare the database connection
include '../connect.php';
$table = 'hourly'; //final


//////////////////////////////////////////////////////////////////////////////
//                                                                          //
//                  RETRIEVAL + PRE-PROCESSING OF DATA FROM DB              //
//                                                                          //
//////////////////////////////////////////////////////////////////////////////


// Since there are multiple reference series, create an array of reference series arrays
$reference_series = array();
//  $reference_series_ids = array();
$all_reference_series_points = array();

//    $reference_series_ids = $_GET['reference_series'];

$reference_series = 0;

// Query the data of each reference series from the database
foreach ($reference_series_ids as $reference_series_id) {
    //if normalization is required, get relevant values from the database

    $query = "
            SELECT
                CONVERT(sys.timestamp_to_str($table.datetime, '%s'), int) as datetime,
                $table.value as value,
                series.title
            FROM
                series
            LEFT JOIN
                $table
            ON
                series.id = $table.series_id
            WHERE 
                series.id = $reference_series_id AND $table.datetime > '$startTime'
            ORDER BY
               datetime
           LIMIT $range
        ";

    $result = monetdb_query($conn, $query);

    if (!$result) {
        exit;
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
    $reference_series_points = linear_interpolated_points($reference_series_points);

    array_push($all_reference_series_points, $reference_series_points);

    $reference_series += 1;
}

// Add all reference series to the object
//$retrieve_object->reference_series = array_values($reference_series);

//////////////////////////////////////////////////////////////////////////////
//                                                                          //
//                 CENTROID DECOMPOSITION + POST-PROCESSING                 //
//                                                                          //
//////////////////////////////////////////////////////////////////////////////


// Push all series (base series + reference series) to matrix X (an array of arrays)
$x = array();

$n = count($reference_series_points);    // number of values per series: rows in the matrix
$m = $reference_series; // number of series (reference series + base series): columns in the matrix

for ($i = 0; $i < $n; $i++) {
    $tmp_array = array();
    for ($j = 0; $j < $m; $j++) {
        array_push($tmp_array, $all_reference_series_points[$j][$i][1]);
    }
    array_push($x, $tmp_array);
}

//////////////////////////////////////////////////////////////////////////////
//                                                                          //
//                  PRINTING OF ALL DATA IN JSON FORMAT                     //
//                                                                          //
//////////////////////////////////////////////////////////////////////////////


// Return data (the retrieve object) in a file in JSON notation
//header('Content-Type: text/javascript');
//echo json_encode($x);

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
    $first_non_null_reached = false;
    $increment = 0;//init
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

?>



<?php
$page_title = "On-line ";
include '../header.php';
?>
<div class="container">
    <div class="col-lg-12">
        <div class="page-header">
            <h2>Stream decomposition
                <small>run-time comparison
            </h2>
        </div>
    </div>
    <button id="play" type="button" class="btn btn-primary">
        <span class="glyphicon glyphicon-play" aria-hidden="true"></span>
    </button>
    <button style="display:none" id="pause" type="button" class="btn btn-info">
        <span class="glyphicon glyphicon-pause" aria-hidden="true"></span>
    </button>

    <div class="col-lg-12">
        <h3></small></h3>
        <div id="scale"></div>
    </div>
    <div class="col-lg-12">
        <div class="panel-group" id="accordion" role="tablist" aria-multiselectable="true">
            <div id="here"></div>
        </div>
    </div>
</div>


<script type='text/javascript'>

    $(function () {

        $('#scale').highcharts({
            chart: {
                type: 'line'
            },
            title: {
                text: null
            },
            xAxis: {
                tickInterval: 1,
                title: {
                    text: 'n'
                },
                labels : {
                    style: { "fontSize" : "15px" }
                }
            },
            yAxis: {
                title: {
                    text: 'run-time [ms]'
                },
                min: 0,
                labels : {
                    style : { "fontSize" : "15px", "font-weight" : "bold" }
                }
            },
            tooltip: {
                formatter: function () {
                    return this.series.name + ' row ' + this.x + ': ' + this.y;
                }
            },
            legend: {
                enabled: true,
                floating: true,
                layout: 'horizontal',
                align: 'right',
                verticalAlign: 'top'
            },
            credits: {
                enabled: false
            },
            exporting: {
                enabled: false
            },
            series: [{
                name: 'updated-CD',
                data: []
            }, {
                name: 'regular-CD',
                data: []
            }, {
                name: 'cached-CD',
                data: []
            }]
        });

        var wait = false;
        var verbose = false;

        var l, r;
        var index = 0;

        var original = <?php echo json_encode($x); ?>;

        //var cd = cached_centroid_decomposition([[1,2,2],[4,4,6]], [null, null, null]);
        var z_values = [null, null, null];

        var x = [original[0], original[1], original[2]];
        var cd = centroid_decomposition(clone_array(x));

        l = cd[0];
        r = cd[1];
        count = cd[2];

        var b = l;
        var v = r;

        var out = '<div class="panel panel-default">'
            + '<div class="panel-heading" role="tab" id="heading">'
            + '<h4 class="panel-title">'
            + '<a role="button" data-toggle="collapse" data-parent="#accordion" href="#collapse" aria-expanded="true" aria-controls="collapse">Initialisation / CD(X<sub>0</sub>)</a>'
            + '</h4>'
            + '</div>'
            + '<div id="collapse" class="panel-collapse collapse" role="tabpanel" aria-labelledby="heading">'
            + '<div class="panel-body">'
            + '<div class="col-sm-4">' + print_table(x, 'X<sub>0</sub> = L<sub>0</sub>*R<sub>0</sub><sup>T</sup>') + '</div>'
            + '<div class="col-sm-4">' + print_table(l, 'L<sub>0</sub>') + '</div>'
            + '<div class="col-sm-4">' + print_table(r, 'R<sub>0</sub>') + '</div>'
            + '</div>';

        $('#here').append(out);

        /*$('#add_row').click(function () {

            var rand = [];
            for (i=0; i<x[0].length; i++){
                var tmp = Math.floor((Math.random() * 10) + 1)-5;
                rand.push(tmp);
            }
            var row = [rand];

            $('.panel-collapse.in').collapse('hide');

            var out = '<div class="panel panel-default">'
                + '<div class="panel-heading" role="tab" id="heading'+index+'">'
                + '<h4 class="panel-title">'
                + '<a role="button" data-toggle="collapse" data-parent="#accordion" href="#collapse'+index+'" aria-expanded="true" aria-controls="collapse'+index+'">'
                + 'Adding a row to X<sub>'+index+'</sub>'
                + '</a>'
                + '</h4>'
                + '</div>'
                + '<div id="collapse'+index+'" class="panel-collapse collapse" role="tabpanel" aria-labelledby="heading'+index+'">'
                + '<div class="panel-body">';

            x = row_append(x,row);

            var t0 = performance.now();
            var ucd = cd_update(b, v, transpose(row));
            var t1 = performance.now();

            b = ucd[0];
            v = ucd[1];
            s = ucd[2];
            //count = ucd[3];
            count = (t1 - t0);

            $('#scale').highcharts().series[0].addPoint(count);


            out += '<div class="col-sm-3"><h4>New row:</h4>'+print_row(row)+'</div>'
                + '<div class="col-sm-3">'
                + '<h4>Updating CD:</h4>'
                + print_table(s, 'S<sub>'+(index+1)+'</sub>')
                + '<p>Duration for CD(S): '+count+'</p>'
                + print_table(b, 'B<sub>'+(index+1)+'</sub>')
                + print_table(v, 'V<sub>'+(index+1)+'</sub>')
                + print_table(mult(b,transpose(v)), 'B<sub>'+(index+1)+'</sub>*V<sub>'+(index+1)+'</sub><sup>T</sup>')
                + '</div>';

            var t0 = performance.now();
            cd = centroid_decomposition(clone_array(x), verbose);
            var t1 = performance.now();


            l = cd[0];
            r = cd[1];
            //count = cd[2];
            count = (t1 - t0);

            $('#scale').highcharts().series[1].addPoint(count);

            out += '<div class="col-sm-3">'
                + '<h4>Regular CD:</h4>'
                + print_table(x, 'X<sub>'+(index+1)+'</sub>')
                + '<p>Duration for CD(X): '+count+'</p>'
                + print_table(l, 'L<sub>'+(index+1)+'</sub>')
                + print_table(r, 'R<sub>'+(index+1)+'</sub>')
                + print_table(mult(l,transpose(r)), 'L<sub>'+(index+1)+'</sub>*R<sub>'+(index+1)+'</sub><sup>T</sup>')
                + '</div>';

            var t0 = performance.now();
            cd = cached_centroid_decomposition(clone_array(x), z_values, verbose);
            var t1 = performance.now();

            l = cd[0];
            r = cd[1];
            //count = cd[2];
            count = (t1 - t0);
            z_values = cd[3];

            out += '<div class="col-sm-3">'
                + '<h4>Cached CD:</h4>'
                + print_table(x, 'X<sub>'+(index+1)+'</sub>')
                + '<p>Duration for CD(X): '+count+'</p>'
                + print_table(l, 'L<sub>'+(index+1)+'</sub>')
                + print_table(r, 'R<sub>'+(index+1)+'</sub>')
                + print_table(mult(l,transpose(r)), 'L<sub>'+(index+1)+'</sub>*R<sub>'+(index+1)+'</sub><sup>T</sup>')
                + '</div>';

            $('#scale').highcharts().series[2].addPoint(count);

            $('#here').prepend(out);
            index++;
        });*/
        var timer = null,
            running = false,
            ztvMax = 0,
            diff = 0,
            i = 0,
            id;

        var counter = 2;


        $(window).keypress(function (e) {
            if (e.which === 32) {

                if (running) {
                    $('#pause').click();
                }
                else {
                    $('#play').click();
                }
            }
        });


        $('#pause').click(function () {
            $('#play').show();
            $('#pause').hide();
            clearTimeout(timer);
            timer = null;
            running = false;
        });

        $('#reset').click(function () {
            clearTimeout(timer);
            timer = null;
            running = false;
        });


        $('#play').click(function () {
            $('#pause').show();
            $('#play').hide();
            if (!running) {
                running = true;
                $('#log').prepend("Running<br>");
                (function next() {
                    timer = setTimeout(function () {
                        counter++;

                        var row = [original[counter]];


                        $('.panel-collapse.in').collapse('hide');

                        var out = '<div class="panel panel-default">'
                            + '<div class="panel-heading" role="tab" id="heading' + index + '">'
                            + '<h4 class="panel-title">'
                            + '<a role="button" data-toggle="collapse" data-parent="#accordion" href="#collapse' + index + '" aria-expanded="true" aria-controls="collapse' + index + '">'
                            + 'Adding a row to X<sub>' + index + '</sub>'
                            + '</a>'
                            + '</h4>'
                            + '</div>'
                            + '<div id="collapse' + index + '" class="panel-collapse collapse" role="tabpanel" aria-labelledby="heading' + index + '">'
                            + '<div class="panel-body">';

                        x = row_append(x, row);

                        var t0 = performance.now();
                        var ucd = cd_update(b, v, transpose(row));
                        var t1 = performance.now();

                        b = ucd[0];
                        v = ucd[1];
                        s = ucd[2];
                        //count = ucd[3];
                        count = (t1 - t0);

                        $('#scale').highcharts().series[0].addPoint(count);


                        out += '<div class="col-sm-3"><h4>New row:</h4>' + print_row(row) + '</div>'
                            + '<div class="col-sm-3">'
                            + '<h4>Updating-CD:</h4>'
                            + print_table(s, 'S<sub>' + (index + 1) + '</sub>')
                            + '<p>Computation time for CD(S): ' + Math.round(count * 1000) / 1000 + 'ms</p>'
                            + print_table(b, 'B<sub>' + (index + 1) + '</sub>')
                            + print_table(v, 'V<sub>' + (index + 1) + '</sub>')
                            + print_table(mult(b, transpose(v)), 'B<sub>' + (index + 1) + '</sub>*V<sub>' + (index + 1) + '</sub><sup>T</sup>')
                            + '</div>';

                        var t0 = performance.now();
                        cd = centroid_decomposition(clone_array(x), verbose);
                        var t1 = performance.now();


                        l = cd[0];
                        r = cd[1];
                        //count = cd[2];
                        count = (t1 - t0);

                        $('#scale').highcharts().series[1].addPoint(count);

                        out += '<div class="col-sm-3">'
                            + '<h4>Regular CD:</h4>'
                            + print_table(x, 'X<sub>' + (index + 1) + '</sub>')
                            + '<p>Computation time for CD(X): ' + Math.round(count * 1000) / 1000 + 'ms</p>'
                            + print_table(l, 'L<sub>' + (index + 1) + '</sub>')
                            + print_table(r, 'R<sub>' + (index + 1) + '</sub>')
                            + print_table(mult(l, transpose(r)), 'L<sub>' + (index + 1) + '</sub>*R<sub>' + (index + 1) + '</sub><sup>T</sup>')
                            + '</div>';

                        var t0 = performance.now();
                        cd = cached_centroid_decomposition(clone_array(x), z_values, verbose);
                        var t1 = performance.now();

                        l = cd[0];
                        r = cd[1];
                        //count = cd[2];
                        count = (t1 - t0);
                        z_values = cd[3];

                        out += '<div class="col-sm-3">'
                            + '<h4>Cached-CD:</h4>'
                            + print_table(x, 'X<sub>' + (index + 1) + '</sub>')
                            + '<p>Computation time for cached-CD(X): ' + Math.round(count * 1000) / 1000 + 'ms</p>'
                            + print_table(l, 'L<sub>' + (index + 1) + '</sub>')
                            + print_table(r, 'R<sub>' + (index + 1) + '</sub>')
                            + print_table(mult(l, transpose(r)), 'L<sub>' + (index + 1) + '</sub>*R<sub>' + (index + 1) + '</sub><sup>T</sup>')
                            + '</div>';

                        $('#scale').highcharts().series[2].addPoint(count);

                        $('#here').prepend(out);
                        index++;
                        next();
                    }, 600);
                })();
            }
        });
    });


    //////////////////////////////////////////////////////////////////////////////
    //                                                                          //
    //                CENTROID DECOMPOSITION RELATED ALGORITHMS                 //
    //                                                                          //
    //////////////////////////////////////////////////////////////////////////////


    function cd_update(b0, v0, b, verbose) {

        let v1;
        let b1;
        let bs;
        let vs;
        let count;
        let cd;
        let s;

        const epsilon = 0.001;

        let n = mult(transpose(v0), b);
        if (verbose) print_array(n, "n");
        if (verbose) print_array(b, "b");
        if (verbose) print_array(mult(v0, n), "mult(v0, n)");
        let q = sub(b, mult(v0, n));
        if (verbose) print_array(q, "q");
        let qnorm = euclid_norm(q);

        if (verbose) print_array(q, "q");
        if (verbose) $('#here').append("qnorm: " + qnorm + "<br>");

        if (qnorm < epsilon) {
            //console.log("Qnorm < epsilon");

            s = row_append(b0, transpose(n));
            cd = centroid_decomposition(s, verbose);

            bs = cd[0];
            vs = cd[1];
            count = cd[2];

            if (verbose) print_array(bs, "bs");
            if (verbose) print_array(vs, "vs");

            b1 = bs;
            v1 = mult(dyn_col_append(v0, 0), dyn_row_append(vs, 0));
        }

        else {
            //console.log("Qnorm > epsilon");

            let q_big = scalar_div(q, qnorm);
            if (verbose) print_array(q_big, "q_big");

            let tmp1 = dyn_col_append(b0, 0);
            let tmp2 = dyn_col_append(transpose(n), euclid_norm(q));
            s = row_append(tmp1, tmp2);

            cd = centroid_decomposition_update(z_values, s, verbose);

            bs = cd[0];
            vs = cd[1];
            count = cd[2];

            if (verbose) print_array(bs, "bs");
            if (verbose) print_array(vs, "vs");

            b1 = bs;
            v1 = mult(col_append(v0, q_big), vs);
        }

        return [b1, v1, s, count];
    }

    function scalable_sign_vector(x, verbose) {
        var m = x.length; // number of rows
        var n = x[0].length; // number of columns

        var z = [];
        for (i = 0; i < m; i++) {
            z.push([1]);
        }

        var s = init_array(n, 1); //(double[n][1])
        var v = init_array(m, 1); //(double[m][1])

        var count = 0;
        var pos = 0;
        do {
            //console.log("Z "+z);
            count++;
            // change sign
            if (pos === 0) {
                for (i = 0; i < m; i++) {
                    z[i][0] = 1;
                }
            }
            else {
                var tmp = z[pos - 1][0];
                z[pos - 1][0] = tmp * (-1);
            }

            for (y = 0; y < n; y++) {
                s[y][0] = 0;
            }
            for (var i = 0; i < m; i++) {
                s = add(s, scalar_mult(transpose(extract_row(x, i)), z[i][0]));
            }

            if (verbose) print_array(s, "S");

            for (var i = 0; i < m; i++) {
                var tmp1 = get_value(mult(extract_row(x, i), s));
                var tmp2 = get_value(mult(extract_row(x, i), transpose(extract_row(x, i))));
                v[i][0] = (tmp1 - z[i][0] * tmp2);
                //console.log("v: "+v[i][0]);
            }

            if (verbose) print_array(v, "V");

            // search next element
            val = 0;
            pos = 0;
            for (var i = 0; i < m; i++) {
                if ((z[i][0] * v[i][0]) < 0) {
                    if (Math.abs(v[i][0]) > val) {
                        //console.log(Math.abs(v[i][0]) + " > "+ val);
                        val = Math.abs(v[i][0]);
                        pos = i + 1;
                    }
                }
            }
            //console.log("pos: "+pos);

        } while (pos !== 0);
        if (verbose) print_array(z, "z");
        //console.log(count);
        return [z, count];
    }


    function cached_scalable_sign_vector(z, x, verbose) {
        var m = x.length; // number of rows
        var n = x[0].length; // number of columns

        if (z == null) {
            z = [];
            for (i = 0; i < m; i++) {
                z.push([1]);
            }
        }
        else {
            while (z.length < m) {
                z.push([1]);
                //console.log("push");
            }
        }

        let s = init_array(n, 1); //(double[n][1])
        let v = init_array(m, 1); //(double[m][1])

        let count = 0;
        let pos = 0;
        do {
            //console.log("Z "+z);

            count++;
            // change sign
            /*if (pos == 0){
                for (i=0; i< m; i++){
                    z[i][0] = 1;
                }
            }*/
            if (pos !== 0) {
                var tmp = z[pos - 1][0];
                z[pos - 1][0] = tmp * (-1);
            }

            for (y = 0; y < n; y++) {
                s[y][0] = 0;
            }
            for (var i = 0; i < m; i++) {
                s = add(s, scalar_mult(transpose(extract_row(x, i)), z[i][0]));
            }

            if (verbose) print_array(s, "S");

            for (var i = 0; i < m; i++) {
                var tmp1 = get_value(mult(extract_row(x, i), s));
                var tmp2 = get_value(mult(extract_row(x, i), transpose(extract_row(x, i))));
                v[i][0] = (tmp1 - z[i][0] * tmp2);
                //console.log("v: "+v[i][0]);
            }

            if (verbose) print_array(v, "V");

            // search next element
            val = 0;
            pos = 0;
            for (var i = 0; i < m; i++) {
                if ((z[i][0] * v[i][0]) < 0) {
                    if (Math.abs(v[i][0]) > val) {
                        //console.log(Math.abs(v[i][0]) + " > "+ val);
                        val = Math.abs(v[i][0]);
                        pos = i + 1;
                    }
                }
            }
            //console.log("pos: "+pos);
        } while (pos != 0);
        if (verbose) print_array(z, "z");
        //console.log(count);
        return [z, count];
    }

    function centroid_decomposition(x, verbose) {
        if (verbose) $('#here').append("CD");
        var m = x.length; // number of rows
        var n = x[0].length; // number of columns

        if (verbose) print_array(x, "X");

        var l, r, l_column, r_column, c_column;

        var count = 0;

        for (var j = 0; j < n; j++) {

            var ssv = scalable_sign_vector(x, verbose);
            var z = ssv[0];
            //console.log(" "+z);
            count += ssv[1];
            //var z = brute_force_sign_vector(x);

            //print_array(z, "z");

            c_column = mult(transpose(x), z);
            r_column = scalar_div(c_column, euclid_norm(c_column));
            l_column = mult(x, r_column);

            if (j === 0) {
                r = clone_array(r_column);
                l = clone_array(l_column);
            }
            else {
                r = col_append(r, clone_array(r_column));
                l = col_append(l, clone_array(l_column));
            }

            x = sub(x, mult(l_column, transpose(r_column)));

        }

        if (verbose) print_array(l, "l");
        if (verbose) print_array(r, "r");

        return [l, r, count];
    }

    function cached_centroid_decomposition(x, z_values, verbose) {
        if (verbose) $('#here').append("CD");
        var m = x.length; // number of rows
        var n = x[0].length; // number of columns

        if (verbose) print_array(x, "X");

        var l, r, l_column, r_column, c_column;

        var count = 0;

        for (var j = 0; j < n; j++) {

            var ssv = cached_scalable_sign_vector(z_values[j], x, verbose);
            var z = ssv[0];
            z_values[j] = z;
            //console.log(" "+z);
            count += ssv[1];
            //var z = brute_force_sign_vector(x);

            //print_array(z, "z");

            c_column = mult(transpose(x), z);
            r_column = scalar_div(c_column, euclid_norm(c_column));
            l_column = mult(x, r_column);

            if (j === 0) {
                r = clone_array(r_column);
                l = clone_array(l_column);
            }
            else {
                r = col_append(r, clone_array(r_column));
                l = col_append(l, clone_array(l_column));
            }

            x = sub(x, mult(l_column, transpose(r_column)));

        }

        if (verbose) print_array(l, "l");
        if (verbose) print_array(r, "r");

        return [l, r, count, z_values];
    }


    //////////////////////////////////////////////////////////////////////////////
    //                                                                          //
    //                         HELPERS AND INITIALIZERS                         //
    //                                                                          //
    //////////////////////////////////////////////////////////////////////////////

    function print_array(array, title) {
        var rows = array.length;
        var columns = array[0].length;

        $('#here').append(title + ":");
        $('#here').append("<table>");
        for (row_index = 0; row_index < rows; row_index++) {
            $('#here').append("<tr>");
            for (column_index = 0; column_index < columns; column_index++) {
                $('#here').append("<td>" + (Math.round(array[row_index][column_index] * 100) / 100).toFixed(2) + "</td>");
            }
            $('#here').append("</tr>");
        }
        $('#here').append("</table>");
    }

    function print_row(array) {
        var rows = array.length;
        var columns = array[0].length;

        var out = "<table>";
        for (row_index = 0; row_index < rows; row_index++) {
            out += "<tr>";
            for (column_index = 0; column_index < columns; column_index++) {
                out += "<td style='padding-right: 10px;'>" + (Math.round(array[row_index][column_index] * 100) / 100).toFixed(2) + "</td>";
            }
            out += "</tr>";
        }
        out += "</table>";
        return out;
    }

    function print_table(array, title) {
        var rows = array.length;
        var columns = array[0].length;

        var out = "<table>";
        for (row_index = 0; row_index < rows; row_index++) {
            if (row_index == 0) {
                out += "<tr><td style='padding-right: 10px; min-width: 70px;'><b>" + title + ":</b></td>";
            }
            else {
                out += "<tr><td></td>";
            }
            for (column_index = 0; column_index < columns; column_index++) {
                out += "<td style='padding-right: 10px; text-align: right;'>" + (Math.round(array[row_index][column_index] * 100) / 100).toFixed(2) + "</td>";
            }
            out += "</tr>";
        }
        out += "</table>";
        out += "<br>";

        return out;
    }

</script>

<?php include '../footer.php'; ?>
