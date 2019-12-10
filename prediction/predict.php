<?php session_start();
// this is a workaround
error_reporting(0);

$dataset = @$_GET['dataset'];
$base_serie = @$_GET['base_serie'];
$mode = @$_GET['mode'];
$amount = @$_GET['amount']; // reference time series count
$pred_percent = @$_GET['pred_percent'];

$start = @$_GET['start'];
if ($start && !preg_match('/^[0-9]+$/', $start)) {
    die("Invalid start parameter: $start");
}
$end = @$_GET['end'];
if ($end && !preg_match('/^[0-9]+$/', $end)) {
    die("Invalid end parameter: $end");
}
if (!isset($dataset)) {
    header("Location: datasets.php");
    die();
}
if (!isset($pred_percent))
{
    $pred_percent = 10;
}
if ($mode == "manual") {
    if (empty($_GET['reference_serie'])) {
        $_SESSION["error"] = 'Please select at least one reference time-serie for the retrieval.';
        header("Location: explore.php?dataset=$dataset");
        die();
    }
}
include '../connect.php';

// Prepare a query for execution
$result = monetdb_prepare($conn, "dataset_query",
    'SELECT
                                sets.title as title,
                                sets."desc" as "desc",
                                sets.unit as unit
                            FROM
                                sets 
                            WHERE 
                                sets.id = $1');

$result = monetdb_execute($conn, "dataset_query", array($dataset));

if (!$result) {
    die(monetdb_last_error());
}

while ($row = monetdb_fetch_assoc($result)) {
    extract($row);
}

if (!isset($title)) {
    exit("Error: This dataset does not exist!");
}

$page_title = "Retrieve: " . $title;
include '../header.php';

?>

<div id="loading">
    <img id="loading-image" src="/resources/loader.gif" alt="Loading..."/>
    <h3>The prediction using Centroid Decomposition is being processed.</h3>
    <p>Depending on parameters, the processing can take up to several minutes to complete. <br>
        Factors that influence the duration:
    <ul>
        <li>The amount of values to predict</li>
        <li>The time range selected</li>
    </ul>
    </p>
</div>
<div class="container-fluid">
    <button id="showMenu" class="btn btn-default btn-sm" style="position:absolute; top: 77px; right:20px; z-index: 1;">
        <span class="glyphicon glyphicon-chevron-left" aria-hidden="true"></span>
        <span class="glyphicon glyphicon-align-justify" aria-hidden="true"></span>
    </button>
    <button id="hideMenu" class="btn btn-default btn-sm"
            style="display: none; position:absolute; top: 77px; right:20px; z-index: 1;">
        <span class="glyphicon glyphicon-align-justify" aria-hidden="true"></span>
        <span class="glyphicon glyphicon-chevron-right" aria-hidden="true"></span>
    </button>
    <div class="form-group" style="position:absolute; top: 77px; right:85px; z-index: 1;">
        Data:
        <div class="btn-group" role="group">
            <button id="rawButton" type="button" class="btn btn-default btn-sm active">Raw</button>
            <button id="zButton" type="button" class="btn btn-default btn-sm">Z-Score</button>
            <button id="minMaxButton" type="button" class="btn btn-default btn-sm">Min-Max</button>
        </div>
    </div>
    <div id="chart" class="col-md-12">
        <div id="container" style="width:100%; height: 100%; margin: 0 auto"></div>
    </div>
    <div id="menu" class="hidden" style="padding-top: 40px;">
        <h3><?php echo $title; ?></h3>
        <p>Unit: <?php echo $unit; ?></p>
        <p><?php echo $desc; ?></p>
        <h4>Retrieval information:</h4>
        <p>
            Base time-series: <span id="base-serie-title"></span><br>
            Range: <br>
        <ul>
            <li>Start: <span id="start-time"></span></li>
            <li>End: <span id="end-time"></span></li>
        </ul>
        Existing values: <span id="base-serie-existing"></span><br>
        Missing values: <span id="base-serie-missing"></span><br>
        Threshold: <span id="threshold"></span><br>
        cRecM iterations: <span id="iterations"></span><br>
        Processing time: <span id="duration"></span> seconds
        </p>
        <p><a href="explore.php?dataset=<?php echo $dataset; ?>">Return to dataset overview</a></p>
    </div>
</div>

<script type='text/javascript'>

    $(function () {
        var retrieve_object;

        Highcharts.theme = {
            colors: ["#7cb5ec", "#2b908f", "#a6c96a", "#876d5d", "#8f10ba", "#f7a35c", "#434348", "#f15c80", "#910000", "#8085e9", "#365e0c", "#90ed7d"]
        };

        Highcharts.setOptions(Highcharts.theme);


        $("#hideMenu").click(function () {
            $('#chart').removeClass('col-md-9').addClass('col-md-12');
            $('#menu').removeClass('col-md-3').addClass('hidden');
            $('#hideMenu').hide();
            $('#showMenu').show();
            $('#container').highcharts().reflow();
        });

        $("#showMenu").click(function () {
            $('#chart').removeClass('col-md-12').addClass('col-md-9');
            $('#menu').removeClass('hidden').addClass('col-md-3');
            $('#showMenu').hide();
            $('#hideMenu').show();
            $('#container').highcharts().reflow();
        });

        $("#rawButton").click(function () {
            setNorm(0);
            $('#zButton').removeClass('active');
            $('#minMaxButton').removeClass('active');
            $('#rawButton').addClass('active');
        });

        $("#zButton").click(function () {
            setNorm(2);
            $('#rawButton').removeClass('active');
            $('#zButton').addClass('active');
            $('#minMaxButton').removeClass('active');
        });

        $("#minMaxButton").click(function () {
            setNorm(1);
            $('#rawButton').removeClass('active');
            $('#zButton').removeClass('active');
            $('#minMaxButton').addClass('active');
        });

        var norm = 0;
        var min, max;

        function setNorm(norm) {
            var i;
            var chart = $('#container').highcharts();
            if (norm === 0) {
                i = 0;
                retrieve_object.reference_series.forEach(function (series) {
                    chart.series[i].setData(series.points.raw);
                    i += 1;
                });
                retrieve_object.predicted_series.forEach(function (series) {
                    chart.series[i].setData(series.points.raw);
                    i += 1;
                });
                retrieve_object.ground_series.forEach(function (series) {
                    chart.series[i].setData(series.points.raw);
                    i += 1;
                });
                /*
                var points0 = chart.series[i].data;
                for (j = 0; j < points0.length; j++)
                {
                    points0[j].y = null;
                }
                points0[points0.length-1].y = 15.0;
                chart.series[i].setData(points0);
                */
            }
            else if (norm === 2) {
                i = 0;
                retrieve_object.reference_series.forEach(function (series) {
                    chart.series[i].setData(series.points.znorm);
                    i += 1;
                });
                retrieve_object.predicted_series.forEach(function (series) {
                    chart.series[i].setData(series.points.znorm);
                    i += 1;
                });
                retrieve_object.ground_series.forEach(function (series) {
                    chart.series[i].setData(series.points.znorm);
                    i += 1;
                });

                /*
                var points0 = chart.series[i].data;
                for (j = 0; j < points0.length; j++)
                {
                    points0[j].y = null;
                }
                points0[points0.length-1].y = 9.0;
                chart.series[i].setData(points0);
                */
            }
            else if (norm === 1) {
                i = 0;
                retrieve_object.reference_series.forEach(function (series) {
                    chart.series[i].setData(series.points.minmax);
                    i += 1;
                });
                retrieve_object.predicted_series.forEach(function (series) {
                    chart.series[i].setData(series.points.minmax);
                    i += 1;
                });
                retrieve_object.ground_series.forEach(function (series) {
                    chart.series[i].setData(series.points.minmax);
                    i += 1;
                });
            }
        }

        var query = 'predict_query.php?dataset=<?php echo $dataset; ?>&mode=<?php echo $mode; ?>&amount=<?php echo $amount; ?>';
        query = query.concat("&start=<?php echo $_GET['start']; ?>");
        query = query.concat("&end=<?php echo $_GET['end']; ?>");
        query = query.concat("&pred_percent=<?php echo $pred_percent; ?>");
        <?php foreach ($_GET['reference_serie'] as $selected_option) {
            echo 'query = query.concat("&reference_serie[]=' . $selected_option . '");';
        } ?>
        query = query.concat('&callback=?');

        $.getJSON(query, function (data) {
            $('#loading').hide();
            retrieve_object = data[0];
            var renderedSeries = [];
            var visibility = <?php echo "$dataset" ?> != 4;

            var counter = 0;
            const reflen = retrieve_object.reference_series.length;

            retrieve_object.reference_series.forEach(function (series) {
                renderedSeries.push({
                    type: 'line',
                    visible: true,
                    name: "[R] ".concat(series.title),
                    data: series.points.raw.slice(0, series.points.raw.length),
                    color: Highcharts.theme.colors[counter],
                    marker: {
                        enabled: false
                    },
                    dataGrouping: {enabled: false}
                });
                counter++;

            });

            counter = 0;

            retrieve_object.predicted_series.forEach(function (series) {
                renderedSeries.push({
                    type: 'line',
                    visible: true,
                    name: "[P] ".concat(series.title),
                    data: series.points.raw.slice(0, series.points.raw.length),
                    color: 'red',
                    dashStyle: 'shortdot',
                    marker: {
                        enabled: false
                    },
                    dataGrouping: {enabled: false}
                });

                visibility = counter == (reflen - 2) && <?php echo "$dataset" ?> != 4;
                counter++;

            });

            counter = 0;

            retrieve_object.ground_series.forEach(function (series) {
                renderedSeries.push({
                    type: 'line',
                    visible: true,
                    name: "[P] ".concat(series.title),
                    data: series.points.raw.slice(0, series.points.raw.length),
                    color: Highcharts.theme.colors[counter],
                    dashStyle: 'shortdot',
                    marker: {
                        enabled: false
                    },
                    dataGrouping: {enabled: false}
                });

                visibility = counter == (reflen - 2) && <?php echo "$dataset" ?> != 4;
                counter++;

            });

            /*
            renderedSeries.push({
                type: 'line',
                visible: true,
                name: "[ref]",
                data: retrieve_object.ground_series[0].points.raw.slice(0, retrieve_object.ground_series[0].points.raw.length),
                color: 'black',
                dashStyle: 'shortdot',
                marker: {
                    enabled: false
                },
                dataGrouping: {enabled: false}
            });
            */

            var info = retrieve_object.info;

            $('#start-time').html(info.start_time);
            $('#end-time').html(info.end_time);
            $('#duration').html((info.duration / 1000).toFixed(3));

            // create the chart
            $('#container').highcharts('StockChart', {

                chart: {
                    type: 'line',
                    zoomType: 'x'
                },

                navigator: {
                    adaptToUpdatedData: false,
                    series: {
                        color: '#ff0000',
                        data: retrieve_object.reference_series[0].points.raw.slice(0, retrieve_object.reference_series[0].points.raw.length),
                    }
                },

                rangeSelector: {
                    buttons: [
                        {
                            type: 'week',
                            count: 1,
                            text: '1w'
                        },
                        {
                            type: 'month',
                            count: 1,
                            text: '1m'
                        },
                        {
                            type: 'all',
                            text: 'All'
                        }
                    ],
                    inputEnabled: false,
                    selected: 2 // all
                },

                plotOptions: {
                    line: {
                        marker: {
                            enabled: true
                        }
                    }
                },

                tooltip: {
                    // pointFormat: '<span style="color:{series.color}">{series.name}</span>: {point.y}<br/>',
                    valueDecimals: 4
                },

                series: renderedSeries,

                exporting: {
                    enabled: false
                },
                legend: {
                    enabled: true,
                    floating: true,
                    layout: 'horizontal',
                    align: 'left',
                    y: 40,
                    verticalAlign: 'top'
                },
                xAxis:{
                    labels : {
                        style: { "fontSize" : "15px" }
                    }
                },
                yAxis: {
                    opposite: true,
                    labels : {
                        style : { "fontSize" : "15px", "font-weight" : "bold" }
                    }
                },
                credits: {
                    enabled: false,
                },
                title: {
                    text: null
                }

            });
        });

    });
</script>

<?php
include '../footer.php';
monetdb_disconnect();
?>
