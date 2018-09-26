<?php
$page_title = "Static example";
include '../header.php';

$interval = @$_GET['interval'];

if (!isset($interval)) {
    $interval = 40;
}
?>
    <div class="container"
         style="position: fixed; z-index: 1000; margin-top: -20px; padding-top: 20px; padding-bottom: 10px; width: 100%; background: #fff;">
        <div class="text-center" style="height: 4vh; float: left">
            <button id="play" type="button" class="btn btn-primary">
                <span class="glyphicon glyphicon-play" aria-hidden="true"></span>
            </button>
            <button style="display:none" id="pause" type="button" class="btn btn-info">
                <span class="glyphicon glyphicon-pause" aria-hidden="true"></span>
            </button>
            <button id="reset" type="button" class="btn btn-default">
                <span class="glyphicon glyphicon-repeat" aria-hidden="true"></span>
            </button>
            <small id="counter">Iteration: - | Frobenius distance: -</small>
        </div>
    </div>
    <div class="container">
        <div class="col-md-12">
            <div class="page-header">
                <br/>
                <h2>Recovery
                    <small>using synthetic data</small>
                </h2>
            </div>
        </div>
        <div class="col-md-12">
            <div style="width: 72%; float: left">
                <h4>Recovery progress
                    <button id="x-show" type="button" class="btn btn-default btn-xs">
                        <span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span> show details
                    </button>
                    <button style="display: none;" id="x-hide" type="button" class="btn btn-default btn-xs">
                        <span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span> hide details
                    </button>
                </h4>
                <div id="x-details" style="display: none;">
                    <p>
                    <ul>
                        <li>Showing recovery progress of matrix X as a chart (left) and in values (right)</li>
                        <li>Use the controls on the top to start, pause and reset the recovery progress</li>
                        <li>All missing values are in the base series</li>
                        <li>Values and chart of X are updated after each iteration of the recovery algorithm</li>
                        <li>The recovery terminates when Frobenius distance drops below the threshold</li>
                        <li>Unhide iteration progress for more details about the each iteration of the recovery algorithm</li>
                    </ul>
                    </p>
                </div>
                <div id="x-chart" style="height: 60vh; width: 100%;"></div>
                <h4>Frobenius distance
                    <button id="frob-show" type="button" class="btn btn-default btn-xs">
                        <span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span> show details
                    </button>
                    <button style="display: none;" id="frob-hide" type="button" class="btn btn-default btn-xs">
                        <span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span> hide details
                    </button>
                </h4>
                <div id="frob-details" style="display: none;">
                    <p>
                    <ul>
                        <li>Showing calculated Frobenius distance between X<sub>i-1</sub> and X<sub>i</sub> after each
                            iteration i
                        </li>
                        <li>The recovery progress runs until the Frobenius distance drops below the threshold epsilon =
                            0.01
                        </li>
                    </ul>
                    </p>
                </div>
            </div>

            <div id="x-print" style="width: 22%; float: right;"></div>
        </div>
        <div class="col-md-12">
            <div id="frobenius-chart" style="height: 30vh; width: 100%;"></div>
        </div>
        <div class="col-md-12">
            <h3>Sign Vector Computation
                <button id="ip-show" type="button" class="btn btn-default btn-xs">
                    <span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span> show
                </button>
                <button style="display: none;" id="ip-hide" type="button" class="btn btn-default btn-xs">
                    <span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span> hide
                </button>
            </h3>
        </div>
        <div id="ip-details" style="display: none;">
            <div class="col-md-12">
            </div>
            <div class="col-md-8">
                <h4>
                    <button id="sv-show" type="button" class="btn btn-default btn-xs">
                        <span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span> show details
                    </button>
                    <button style="display: none;" id="sv-hide" type="button" class="btn btn-default btn-xs">
                        <span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span> hide details
                    </button>
                </h4>
                <div id="sv-details" style="display: none;">
                    <p>
                    <ul>
                        <li>Showing computation of the maximizing sign vectors for the current iteration
                        </li>
                        <li>Notice how the maximizing sign vectors of the previous iteration are used as
                            initialization
                        </li>
                    </ul>
                    </p>

                </div>
                <div style="height: 60vh; width: 25%; float: left; text-align: center;">
                    <div id="z1-chart" style="height: 57vh;"></div>
                    <b style="color: #7cb5ec;">Z<sub>1</sub></b>
                </div>
                <div style="height: 60vh; width: 25%; float: left; text-align: center;">
                    <div id="z2-chart" style="height: 57vh;"></div>
                    <b style="color: #434348;">Z<sub>2</sub></b>
                </div>
                <div style="height: 60vh; width: 25%; float: left; text-align: center;">
                    <div id="z3-chart" style="height: 57vh;"></div>
                    <b style="color: #90ed7d;">Z<sub>3</sub></b>
                </div>
                <div style="height: 60vh; width: 25%; float: left; text-align: center;">
                    <div id="z4-chart" style="height: 57vh;"></div>
                    <b style="color: #f7a35c;">Z<sub>4</sub></b>
                </div>
            </div>
            <div class="col-md-4" style="display: none">
                <h4>Z<sup>T</sup>*V
                    <button id="ztv-show" type="button" class="btn btn-default btn-xs">
                        <span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span> show details
                    </button>
                    <button style="display: none;" id="ztv-hide" type="button" class="btn btn-default btn-xs">
                        <span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span> hide details
                    </button>
                </h4>
                <div id="ztv-details" style="display: none;">
                    <p>
                    <ul>
                        <li>Showing the product Z<sup>T</sup>*V of each sign vector as it maximizes through the SSV</li>
                    </ul>
                    </p>
                </div>
                <div id="local_ztv-chart" style="height: 60vh;"></div>
            </div>
            <div class="col-md-4">
                <h4>Log</h4>
                <div id="log" style="height: 60vh; width: 100%; overflow: auto; font-size: 12px;"></div>
            </div>
        </div>
    </div>

    <script type='text/javascript'>

        var interval = <?php echo $interval; ?>;

        function print_table(array) {
            var rows = array.length;
            var columns = array[0].length;

            var out = "<table>";
            out += "<tr><th style='color: #ff0000;'>Base</th><th style='color: #058DC7;'>Ref<sub>1</sub></th><th style='color: #50B432;'>Ref<sub>2</sub></th><th style='color: #DDDF00;'>Ref<sub>3</sub></th></tr>";
            for (row_index = 0; row_index < rows; row_index++) {
                out += "<tr>";
                for (column_index = 0; column_index < columns; column_index++) {
                    if (column_index === 0) {
                        out += "<td style='padding-right: 10px; padding-top: 5px; text-align: right; color: red'>";
                    }
                    else if (column_index === 1) {
                        out += "<td style='padding-right: 10px; padding-top: 5px; text-align: right; color: #058DC7;'>";
                    }
                    else if (column_index === 2) {
                        out += "<td style='padding-right: 10px; padding-top: 5px; text-align: right; color: #50B432;'>";
                    }
                    else if (column_index === 3) {
                        out += "<td style='padding-right: 10px; padding-top: 5px; text-align: right; color: #DDDF00;'>";
                    }
                    if (array[row_index][column_index] != null) {
                        out += (Math.round(array[row_index][column_index] * 100) / 100).toFixed(2) + "</td>";
                    }
                    else {
                        out += '-</td>';
                    }
                }
                out += "</tr>";
            }
            out += "</table>";
            return out;
        }

        // Matrix transposition
        function transpose(array) {
            let rows = array.length,
                cols = array[0].length;

            var tp = [];
            for (i = 0; i < cols; i++) {
                var tmp_array = [];
                for (j = 0; j < rows; j++) {
                    tmp_array.push(array[j][i]);
                }
                tp.push(tmp_array);
            }
            return tp;
        }

        $.getJSON('static_query.php?callback=?', function (data) {

            var cd_object = data[0];
            var iterations = cd_object.iterations;

            $('#x-chart').highcharts({
                colors: ['#058DC7', '#50B432', '#DDDF00', '#24CBE5', '#64E572', '#FF9655', '#FFF263', '#6AF9C4'],
                title: {
                    text: null
                },
                series: [{
                    name: 'Ref-series 1',
                    data: iterations[0].x[1].slice()
                }, {
                    name: 'Ref-series 2',
                    data: iterations[0].x[2].slice()
                }, {
                    name: 'Ref-series 3',
                    data: iterations[0].x[3].slice()
                }, {
                    name: 'Base-series',
                    data: iterations[0].x[0].slice(),
                    color: 'red'
                }, {
                    name: 'Recovered points',
                    data: null,
                    dashStyle: 'shortdot',
                    color: 'red'
                },
                    {
                        name: 'Original',
                        data: iterations[0].original.slice(),
                        color: 'red',
                        visible: false
                    }],
                tooltip: {
                    formatter: function () {
                        return this.series.name + '[' + this.x + ']: ' + this.y.toFixed(2);
                    }
                },
                exporting: {
                    enabled: false
                },
                credits: {
                    enabled: false
                },
                legend: {
                    layout: 'horizontal',
                    align: 'right',
                    enabled: true,
                    verticalAlign: 'top',
                    y: -10
                },
                yAxis: {
                    min: -100,
                    max: 100
                }
            });

            $('#z1-chart').highcharts({
                chart: {
                    type: 'bar',
                    animation: false
                },
                title: {
                    text: null
                },
                yAxis: {
                    reversed: true,
                    tickInterval: 1,
                    max: 1,
                    min: -1,
                    title: {
                        text: null
                    }
                },
                xAxis: {
                    lineWidth: 0,
                    minorGridLineWidth: 0,
                    lineColor: 'transparent',
                    labels: {
                        enabled: false
                    },
                    minorTickLength: 0,
                    tickLength: 0
                },
                tooltip: {
                    formatter: function () {
                        return this.series.name + '[' + this.x + ']: ' + this.y;
                    }
                },
                legend: {
                    enabled: false
                },
                exporting: {
                    enabled: false
                },
                series: [{
                    name: 'Z1',
                    data: null
                }],
                plotOptions: {
                    bar: {
                        animation: false,
                        zones: [{
                            value: 0,
                            color: 'red'
                        }, {
                            color: 'blue'
                        }]
                    }
                },
                credits: {
                    enabled: false
                }
            });

            $('#z2-chart').highcharts({
                chart: {
                    type: 'bar',
                    animation: false
                },
                title: {
                    text: null
                },
                yAxis: {
                    reversed: true,
                    tickInterval: 1,
                    max: 1,
                    min: -1,
                    title: {
                        text: null
                    }
                },
                xAxis: {
                    lineWidth: 0,
                    minorGridLineWidth: 0,
                    lineColor: 'transparent',
                    labels: {
                        enabled: false
                    },
                    minorTickLength: 0,
                    tickLength: 0
                },
                tooltip: {
                    formatter: function () {
                        return this.series.name + '[' + this.x + ']: ' + this.y;
                    }
                },
                legend: {
                    enabled: false
                },
                exporting: {
                    enabled: false
                },
                series: [{
                    name: 'Z2',
                    data: null
                }],
                plotOptions: {
                    bar: {
                        animation: false,
                        zones: [{
                            value: 0,
                            color: 'red'
                        }, {
                            color: 'blue'
                        }]
                    }
                },
                credits: {
                    enabled: false
                }
            });

            $('#z3-chart').highcharts({
                chart: {
                    type: 'bar',
                    animation: false
                },
                title: {
                    text: null
                },
                yAxis: {
                    reversed: true,
                    tickInterval: 1,
                    max: 1,
                    min: -1,
                    title: {
                        text: null
                    }
                },
                xAxis: {
                    lineWidth: 0,
                    minorGridLineWidth: 0,
                    lineColor: 'transparent',
                    labels: {
                        enabled: false
                    },
                    minorTickLength: 0,
                    tickLength: 0
                },
                tooltip: {
                    formatter: function () {
                        return this.series.name + '[' + this.x + ']: ' + this.y;
                    }
                },
                legend: {
                    enabled: false
                },
                exporting: {
                    enabled: false
                },
                series: [{
                    name: 'Z3',
                    data: null
                }],
                plotOptions: {
                    bar: {
                        animation: false,
                        zones: [{
                            value: 0,
                            color: 'red'
                        }, {
                            color: 'blue'
                        }]
                    }
                },
                credits: {
                    enabled: false
                }
            });

            $('#z4-chart').highcharts({
                chart: {
                    type: 'bar',
                    animation: false
                },
                title: {
                    text: null
                },
                yAxis: {
                    reversed: true,
                    tickInterval: 1,
                    max: 1,
                    min: -1,
                    title: {
                        text: null
                    }
                },
                xAxis: {
                    lineWidth: 0,
                    minorGridLineWidth: 0,
                    lineColor: 'transparent',
                    labels: {
                        enabled: false
                    },
                    minorTickLength: 0,
                    tickLength: 0
                },
                tooltip: {
                    formatter: function () {
                        return this.series.name + '[' + this.x + ']: ' + this.y;
                    }
                },
                legend: {
                    enabled: false
                },
                exporting: {
                    enabled: false
                },
                series: [{
                    name: 'Z4',
                    data: null
                }],
                plotOptions: {
                    bar: {
                        animation: false,
                        zones: [{
                            value: 0,
                            color: 'red'
                        }, {
                            color: 'blue'
                        }]
                    }
                },
                credits: {
                    enabled: false
                }
            });

            $('#frobenius-chart').highcharts({
                chart: {
                    type: 'line'
                },
                title: {
                    text: null
                },
                xAxis: {
                    min: 0,
                    tickInterval: 1,
                    title: {
                        text: 'iteration'
                    }
                },
                yAxis: {
                    min: 0.001,
                    type: 'logarithmic',
                    title: {
                        text: 'Frobenius'
                    },
                    plotLines: [{
                        color: '#FF0000',
                        width: 2,
                        value: 0.01
                    }]
                },
                tooltip: {
                    formatter: function () {
                        return 'Fnorm[X<sub>' + this.x + '</sub>-X<sub>' + (this.x - 1) + '</sub>] = ' + Highcharts.numberFormat(this.y, 2);
                    }
                },
                legend: {
                    enabled: false
                },
                exporting: {
                    enabled: false
                },
                series: [{
                    data: null
                }],
                credits: {
                    enabled: false
                },
            });

            $('#local_ztv-chart').highcharts({
                chart: {
                    type: 'line',
                    animation: false
                },
                title: {
                    text: null
                },
                xAxis: {
                    min: 0,
                    max: 14,
                    title: {
                        text: 's = step#'
                    }
                },
                yAxis: {
                    min: 0,
                    max: 1200000,
                    title: {
                        enabled: false
                    }
                },
                tooltip: {
                    formatter: function () {
                        return this.series.name + '(' + this.x + ') = ' + Highcharts.numberFormat(this.y, 0);
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
                    name: 'Z<sub>1</sub>',
                },
                    {
                        data: null,
                        name: 'Z<sub>2</sub>',
                    },
                    {
                        data: null,
                        name: 'Z<sub>3</sub>',
                    },
                    {
                        data: null,
                        name: 'Z<sub>4</sub>',
                    }
                ],
                credits: {
                    enabled: false
                },
            });

            $('#x-show').click(function () {
                $('#x-show').hide();
                $('#x-hide').show();
                $('#x-details').show();
            });
            $('#x-hide').click(function () {
                $('#x-hide').hide();
                $('#x-details').hide();
                $('#x-show').show();
            });

            $('#ztv-show').click(function () {
                $('#ztv-show').hide();
                $('#ztv-hide').show();
                $('#ztv-details').show();
            });
            $('#ztv-hide').click(function () {
                $('#ztv-hide').hide();
                $('#ztv-details').hide();
                $('#ztv-show').show();
            });

            $('#ztf-show').click(function () {
                $('#ztf-show').hide();
                $('#ztf-hide').show();
                $('#ztf-details').show();
            });
            $('#ztf-hide').click(function () {
                $('#ztf-hide').hide();
                $('#ztf-details').hide();
                $('#ztf-show').show();
            });

            $('#sv-show').click(function () {
                $('#sv-show').hide();
                $('#sv-hide').show();
                $('#sv-details').show();
            });
            $('#sv-hide').click(function () {
                $('#sv-hide').hide();
                $('#sv-details').hide();
                $('#sv-show').show();
            });

            $('#frob-show').click(function () {
                $('#frob-show').hide();
                $('#frob-hide').show();
                $('#frob-details').show();
            });
            $('#frob-hide').click(function () {
                $('#frob-hide').hide();
                $('#frob-details').hide();
                $('#frob-show').show();
            });

            $('#intro-show').click(function () {
                $('#intro-show').hide();
                $('#intro-hide').show();
                $('#intro-details').show();
            });
            $('#intro-hide').click(function () {
                $('#intro-hide').hide();
                $('#intro-details').hide();
                $('#intro-show').show();
            });


            $('#ip-show').click(function () {
                $('#ip-show').hide();
                $('#ip-hide').show();
                $('#ip-details').show();
                $('#z1-chart').highcharts().reflow();
                $('#z2-chart').highcharts().reflow();
                $('#z3-chart').highcharts().reflow();
                $('#z4-chart').highcharts().reflow();
                $('#local_ztv-chart').highcharts().reflow();
            });
            $('#ip-hide').click(function () {
                $('#ip-hide').hide();
                $('#ip-details').hide();
                $('#ip-show').show();
            });

            $('#x-print').html("<h4>X<sub>0</sub>:</h4>" + print_table(transpose(iterations[0].x)));


            var timer = null,
                running = false,
                diffCounter = 1,
                ztvCounter = 0.25,
                stepCounter = 0,
                ztvMax = 0,
                diff = 0,
                i = 0,
                prevJd = -1,
                prevId = -1,
                id;


            $('#pause').click(function () {
                $('#play').show();
                $('#pause').hide();
                clearTimeout(timer);
                timer = null;
                running = false;
                $('#log').prepend("Paused<br>");
            });

            $('#reset').click(function () {
                clearTimeout(timer);
                timer = null;
                running = false;
                i = 0;
                diffCounter = 1;
                ztvCounter = 0.25;
                stepCounter = 0;
                ztvMax = 0;
                diff = 0;
                i = 0;
                prevJd = -1;
                prevId = -1;
                $('#z1-chart').highcharts().series[0].setData(null);
                $('#z2-chart').highcharts().series[0].setData(null);
                $('#z3-chart').highcharts().series[0].setData(null);
                $('#z4-chart').highcharts().series[0].setData(null);
                $('#local_ztv-chart').highcharts().series[0].setData(null);
                $('#local_ztv-chart').highcharts().series[1].setData(null);
                $('#local_ztv-chart').highcharts().series[2].setData(null);
                $('#local_ztv-chart').highcharts().series[3].setData(null);
                $('#x-chart').highcharts().series[0].setData(iterations[0].x[1]);
                $('#x-chart').highcharts().series[1].setData(iterations[0].x[2]);
                $('#x-chart').highcharts().series[2].setData(iterations[0].x[3]);
                $('#x-chart').highcharts().series[4].setData(iterations[0].x[0]);
                $('#frobenius-chart').highcharts().series[0].setData(null);
                $('#log').html("Reset<br>");
                $('#x-print').html("<h4>X<sub>0</sub>:</h4>" + print_table(transpose(iterations[0].x)));
            });


            $('#play').click(function () {
                $('#pause').show();
                $('#play').hide();
                if (!running) {
                    running = true;
                    $('#log').prepend("Running<br>");
                    i = 0;

                    var invervalFunction = function () {
                        if (iterations[i].x != null) {
                            $('#x-chart').highcharts().series[4].setData(iterations[i].x[0].slice());
                            $('#x-print').html("<h4>X<sub>" + (diffCounter) + "</sub>:</h4>" + print_table(transpose(iterations[i].x)));
                        }
                        if (iterations[i].diff != null) {
                            $('#frobenius-chart').highcharts().series[0].addPoint([diffCounter, iterations[i].diff]);
                            diffCounter += 1;
                            diff = iterations[i].diff;
                        }
                        if (iterations[i].ztv != null) {
                            //$('#ztf-chart').highcharts().series[0].addPoint([ztvCounter,iterations[i].ztv]);
                            ztvCounter += 1 / 4;
                            if (ztvMax < iterations[i].ztv) {
                                ztvMax = iterations[i].ztv;
                            }
                        }
                        if (i > 1) {
                            id = iterations[i].id;
                            jd = iterations[i].jd;
                            stepCounter++;
                            if (jd !== prevJd) {
                                $('#local_ztv-chart').highcharts().series[0].setData(null);
                                $('#local_ztv-chart').highcharts().series[1].setData(null);
                                $('#local_ztv-chart').highcharts().series[2].setData(null);
                                $('#local_ztv-chart').highcharts().series[3].setData(null);
                                prevJd = jd;
                            }
                            if (id !== prevId) {
                                prevId = id;
                                stepCounter = 0;
                            }
                            switch (iterations[i].id) {
                                case 0:
                                    $('#z1-chart').highcharts().series[0].setData(iterations[i].z);
                                    $('#z2-chart').highcharts().series[0].setData(null);
                                    $('#z3-chart').highcharts().series[0].setData(null);
                                    $('#z4-chart').highcharts().series[0].setData(null);
                                    $('#local_ztv-chart').highcharts().series[id].addPoint(iterations[i].local_ztv);
                                    $('#log').prepend(i + ": " + iterations[i].log + "<br>");
                                    break;
                                case 1:
                                    $('#z2-chart').highcharts().series[0].setData(iterations[i].z);
                                    $('#local_ztv-chart').highcharts().series[id].addPoint(iterations[i].local_ztv);
                                    $('#log').prepend(i + ": " + iterations[i].log + "<br>");
                                    break;
                                case 2:
                                    $('#z3-chart').highcharts().series[0].setData(iterations[i].z);
                                    $('#local_ztv-chart').highcharts().series[id].addPoint(iterations[i].local_ztv);
                                    $('#log').prepend(i + ": " + iterations[i].log + "<br>");
                                    break;
                                case 3:
                                    $('#z4-chart').highcharts().series[0].setData(iterations[i].z);
                                    $('#local_ztv-chart').highcharts().series[id].addPoint(iterations[i].local_ztv);
                                    $('#log').prepend(i + ": " + iterations[i].log + "<br>");
                                    break;
                            }
                        }
                        next();
                    };

                    var next = function () {
                        if (i++ >= iterations.length - 1) return;
                        if (i === 1) {
                            $('#log').prepend(i + ": " + iterations[i].log + "<br>");
                        }
                        $('#counter').html(
                            "Recovery algorithm iteration: " + (diffCounter - 1) + " | Frobenius distance: " + diff.toFixed(4)
                        );
                        timer = setTimeout(invervalFunction, interval);
                    };

                    invervalFunction();
                }
            });
        });

    </script>

<?php include '../footer.php'; ?>