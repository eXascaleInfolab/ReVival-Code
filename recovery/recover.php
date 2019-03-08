<?php session_start();
// this is a workaround
error_reporting(0);

$dataset = @$_GET['dataset'];
$base_serie = @$_GET['base_serie'];
$mode = @$_GET['mode'];
$amount = @$_GET['amount'];

$start = @$_GET['start'];
if ($start && !preg_match('/^[0-9]+$/', $start)) {
    die("Invalid start parameter: $start");
}
$end = @$_GET['end'];
if ($end && !preg_match('/^[0-9]+$/', $end)) {
    die("Invalid end parameter: $end");
}
$truncation = @$_GET['truncation'];
if ($truncation && !preg_match('/^[0-9]+$/', $truncation)) {
    die("Invalid truncation parameter: $truncation");
}

if (!isset($dataset)) {
    header("Location: datasets.php");
    die();
}
if (!isset($base_serie)) {
    $_SESSION["error"] = 'Please select a base time-serie for the retrieval.';
    header("Location: explore.php?dataset=$dataset");
    die();
}
if ($base_serie == "") {
    $_SESSION["error"] = 'Please select a base time-serie for the retrieval.';
    header("Location: explore.php?dataset=$dataset");
    die();
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
    <h3>The recovery of missing values using Centroid Decomposition is being processed.</h3>
    <p>Depending on parameters, the processing can take up to several minutes to complete. <br>
        Factors that influence the duration:
    <ul>
        <li>The amount of (missing) values</li>
        <li>The amount of reference time series</li>
        <li>The time range selected</li>
        <li>The selected threshold epsilon</li>
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
            <button id="rawButton" type="button" class="btn btn-default btn-sm">Raw</button>
            <button id="zButton" type="button" class="btn btn-default btn-sm active">Z-Score</button>
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
            colors: ['#058DC7', '#50B432', '#DDDF00', '#24CBE5', '#64E572', '#FF9655', '#FFF263', '#6AF9C4']
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

        var norm = 1;
        var min, max;

        function setNorm(norm) {
            var i;
            var chart = $('#container').highcharts();
            if (norm === 0) {
                i = 3;
                retrieve_object.reference_series.forEach(function (series) {
                    chart.series[i].setData(series.points.raw);
                    i += 1;
                });
                chart.series[2].setData(retrieve_object.linear.points.raw);
                chart.series[1].setData(retrieve_object.original.points.raw);
                chart.series[0].setData(retrieve_object.retrieved.points.raw);
                if (retrieve_object.comparison != null) {
                    chart.series[i].setData(retrieve_object.comparison.points.raw);
                }
            }
            else if (norm === 2) {
                i = 3;
                retrieve_object.reference_series.forEach(function (series) {
                    chart.series[i].setData(series.points.znorm);
                    i += 1;
                });
                chart.series[2].setData(retrieve_object.linear.points.znorm);
                chart.series[1].setData(retrieve_object.original.points.znorm);
                chart.series[0].setData(retrieve_object.retrieved.points.znorm);
                if (retrieve_object.comparison != null) {
                    chart.series[i].setData(retrieve_object.comparison.points.znorm);
                }
            }
            else if (norm === 1) {
                i = 3;
                retrieve_object.reference_series.forEach(function (series) {
                    chart.series[i].setData(series.points.minmax);
                    i += 1;
                });
                chart.series[2].setData(retrieve_object.linear.points.minmax);
                chart.series[1].setData(retrieve_object.original.points.minmax);
                chart.series[0].setData(retrieve_object.retrieved.points.minmax);
                if (retrieve_object.comparison != null) {
                    chart.series[i].setData(retrieve_object.comparison.points.minmax);
                }
            }
        }

        var query = 'recover_query.php?dataset=<?php echo $dataset; ?>&mode=<?php echo $mode; ?>&amount=<?php echo $amount; ?>';
        query = query.concat("&start=<?php echo $_GET['start']; ?>");
        query = query.concat("&end=<?php echo $_GET['end']; ?>");
        query = query.concat("&base_serie=<?php echo $_GET['base_serie']; ?>");
        query = query.concat("&comparison_serie=<?php echo $_GET['comparison_serie']; ?>");
        <?php foreach ($_GET['reference_serie'] as $selected_option) {
        echo 'query = query.concat("&reference_serie[]=' . $selected_option . '");';
    } ?>
        query = query.concat('&threshold=<?php echo $_GET['threshold']; ?>');
        query = query.concat('&truncation=<?php echo $_GET['truncation']; ?>');
        query = query.concat('&range=<?php echo $_GET['range']; ?>');
        query = query.concat('&callback=?');

        $.getJSON(query, function (data) {
            $('#loading').hide();
            retrieve_object = data[0];
            var renderedSeries = [];
            var visibility = true;

            renderedSeries.push({
                type: 'line',
                name: "[B] ".concat(retrieve_object.retrieved.title).concat("<br>centroid decomp."),
                data: retrieve_object.retrieved.points.znorm.slice(0, retrieve_object.retrieved.points.znorm.length),
                marker: {
                    radius: 2,
                    symbol: 'circle'
                },
                dashStyle: 'shortdot',
                color: 'red',
                zIndex: 998,
                dataGrouping: {enabled: false}
            });

            renderedSeries.push({
                type: 'line',
                name: "[B] ".concat(retrieve_object.original.title).concat("<br>existing values"),
                data: retrieve_object.original.points.znorm.slice(0, retrieve_object.original.points.znorm.length),
                color: 'black',
                lineWidth: 3,
                marker: {
                    radius: 2,
                    symbol: 'circle'
                },
                zIndex: 999,
                dataGrouping: {enabled: false}
            });

            renderedSeries.push({
                type: 'line',
                visible: false,
                name: "[B] ".concat(retrieve_object.linear.title).concat("<br>linear interp."),
                data: retrieve_object.linear.points.znorm.slice(0, retrieve_object.linear.points.znorm.length),
                marker: {
                    radius: 2,
                    symbol: 'circle'
                },
                color: 'green',
                zIndex: 997,
                dataGrouping: {enabled: false}
            });

            var counter = 0;

            retrieve_object.reference_series.forEach(function (series) {

                renderedSeries.push({
                    type: 'line',
                    visible: visibility,
                    name: "[R] ".concat(series.title),
                    data: series.points.znorm.slice(0, series.points.znorm.length),
                    marker: {
                        enabled: false
                    },
                    dataGrouping: {enabled: false}
                });

                counter++;
                if (counter > 1) visibility = false;

            });

            if (retrieve_object.comparison != null) {
                renderedSeries.push({
                    type: 'line',
                    visible: false,
                    name: "[O] ".concat(retrieve_object.comparison.title).concat("<br>ground truth"),
                    data: retrieve_object.comparison.points.znorm.slice(0, retrieve_object.comparison.points.znorm.length),
                    color: 'red',
                    marker: {
                        radius: 2,
                        symbol: 'circle'
                    },
                    zIndex: 996,
                    dataGrouping: {enabled: false}
                });
            }

            var info = retrieve_object.info;

            $('#start-time').html(info.start_time);
            $('#end-time').html(info.end_time);
            $('#base-serie-title').html(info.base_serie_title);
            $('#base-serie-existing').html(info.base_serie_existing);
            $('#base-serie-missing').html(info.base_serie_missing);
            $('#threshold').html(info.threshold);
            $('#iterations').html(info.iterations);
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
                        data: retrieve_object.missing.points.slice(0, retrieve_object.missing.points.length),
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
