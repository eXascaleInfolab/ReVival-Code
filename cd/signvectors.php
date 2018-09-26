<?php
$page_title = "Strategy comparison";
include '../header.php';

$interval = @$_GET['interval'];

if (!isset($interval)) {
    $interval = 1000;
}
$amount = 5;
?>
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div class="page-header">
                <h2>Maximizing Sign Vector
                    <small>Strategy comparison</small>
                </h2>
            </div>
        </div>
        <?php for ($i = 0; $i < $amount; $i++) { ?>
            <div class="col-md-12">
                <h3>Example #<?php echo $i + 1; ?>
                    <button id="play<?php echo $i; ?>" type="button" class="btn btn-primary">
                        <span class="glyphicon glyphicon-play" aria-hidden="true"></span>
                    </button>
                    <button style="display:none" id="pause<?php echo $i; ?>" type="button" class="btn btn-info">
                        <span class="glyphicon glyphicon-pause" aria-hidden="true"></span>
                    </button>
                    <button id="reset<?php echo $i; ?>" type="button" class="btn btn-default">
                        <span class="glyphicon glyphicon-repeat" aria-hidden="true"></span>
                    </button>
                </h3>
            </div>
            <div class="col-md-5">
                <h4>Sign Vector Z computation</h4>
                <div id="z-ssv-chart<?php echo $i; ?>" style="height: 60vh; width: 20%; float: left;"></div>
                <div id="z-dsv-chart<?php echo $i; ?>" style="height: 60vh; width: 20%; float: left;"></div>
                <div id="z-tsv-chart<?php echo $i; ?>" style="height: 60vh; width: 20%; float: left;"></div>
                <div id="z-psv-chart<?php echo $i; ?>" style="height: 60vh; width: 20%; float: left;"></div>
                <div id="z-optimal-chart<?php echo $i; ?>" style="height: 60vh; width: 20%; float: left;"></div>
            </div>
            <div class="col-md-3">
                <h4>Z<sup>T</sup>*V</h4>
                <div id="local_ztv-chart<?php echo $i; ?>" style="height: 60vh;"></div>
            </div>
            <div class="col-md-3">
                <h4>Statistics</h4>
                <div id="iterations<?php echo $i; ?>" style="height: 30vh;"></div>
                <div id="switches<?php echo $i; ?>" style="height: 30vh;"></div>
            </div>
            <div class="col-md-1" id="x<?php echo $i; ?>">
            </div>
        <?php } ?>
    </div>
</div>

