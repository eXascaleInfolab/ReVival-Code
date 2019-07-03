<?php session_start();

$dataset = @$_GET['dataset'];

if (!isset($dataset)) {
    header("Location: datasets.php");
}

include '../connect.php';


// Prepare a query for execution
$result = monetdb_prepare($conn, "dataset_query",
    'SELECT
                                sets.title as title,
                                sets."desc" as "desc",
                                sets.source_title as source_title,
                                sets.source_url as source_url,
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

// Prepare a query for execution
$result = monetdb_prepare($conn, "series_query",
    'SELECT
            series.title as serie_title,
            series.id as id
        FROM
            sets_series
        LEFT JOIN
            series
        ON 
            sets_series.serie_id = series.id
        WHERE
            sets_series.set_id = $1
        ORDER BY
            title');

$result = monetdb_execute($conn, "series_query", array($dataset));

if (!$result) {
    die(monetdb_last_error());
}

$series = array();
while ($row = monetdb_fetch_assoc($result)) {
    extract($row);
    $series[$id] = $serie_title;
}

// Prepare a query for execution
$result = monetdb_prepare($conn, "comparison_query",
    'SELECT
                                series.title as serie_title,
                                series.id as id
                            FROM
                                comparison_series
                            LEFT JOIN
                                series
                            ON 
                                comparison_series.serie_id = series.id
                            WHERE
                                comparison_series.set_id = $1
                            ORDER BY
                                title');

$result = monetdb_execute($conn, "comparison_query", array($dataset));

if (!$result) {
    die(monetdb_last_error());
}

$comparison_series = array();
while ($row = monetdb_fetch_assoc($result)) {
    extract($row);
    $comparison_series[$id] = $serie_title;
}

$page_title = "Explore: " . $title;
include '../header.php';
?>


<div class="container-fluid">
    <button id="showMenu" class="btn btn-default btn-sm"
            style="display: none; position:absolute; top: 77px; right:20px; z-index: 1;">
        <span class="glyphicon glyphicon-chevron-left" aria-hidden="true"></span>
        <span class="glyphicon glyphicon-align-justify" aria-hidden="true"></span>
    </button>
    <button id="hideMenu" class="btn btn-default btn-sm" style="position:absolute; top: 77px; right:20px; z-index: 1;">
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
    <?php if (isset($_SESSION["error"])) { ?>
        <div class="col-md-8">
            <div class="alert alert-danger" role="alert">
                <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span>
                </button>
                <?php echo $_SESSION["error"]; ?></div>
        </div>
        <?php unset($_SESSION["error"]);
    } ?>
    <div class="row" style="height: 90vh;">
        <div id="chart" class="col-md-9">
            <div id="container" style="width:100%; height: 100%; margin: 0 auto"></div>
        </div>
        <div id="menu" class="col-md-3" style="padding-top: 40px;">
            <h3><?php echo $title; ?></h3>
            <p><?php echo $desc; ?></p>
            <p>Source: <a
                        href="<?php echo $source_url; ?>"><?php echo $source_title; ?></a><br>Unit: <?php echo $unit; ?>
            </p>
            <h3>Recovery of missing values</h3>
            <form id="retrieveForm" action="recover.php" method="get">
                <input type="hidden" name="dataset" value='<?php echo $dataset; ?>'>
                <input type="hidden" name="truncation" value='0'>
                <input type="hidden" name="start" id="hiddenMin" value="">
                <input type="hidden" name="end" id="hiddenMax" value="">
                <div class="form-group">
                    <label>Recover missing values for:</label>
                    <select class="form-control" id="base" name="base_serie">
                        <?php foreach ($series as $id => $serie_title) {
                            echo "<option value='" . $id . "'>" . $serie_title . "</option>";
                        } ?>

                    </select>
                </div>
                <div class="form-group">
                    <label>Choice of reference time-series: <a data-container="body" data-toggle="popover"
                                                               data-placement="top"
                                                               data-content="Manual mode let's you manually choose the reference series. Globally correlated mode automatically takes the n most correlated series as reference series. ">
                            <span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span>
                        </a></label>
                    <select id="modeSelect" name="mode" class="form-control" onchange="changeMode(this.value)">
                        <option value="correlated" selected="selected">Automatically (based on correlation)</option>
                        <option value="manual">Manually (select below)</option>
                    </select>
                </div>
                <div id="manual_params" style="display: none;">
                    <div class="form-group">
                        <label>Reference time-series:
                            <small>Multiple selection allowed (Ctrl + click)</small>
                        </label>
                        <select id="manual" name="reference_serie[]" multiple class="form-control">

                            <?php foreach ($series as $id => $serie_title) {
                                echo "<option value='" . $id . "'>" . $serie_title . "</option>";
                            } ?>

                        </select>
                    </div>
                </div>
                <div id="correlated_params">
                    <div class="form-group">
                        <label>Amount of reference time-series:</label>
                        <select id="amount" class="form-control" name="amount">
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="4">4</option>
                            <option value="5" selected>5</option>
                        </select>
                    </div>
                </div>
                <?php if (count($comparison_series) > 0) { ?>
                    <?php foreach ($comparison_series as $id => $serie_title) {
                    } ?>
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" id="comparison" name="comparison_serie" value=" <?php echo $id; ?>">
                            Use ground truth (if available)
                        </label>
                    </div>
                <?php } ?>
                <div class="form-group">
                    <label>Time range:</label>
                    <select class="form-control" id="range" name="range">
                        <option value="7">week</option>
                        <option value="30">month</option>
                        <option value="365">year</option>
                        <option value="0" selected>as selected in chart</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Threshold epsilon for CD:</label>
                    <select class="form-control" id="threshold" name="threshold">
                        <option>0.1</option>
                        <option selected="selected">0.01</option>
                        <option>0.001</option>
                        <option>0.0001</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-default pull-right">Recover</button>
            </form>
        </div>
    </div>
</div>

<script type='text/javascript'>

    $(function () {
        $('[data-toggle="popover"]').popover()
    })

    $(function () {
        $(window).bind("pageshow", function () {
            var form = $("#retrieveForm");
            // let the browser natively reset defaults
            form[0].reset();
        });

        $('#hideMenu').click(function () {
            $('#chart').removeClass('col-md-9').addClass('col-md-12');
            $('#menu').removeClass('col-md-3').addClass('hidden');
            $('#hideMenu').hide();
            $('#showMenu').show();
            $('#container').highcharts().reflow();
        });

        $('#showMenu').click(function () {
            $('#chart').removeClass('col-md-12').addClass('col-md-9');
            $('#menu').removeClass('hidden').addClass('col-md-3');
            $('#showMenu').hide();
            $('#hideMenu').show();
            $('#container').highcharts().reflow();
        });

        $('#rawButton').click(function () {
            setNorm(0);
            $('#zButton').removeClass('active');
            $('#minMaxButton').removeClass('active');
            $('#rawButton').addClass('active');
        });

        $('#zButton').click(function () {
            setNorm(1);
            $('#rawButton').removeClass('active');
            $('#minMaxButton').removeClass('active');
            $('#zButton').addClass('active');
        });

        $('#minMaxButton').click(function () {
            setNorm(2);
            $('#rawButton').removeClass('active');
            $('#zButton').removeClass('active');
            $('#minMaxButton').addClass('active');
        });

        $('#modeSelect').on('change', function () {
            if (this.value == "manual") {
                $('#manual_params').show();
                $('#correlated_params').hide();
            }
            if (this.value == "correlated") {
                $('#manual_params').hide();
                $('#correlated_params').show();
            }
        });

        $('#retrieveForm').click(function () {
            var chart = $('#container').highcharts();
            $('#hiddenMin').val(Math.round(chart.xAxis[0].min));
            $('#hiddenMax').val(Math.round(chart.xAxis[0].max));
        });

        var norm = 1;
        var min, max;

        function setNorm(i) {
            norm = i;
            // if no extremes set => load entire range
            if (min == null || max == null) {
                reloadEntireChart();
            }
            // otherwise =>
            else {
                reloadChartWithExtremes(min, max);
            }
        }

        function loadChart(query) {
            var chart = $('#container').highcharts();
            chart.showLoading('<img src="https://upload.wikimedia.org/wikipedia/commons/b/b1/Loading_icon.gif">');
            $.getJSON(query, function (data) {
                var explore_object = data[0];
                var i = 0;
                explore_object.series.forEach(function (series) {
                    chart.series[i].setData(series.points);
                    i += 1;
                });
                chart.hideLoading();
            });
        }

        /**
         * Load new data depending on the selected min and max
         */
        function afterSetExtremes(e) {
            min = Math.round(e.min);
            max = Math.round(e.max);
            reloadChartWithExtremes(min, max);
        }

        function reloadChartWithExtremes(min, max) {
            var query = 'explore_incomplete_query.php?dataset=<?php echo $dataset; ?>&norm=' + norm + '&start=' + min + '&end=' + max + '&callback=?';
            loadChart(query);
        }

        function reloadEntireChart() {
            var query = 'explore_incomplete_query.php?dataset=<?php echo $dataset; ?>&norm=' + norm + '&callback=?';
            loadChart(query);
        }

        // See source code from the JSONP handler at https://github.com/highcharts/highcharts/blob/master/samples/data/from-sql.php
        $.getJSON('explore_incomplete_query.php?dataset=<?php echo $dataset; ?>&norm=' + norm + '&callback=?', function (data) {
            var explore_object = data[0];
            var renderedSeries = [];
            var visibility = true;

            explore_object.series.forEach(function (series) {

                renderedSeries.push({
                    type: 'line',
                    visible: visibility,
                    name: series.title,
                    data: series.points,
                    dataGrouping: {enabled: false}
                });

            });


            // create the chart
            var chart = $('#container').highcharts('StockChart', {

                chart: {
                    type: 'line',
                    zoomType: 'x'
                },

                /*subtitle: {
                    text: 'Subtitle'
                },*/

                navigator: {
                    adaptToUpdatedData: false
                },

                tooltip: {
                    // pointFormat: '<span style="color:{series.color}">{series.name}</span>: {point.y}<br/>',
                    valueDecimals: 4
                },

                series: renderedSeries,

                scrollbar: {
                    liveRedraw: false
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
                            type: 'year',
                            count: 1,
                            text: '1y'
                        },
                        {
                            type: 'year',
                            count: 2,
                            text: '2y'
                        },
                        {
                            type: 'year',
                            count: 5,
                            text: '5y'
                        },
                        {
                            type: 'all',
                            text: 'All'
                        }
                    ],
                    inputEnabled: false,
                    selected: 3 // 2y
                },

                exporting: {
                    enabled: false
                },

                xAxis: {
                    events: {
                        afterSetExtremes: afterSetExtremes
                    },
                    type: 'datetime',
                    minRange: 24 * 3600 * 1000, // one day
                    labels : {
                        style: { "fontSize" : "15px" }//, "font-weight" : "bold" }
                    }
                },

                legend: {
                    enabled: true,
                    floating: true,
                    layout: 'horizontal',
                    align: 'left',
                    x: 230,
                    verticalAlign: 'top'
                },
                yAxis: {
                    opposite: true,
                    labels : {
                        style : { "fontSize" : "15px" , "font-weight" : "bold" }
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