<script type='text/javascript'>

    var amount = <?php echo $amount; ?>;
    var interval = <?php echo $interval; ?>;
    var speed = Math.sqrt(2000 / interval);

    <?php
    $string = "[";
    for ($i = 0; $i < 14; $i++) {
        $string .= "[" . rand(-100, 100) . "," . rand(-100, 100) . "," . rand(-100, 100) . "]";
        if ($i < 13) {
            $string .= ",";
        }
    }
    $string .= "]";
    echo "console.log('$string')";
    ?>


    //var object = data[0];
    var examples = [
        [[-38, -10, -63], [32, -48, -78], [91, 85, -48], [60, 84, 95], [-41, -39, 65], [-96, 38, -59], [-50, 32, 81], [-63, 78, 5], [-92, -91, -56], [90, 7, 0], [-52, 69, 91], [-14, 0, -58], [8, -9, -74], [61, -50, -90]],
        [[-67, -85, -96], [89, 6, 13], [-59, -19, -22], [-76, 11, -44], [84, -60, 95], [44, -64, -10], [9, -64, -28], [-2, -1, 63], [36, 80, 33], [-24, 93, 69], [11, -75, 85], [16, -87, -9], [-72, -46, 73], [6, -21, -17]],
        [[-54, -46, 45], [-24, 40, -86], [-95, 84, 9], [65, -91, 95], [-89, 11, -81], [17, 8, -94], [83, -38, -86], [76, -18, 12], [46, 100, -88], [14, 100, -18], [-73, -55, 37], [72, 22, -24], [87, 27, -40], [-4, -8, -30]],
        [[74, 11, -35], [13, -97, 54], [-9, -64, -87], [-36, 8, 1], [32, 8, -66], [48, 5, -67], [44, 39, -23], [-11, -93, -12], [32, 58, 100], [-58, 51, -32], [-59, 24, 80], [6, -63, 84], [-40, 28, -81], [-27, 93, 28]],
        [[91, -44, -63], [-1, 91, -66], [-60, 25, 90], [47, -84, 15], [32, -49, 47], [-64, -68, 76], [34, -49, -44], [51, 38, 90], [71, 37, 28], [-66, -79, -46], [-59, -89, 11], [-22, 10, 2], [12, 51, -74], [2, -3, -58]]
    ];

    var index;
    var verbose = false;

    var ssv_z = [];
    var ssv_ztv = [];
    var ssv_iterations = [];
    var ssv_switches = [];
    var dsv_z = [];
    var dsv_ztv = [];
    var dsv_iterations = [];
    var dsv_switches = [];
    var tsv_z = [];
    var tsv_ztv = [];
    var tsv_iterations = [];
    var tsv_switches = [];
    var psv_z = [];
    var psv_ztv = [];
    var psv_iterations = [];
    var psv_switches = [];
    var max_steps = [];
    var max_iterations = [];
    var max_switches = [];
    var min_ztv = [];
    var optimal = [];

    for (index = 0; index < amount; index++) {
        console.log("index= " + index);
        var x = clone_array(examples[index]);

        var ssv = scalable_sign_vector(x, verbose);
        ssv_z.push(ssv[0]);
        console.log("ssv: Z=" + ssv[0][ssv[0].length - 1]);
        console.log("XT*Z=" + euclid_norm(mult(transpose(x), ssv[0][ssv[0].length - 1])));
        ssv_ztv.push(ssv[1]);
        ssv_iterations.push(ssv[2]);
        ssv_switches.push(ssv[3]);
        var dsv = double_scalable_sign_vector(x, verbose);
        dsv_z.push(dsv[0]);
        console.log("dsv: Z=" + dsv[0][dsv[0].length - 1]);
        console.log("XT*Z=" + euclid_norm(mult(transpose(x), dsv[0][dsv[0].length - 1])));
        dsv_ztv.push(dsv[1]);
        dsv_iterations.push(dsv[2]);
        dsv_switches.push(dsv[3]);
        var tsv = triple_scalable_sign_vector(x, verbose);
        tsv_z.push(tsv[0]);
        console.log("tsv: Z=" + tsv[0][tsv[0].length - 1]);
        console.log("XT*Z=" + euclid_norm(mult(transpose(x), tsv[0][tsv[0].length - 1])));
        tsv_ztv.push(tsv[1]);
        tsv_iterations.push(tsv[2]);
        tsv_switches.push(tsv[3]);
        var psv = positive_scalable_sign_vector(x, verbose);
        psv_z.push(psv[0]);
        psv_ztv.push(psv[1]);
        console.log("XT*Z optimal=" + euclid_norm(mult(transpose(x), brute_force_sign_vector(x, verbose))));
        optimal.push(brute_force_sign_vector(x, verbose));
        min_ztv.push(Math.min(ssv_ztv[index][0], dsv_ztv[index][0], tsv_ztv[index][0], psv_ztv[index][0]));
        max_steps.push(Math.max(ssv_ztv[index].length, dsv_ztv[index].length, tsv_ztv[index].length, psv_ztv[index].length));
        max_iterations.push(
            Math.max(
                ssv_iterations[index][ssv_iterations[index].length - 1],
                dsv_iterations[index][dsv_iterations[index].length - 1],
                tsv_iterations[index][tsv_iterations[index].length - 1]
            ) + 1
        );
        max_switches.push(
            Math.max(
                ssv_switches[index][ssv_switches[index].length - 1],
                dsv_switches[index][dsv_switches[index].length - 1],
                tsv_switches[index][tsv_switches[index].length - 1]
            ) + 1
        );

        $('#z-ssv-chart' + index).highcharts({
            chart: {type: 'bar', animation: false},
            title: {text: null},
            yAxis: {
                reversed: true, tickInterval: 1, max: 1, min: -1,
                title: {
                    text: 'SSV',
                    style: {
                        color: '#7cb5ec'
                    }
                }
            },
            xAxis: {
                lineWidth: 0,
                minorGridLineWidth: 0,
                lineColor: 'transparent',
                labels: {enabled: false},
                minorTickLength: 0,
                tickLength: 0
            },
            tooltip: {
                formatter: function () {
                    return this.series.name + '[' + this.x + ']: ' + this.y;
                }
            },
            legend: {enabled: false},
            exporting: {enabled: false},
            series: [{
                name: 'Z',
                data: null
            }],
            plotOptions: {bar: {animation: false, zones: [{value: 0, color: 'red'}, {color: 'blue'}]}},
            credits: {enabled: false}
        });

        $('#z-dsv-chart' + index).highcharts({
            chart: {type: 'bar', animation: false},
            title: {text: null},
            yAxis: {
                reversed: true, tickInterval: 1, max: 1, min: -1,
                title: {
                    text: 'DSV',
                    style: {
                        color: '#434348'
                    }
                }
            },
            xAxis: {
                lineWidth: 0,
                minorGridLineWidth: 0,
                lineColor: 'transparent',
                labels: {enabled: false},
                minorTickLength: 0,
                tickLength: 0
            },
            tooltip: {
                formatter: function () {
                    return this.series.name + '[' + this.x + ']: ' + this.y;
                }
            },
            legend: {enabled: false},
            exporting: {enabled: false},
            series: [{
                name: 'Z',
                data: null
            }],
            plotOptions: {bar: {animation: false, zones: [{value: 0, color: 'red'}, {color: 'blue'}]}},
            credits: {enabled: false}
        });

        $('#z-tsv-chart' + index).highcharts({
            chart: {type: 'bar', animation: false},
            title: {text: null},
            yAxis: {
                reversed: true, tickInterval: 1, max: 1, min: -1,
                title: {
                    text: 'TSV',
                    style: {
                        color: '#90ed7d'
                    }
                }
            },
            xAxis: {
                lineWidth: 0,
                minorGridLineWidth: 0,
                lineColor: 'transparent',
                labels: {enabled: false},
                minorTickLength: 0,
                tickLength: 0
            },
            tooltip: {
                formatter: function () {
                    return this.series.name + '[' + this.x + ']: ' + this.y;
                }
            },
            legend: {enabled: false},
            exporting: {enabled: false},
            series: [{
                name: 'Z',
                data: null
            }],
            plotOptions: {bar: {animation: false, zones: [{value: 0, color: 'red'}, {color: 'blue'}]}},
            credits: {enabled: false}
        });

        $('#z-psv-chart' + index).highcharts({
            chart: {type: 'bar', animation: false},
            title: {text: null},
            yAxis: {
                reversed: true, tickInterval: 1, max: 1, min: -1,
                title: {
                    text: 'PSV',
                    style: {
                        color: '#f7a35c'
                    }
                }
            },
            xAxis: {
                lineWidth: 0,
                minorGridLineWidth: 0,
                lineColor: 'transparent',
                labels: {enabled: false},
                minorTickLength: 0,
                tickLength: 0
            },
            tooltip: {
                formatter: function () {
                    return this.series.name + '[' + this.x + ']: ' + this.y;
                }
            },
            legend: {enabled: false},
            exporting: {enabled: false},
            series: [{
                name: 'Z',
                data: null
            }],
            plotOptions: {bar: {animation: false, zones: [{value: 0, color: 'red'}, {color: 'blue'}]}},
            credits: {enabled: false}
        });

        $('#z-optimal-chart' + index).highcharts({
            chart: {type: 'bar', animation: false},
            title: {text: null},
            yAxis: {
                reversed: true, tickInterval: 1, max: 1, min: -1,
                title: {
                    text: 'Maximizing',
                    style: {
                        color: '#ff0000'
                    }
                }
            },
            xAxis: {
                lineWidth: 0,
                minorGridLineWidth: 0,
                lineColor: 'transparent',
                labels: {enabled: false},
                minorTickLength: 0,
                tickLength: 0
            },
            tooltip: {
                formatter: function () {
                    return this.series.name + '[' + this.x + ']: ' + this.y;
                }
            },
            legend: {enabled: false},
            exporting: {enabled: false},
            series: [{
                name: 'Maximizing',
                data: optimal[index]
            }],
            plotOptions: {bar: {animation: false, zones: [{value: 0, color: 'red'}, {color: 'blue'}]}},
            credits: {enabled: false}
        });

        $('#local_ztv-chart' + index).highcharts({
            chart: {
                type: 'line',
                animation: false
            },
            title: {
                text: null
            },
            xAxis: {
                min: 0,
                max: max_steps[index],
                title: {
                    text: 'iteration'
                },
                tickLength: 1
            },
            yAxis: {
                min: -0.2 * ssv_ztv[index][ssv_ztv[index].length - 1],
                max: 1.1 * ssv_ztv[index][ssv_ztv[index].length - 1],
                title: {
                    enabled: false
                },
                plotLines: [{
                    color: '#FF0000',
                    width: 2,
                    value: ssv_ztv[index][ssv_ztv[index].length - 1]
                }]
            },
            tooltip: {
                formatter: function () {
                    return '<b>' + this.series.name + '</b><br/>' +
                        'Step: ' + this.x + '<br/>' +
                        'ZX^T*V: ' + this.y;
                }
            },
            legend: {
                enabled: false
            },
            exporting: {
                enabled: false
            },
            series: [{
                data: null,
                name: 'SSV',
            },
                {
                    data: null,
                    name: 'DSV',
                },
                {
                    data: null,
                    name: 'TSV',
                },
                {
                    data: null,
                    name: 'PSV',
                }],
            credits: {
                enabled: false
            },
        });

        $('#iterations' + index).highcharts({
            chart: {
                type: 'column'
            },
            title: {
                text: null
            },
            yAxis: {
                min: 0,
                max: max_iterations[index],
                title: {
                    text: 'iterations'
                }
            },
            xAxis: {
                labels: {
                    enabled: false
                }
            },
            tooltip: {
                headerFormat: '<table>',
                pointFormat: '<tr><td style="color:{series.color};padding:0">{series.name}: </td>' +
                    '<td style="padding:0; text-align: right;">{point.y}</td></tr>',
                footerFormat: '</table>',
                shared: true,
                useHTML: true
            },
            plotOptions: {
                column: {
                    pointPadding: 0.2,
                    borderWidth: 0
                }
            },
            exporting: {
                enabled: false
            },
            credits: {
                enabled: false
            },
            series: [{
                name: 'SSV',
                data: null
            }, {
                name: 'DSV',
                data: null
            }, {
                name: 'TSV',
                data: null
            }]
        });

        $('#switches' + index).highcharts({
            chart: {
                type: 'column'
            },
            title: {
                text: null
            },
            yAxis: {
                min: 0,
                max: max_switches[index],
                title: {
                    text: 'sign switches'
                },
                plotLines: [{
                    color: '#FF0000',
                    width: 1,
                    value: ssv_switches[index].length - 1
                }]
            },
            xAxis: {
                labels: {
                    enabled: false
                }
            },
            tooltip: {
                headerFormat: '<table>',
                pointFormat: '<tr><td style="color:{series.color};padding:0">{series.name}: </td>' +
                    '<td style="padding:0; text-align: right;">{point.y}</td></tr>',
                footerFormat: '</table>',
                shared: true,
                useHTML: true
            },
            plotOptions: {
                column: {
                    pointPadding: 0.2,
                    borderWidth: 0
                }
            },
            exporting: {
                enabled: false
            },
            credits: {
                enabled: false
            },
            series: [{
                name: 'SSV',
                data: null
            }, {
                name: 'DSV',
                data: null
            }, {
                name: 'TSV',
                data: null
            }]
        });
    }

    <?php
    for ($i = 0; $i < $amount; $i++){
    ?>

    $('#x<?php echo $i; ?>').html('<h4>X</h4><table>' + print_table(examples[<?php echo $i; ?>]) + "</table>");


    var timer<?php echo $i; ?> = null,
        running<?php echo $i; ?> = false,
        i<?php echo $i; ?> = 0;

    $('#pause<?php echo $i; ?>').click(function () {
        $('#play<?php echo $i; ?>').show();
        $('#pause<?php echo $i; ?>').hide();
        clearTimeout(timer<?php echo $i; ?>);
        timer<?php echo $i; ?> = null;
        running<?php echo $i; ?> = false;
    });

    $('#reset<?php echo $i; ?>').click(function () {
        clearTimeout(timer<?php echo $i; ?>);
        $('#play<?php echo $i; ?>').show();
        $('#pause<?php echo $i; ?>').hide();
        $('#counter<?php echo $i; ?>').html("Counter: 0");
        timer<?php echo $i; ?> = null;
        running<?php echo $i; ?> = false;
        i<?php echo $i; ?>= 0;
        var index = <?php echo $i; ?>;
        $('#z-ssv-chart' + index).highcharts().series[0].setData(null);
        $('#z-dsv-chart' + index).highcharts().series[0].setData(null);
        $('#z-tsv-chart' + index).highcharts().series[0].setData(null);
        $('#z-psv-chart' + index).highcharts().series[0].setData(null);
        $('#local_ztv-chart' + index).highcharts().series[0].setData(null);
        $('#local_ztv-chart' + index).highcharts().series[1].setData(null);
        $('#local_ztv-chart' + index).highcharts().series[2].setData(null);
        $('#local_ztv-chart' + index).highcharts().series[3].setData(null);
        $('#iterations' + index).highcharts().series[0].setData([null]);
        $('#iterations' + index).highcharts().series[1].setData([null]);
        $('#iterations' + index).highcharts().series[2].setData([null]);
        $('#switches' + index).highcharts().series[0].setData([null]);
        $('#switches' + index).highcharts().series[1].setData([null]);
        $('#switches' + index).highcharts().series[2].setData([null]);
    });

    $('#play<?php echo $i; ?>').click(function () {
        var index = <?php echo $i; ?>;
        if (i<?php echo $i; ?>>= max_steps[index]) {
            i<?php echo $i; ?> = 0;
            $('#z-ssv-chart' + index).css({opacity: 1});
            $('#z-dsv-chart' + index).css({opacity: 1});
            $('#z-tsv-chart' + index).css({opacity: 1});
            $('#z-psv-chart' + index).css({opacity: 1});
            $('#z-ssv-chart' + index).highcharts().series[0].setData(null);
            $('#z-dsv-chart' + index).highcharts().series[0].setData(null);
            $('#z-tsv-chart' + index).highcharts().series[0].setData(null);
            $('#z-psv-chart' + index).highcharts().series[0].setData(null);
            $('#local_ztv-chart' + index).highcharts().series[0].setData(null);
            $('#local_ztv-chart' + index).highcharts().series[1].setData(null);
            $('#local_ztv-chart' + index).highcharts().series[2].setData(null);
            $('#local_ztv-chart' + index).highcharts().series[3].setData(null);
            $('#iterations' + index).highcharts().series[0].setData([null]);
            $('#iterations' + index).highcharts().series[1].setData([null]);
            $('#iterations' + index).highcharts().series[2].setData([null]);
            $('#switches' + index).highcharts().series[0].setData([null]);
            $('#switches' + index).highcharts().series[1].setData([null]);
            $('#switches' + index).highcharts().series[2].setData([null]);
        }

        $('#pause<?php echo $i; ?>').show();
        $('#play<?php echo $i; ?>').hide();
        if (!running<?php echo $i; ?>) {
            running<?php echo $i; ?> = true;

            (function next() {
                timer<?php echo $i; ?> = setTimeout(function () {
                    if (i<?php echo $i; ?>>= max_steps[index]) {
                        $('#play<?php echo $i; ?>').show();
                        $('#pause<?php echo $i; ?>').hide();
                        clearTimeout(timer<?php echo $i; ?>);
                        timer<?php echo $i; ?> = null;
                        running<?php echo $i; ?> = false;
                    }
                    else {
                        if (i<?php echo $i; ?>< ssv_z[index].length) {
                            $('#z-ssv-chart' + index).highcharts().series[0].setData(ssv_z[index][i<?php echo $i; ?>].slice());
                            $('#local_ztv-chart' + index).highcharts().series[0].addPoint(ssv_ztv[index][i<?php echo $i; ?>]);
                            $('#iterations' + index).highcharts().series[0].setData([ssv_iterations[index][i<?php echo $i; ?>]]);
                            $('#switches' + index).highcharts().series[0].setData([ssv_switches[index][i<?php echo $i; ?>]]);
                            if (i<?php echo $i; ?>+ 1 == ssv_z[index].length) {
                                $('#z-ssv-chart' + index).css({opacity: 0.5});
                            }
                        }
                        if (i<?php echo $i; ?>< dsv_z[index].length) {
                            $('#z-dsv-chart' + index).highcharts().series[0].setData(dsv_z[index][i<?php echo $i; ?>].slice());
                            $('#local_ztv-chart' + index).highcharts().series[1].addPoint(dsv_ztv[index][i<?php echo $i; ?>]);
                            if (i<?php echo $i; ?>< 31) {
                                $('#iterations' + index).highcharts().series[1].setData([dsv_iterations[index][i<?php echo $i; ?>]]);
                                $('#switches' + index).highcharts().series[1].setData([dsv_switches[index][i<?php echo $i; ?>]]);
                            }
                            else {
                                $('#iterations' + index).highcharts().series[1].setData([null]);
                                $('#switches' + index).highcharts().series[1].setData([null]);
                            }
                            if (i<?php echo $i; ?>+ 1 == dsv_z[index].length) {
                                $('#z-dsv-chart' + index).css({opacity: 0.5});
                            }
                        }
                        if (i<?php echo $i; ?>< tsv_z[index].length) {
                            $('#z-tsv-chart' + index).highcharts().series[0].setData(tsv_z[index][i<?php echo $i; ?>].slice());
                            $('#local_ztv-chart' + index).highcharts().series[2].addPoint(tsv_ztv[index][i<?php echo $i; ?>]);
                            $('#iterations' + index).highcharts().series[2].setData([tsv_iterations[index][i<?php echo $i; ?>]]);
                            $('#switches' + index).highcharts().series[2].setData([tsv_switches[index][i<?php echo $i; ?>]]);
                            if (i<?php echo $i; ?>+ 1 == tsv_z[index].length) {
                                $('#z-tsv-chart' + index).css({opacity: 0.5});
                            }
                        }
                        if (i<?php echo $i; ?>< psv_z[index].length) {
                            $('#z-psv-chart' + index).highcharts().series[0].setData(psv_z[index][i<?php echo $i; ?>].slice());
                            $('#local_ztv-chart' + index).highcharts().series[3].addPoint(psv_ztv[index][i<?php echo $i; ?>]);
                            if (i<?php echo $i; ?>+ 1 == psv_z[index].length) {
                                $('#z-psv-chart' + index).css({opacity: 0.5});
                            }
                        }
                        $('#counter').html("Counter: " + i<?php echo $i; ?>);
                        i<?php echo $i; ?>++;
                        next();
                    }
                }, interval);
            })();
        }
    });
    <?php } ?>
    //});

    //////////////////////////////////////////////////////////////////////////////
    //                                                                          //
    //                    SIGN VECTOR COMPUTATION STRATEGIES                    //
    //                                                                          //
    //////////////////////////////////////////////////////////////////////////////

    function scalable_sign_vector(x, verbose) {
        var m = x.length; // number of rows
        var n = x[0].length; // number of columns

        var z_values = [];
        var ztv_values = [];
        var iteration_values = [];
        var switch_values = [];

        var z = [];
        for (i = 0; i < m; i++) {
            z.push([1]);
        }

        var s = init_array(n, 1); //(double[n][1])
        var v = init_array(m, 1); //(double[m][1])

        var switches = 0;
        var pos = 0;
        do {
            // change sign
            if (pos == 0) {
                for (i = 0; i < m; i++) {
                    z[i][0] = 1;
                }
            }
            else {
                var tmp = z[pos - 1][0];
                z[pos - 1][0] = tmp * (-1);
                switches++;
            }

            for (y = 0; y < n; y++) {
                s[y][0] = 0;
            }
            for (var i = 0; i < m; i++) {
                s = add(s, scalar_mult(transpose(extract_row(x, i)), z[i][0]));
            }

            for (var i = 0; i < m; i++) {
                var tmp1 = get_value(mult(extract_row(x, i), s));
                var tmp2 = get_value(mult(extract_row(x, i), transpose(extract_row(x, i))));
                v[i][0] = (tmp1 - z[i][0] * tmp2);
            }
            if (verbose) print_array(z, "Z");


            // search next element
            val = 0;
            pos = 0;
            for (var i = 0; i < m; i++) {
                if ((z[i][0] * v[i][0]) < 0) {
                    if (Math.abs(v[i][0]) > val) {
                        val = Math.abs(v[i][0]);
                        pos = i + 1;
                    }
                }
            }
            z_values.push(clone_array(z));
            var ztv = 0;
            for (var y = 0; y < m; y++) {
                ztv += z[y] * v[y];
            }
            ztv_values.push(ztv);
            var iterations = z_values.length - 1;
            iteration_values.push(iterations);
            switch_values.push(switches);
        } while (pos != 0);

        return [z_values, ztv_values, iteration_values, switch_values];
    }


    function positive_scalable_sign_vector(x, verbose) {
        var m = x.length; // number of rows
        var n = x[0].length; // number of columns

        var z_values = [];
        var ztv_values = [];
        var iteration_values = [];
        var switch_values = [];
        var z = [];
        for (i = 0; i < m; i++) {
            z.push([1]);
        }

        var s = init_array(n, 1); //(double[n][1])
        var v = init_array(m, 1); //(double[m][1])

        var switches = 0;
        var pos = 0;
        do {
            // change sign
            if (pos == 0) {
                for (i = 0; i < m; i++) {
                    z[i][0] = 1;
                }
            }
            else {
                var tmp = z[pos - 1][0];
                z[pos - 1][0] = tmp * (-1);
                switches++;
            }

            for (y = 0; y < n; y++) {
                s[y][0] = 0;
            }
            for (var i = 0; i < m; i++) {
                s = add(s, scalar_mult(transpose(extract_row(x, i)), z[i][0]));
            }

            for (var i = 0; i < m; i++) {
                var tmp1 = get_value(mult(extract_row(x, i), s));
                var tmp2 = get_value(mult(extract_row(x, i), transpose(extract_row(x, i))));
                v[i][0] = (tmp1 - z[i][0] * tmp2);
            }
            if (verbose) print_array(z, "Z");

            // search next element
            val = 0;
            pos = 0;
            for (var i = 0; i < m; i++) {
                if ((z[i][0] * v[i][0]) > 0) {
                    if (Math.abs(v[i][0]) > val) {
                        val = Math.abs(v[i][0]);
                        pos = i + 1;
                    }
                }
            }
            z_values.push(clone_array(z));
            var ztv = 0;
            for (var y = 0; y < m; y++) {
                ztv += z[y] * v[y];
            }
            ztv_values.push(ztv);

        } while (pos != 0);
        var iterations = z_values.length;

        return [z_values, ztv_values, iterations, switches];
    }


    function double_scalable_sign_vector(x, verbose) {
        var m = x.length; // number of rows
        var n = x[0].length; // number of columns

        var z_values = [];
        var ztv_values = [];
        var iteration_values = [];
        var switch_values = [];

        var z = [];
        for (i = 0; i < m; i++) {
            z.push([1]);
        }

        var s = init_array(n, 1); //(double[n][1])
        var v = init_array(m, 1); //(double[m][1])

        var switches = 0;
        var posA = 0;
        var posB = 0;
        var counter = 0;
        do {
            counter++;
            // change sign
            if (posA != 0) {
                var tmp = z[posA - 1][0];
                z[posA - 1][0] = tmp * (-1);
                switches++;
            }
            if (posB != 0) {
                var tmp = z[posB - 1][0];
                z[posB - 1][0] = tmp * (-1);
                switches++;
            }

            for (y = 0; y < n; y++) {
                s[y][0] = 0;
            }
            for (var i = 0; i < m; i++) {
                s = add(s, scalar_mult(transpose(extract_row(x, i)), z[i][0]));
            }

            //if (verbose) print_array(s,"S");

            for (var i = 0; i < m; i++) {
                var tmp1 = get_value(mult(extract_row(x, i), s));
                var tmp2 = get_value(mult(extract_row(x, i), transpose(extract_row(x, i))));
                v[i][0] = (tmp1 - z[i][0] * tmp2);
                //console.log("v: "+v[i][0]);
            }


            // search next element
            var valA = 0;
            var valB = 0;
            posA = 0;
            posB = 0;
            for (var i = 0; i < m; i++) {
                if ((z[i][0] * v[i][0]) < 0) {
                    if (Math.abs(v[i][0]) > valA && Math.abs(v[i][0]) > valB) {
                        valB = valA;
                        valA = Math.abs(v[i][0]);
                        posB = posA;
                        posA = i + 1;
                    }
                    else if (Math.abs(v[i][0]) > valB) {
                        valB = Math.abs(v[i][0]);
                        posB = i + 1;
                    }
                }
            }
            z_values.push(clone_array(z));
            var ztv = 0;
            for (var y = 0; y < m; y++) {
                ztv += z[y] * v[y];
            }
            ztv_values.push(ztv);

            var iterations = z_values.length - 1;
            iteration_values.push(iterations);
            switch_values.push(switches);

            if (verbose) print_array(z, "Z");
        } while (!((posA == 0 && posB == 0) || counter > 32));

        return [z_values, ztv_values, iteration_values, switch_values];
    }


    function triple_scalable_sign_vector(x, verbose) {
        var m = x.length; // number of rows
        var n = x[0].length; // number of columns

        var z_values = [];
        var ztv_values = [];
        var iteration_values = [];
        var switch_values = [];

        var z = [];
        for (i = 0; i < m; i++) {
            z.push([1]);
        }

        var s = init_array(n, 1); //(double[n][1])
        var v = init_array(m, 1); //(double[m][1])

        var iterations = 0;
        var switches = 0;
        var posA = 0;
        var posB = 0;
        var posC = 0;
        var counter = 0;
        do {
            iterations++;
            counter++;
            // change sign
            if (posA != 0) {
                var tmp = z[posA - 1][0];
                z[posA - 1][0] = tmp * (-1);
                switches++;
            }
            if (posB != 0) {
                var tmp = z[posB - 1][0];
                z[posB - 1][0] = tmp * (-1);
                switches++;
            }
            if (posC != 0) {
                var tmp = z[posC - 1][0];
                z[posC - 1][0] = tmp * (-1);
                switches++;
            }
            for (y = 0; y < n; y++) {
                s[y][0] = 0;
            }
            for (var i = 0; i < m; i++) {
                s = add(s, scalar_mult(transpose(extract_row(x, i)), z[i][0]));
            }

            for (var i = 0; i < m; i++) {
                var tmp1 = get_value(mult(extract_row(x, i), s));
                var tmp2 = get_value(mult(extract_row(x, i), transpose(extract_row(x, i))));
                v[i][0] = (tmp1 - z[i][0] * tmp2);
            }

            // search next element
            var valA = 0;
            var valB = 0;
            var valC = 0;
            posA = 0;
            posB = 0;
            posC = 0;
            for (var i = 0; i < m; i++) {
                if ((z[i][0] * v[i][0]) < 0) {
                    if (Math.abs(v[i][0]) > valA) {
                        valC = valB;
                        valB = valA;
                        valA = Math.abs(v[i][0]);
                        posC = posB;
                        posB = posA;
                        posA = i + 1;
                    }
                    else if (Math.abs(v[i][0]) > valB) {
                        valC = valB;
                        valB = Math.abs(v[i][0]);
                        posC = posB;
                        posB = i + 1;
                    }
                    else if (Math.abs(v[i][0]) > valC) {
                        valC = Math.abs(v[i][0]);
                        posC = i + 1;
                    }
                }
            }
            z_values.push(clone_array(z));
            var ztv = 0;
            for (var y = 0; y < m; y++) {
                ztv += z[y] * v[y];
            }
            ztv_values.push(ztv);
            if (verbose) print_array(z, "Z");

            var iterations = z_values.length - 1;
            iteration_values.push(iterations);
            switch_values.push(switches);

        } while (!((posA == 0 && posB == 0 && posC == 0) || counter > 32));

        return [z_values, ztv_values, iteration_values, switch_values];
    }

    function brute_force_sign_vector(x) {
        var m = x.length; // number of rows
        var n = x[0].length; // number of columns
        var max_sum = 0;
        var max_sign_vector;

        var z = init_array(m, 1); //(double[m][1])

        var total = Math.pow(2, m);
        for (var i = 0; i < total; i++) {
            for (var j = 0; j < m; j++) {
                z[j][0] = 1;
            }
            var binary = (i).toString(2);
            while (binary.length < m) {
                binary = "0" + binary;
            }
            for (var j = 0; j < binary.length; j++) {
                if (binary[j] == 1) {
                    z[j][0] = -1;
                }
            }
            var prod = mult(transpose(x), z);

            norm = euclid_norm(prod);
            if (norm > max_sum) {
                max_sum = norm;
                max_sign_vector = clone_array(z);
            }
        }
        return max_sign_vector;
    }

    //////////////////////////////////////////////////////////////////////////////
    //                                                                          //
    //                         HELPERS AND INITIALIZERS                         //
    //                                                                          //
    //////////////////////////////////////////////////////////////////////////////

    function print_array(array, name) {
        var rows = array.length;
        var columns = array[0].length;

        $('#here').append(name + ":");
        $('#here').append("<table>");
        for (row_index = 0; row_index < rows; row_index++) {
            $('#here').append("<tr>");
            for (column_index = 0; column_index < columns; column_index++) {
                $('#here').append("<td>" + array[row_index][column_index] + "</td>");
            }
            $('#here').append("</tr>");
        }
        $('#here').append("</table>");
    }

    function print_table(array) {
        var rows = array.length;
        var columns = array[0].length;

        var out = "";
        for (row_index = 0; row_index < rows; row_index++) {
            out += "<tr>";
            for (column_index = 0; column_index < columns; column_index++) {
                out += "<td style='padding-right: 10px; padding-top: 5px; text-align: right;'>" + array[row_index][column_index] + "</td>";
            }
            out += "</tr>";
        }
        return out;
    }

</script>

<?php include '../footer.php'; ?>
