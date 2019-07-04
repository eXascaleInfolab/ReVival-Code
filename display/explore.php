<?php session_start();

$dataset = @$_GET['dataset'];

if (!isset($dataset)) {
    header("Location: datasets.php");
}

include '../connect.php';

// Prepare a query for execution
$result = monetdb_prepare($conn, "my_query",
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


// Execute the prepared query.  Note that it is not necessary to escape
// the string "Joe's Widgets" in any way
$result = monetdb_execute($conn, "my_query", array($dataset));

/*    $query = "
        SELECT
            sets.title as title,
            sets.desc as desc,
            sets.source_title as source_title,
            sets.source_url as source_url,
            sets.unit as unit
        FROM
            sets 
        WHERE 
            sets.id ='$dataset'
    ";

    $result = monetdb_query($conn, $query);*/

if (!$result) {
    die(monetdb_last_error());
}

while ($row = monetdb_fetch_assoc($result)) {
    extract($row);
}

if (!isset($title)) {
    exit("Error: This dataset does not exist!");
}

$query = "
        SELECT
            series.title as serie_title,
            series.id as id
        FROM
            sets_series
        LEFT JOIN
            series
        ON 
            sets_series.serie_id = series.id
        WHERE
            sets_series.set_id = '$dataset'
        ORDER BY
            title
    ";

$result = monetdb_query($conn, $query);

if (!$result) {
    die(monetdb_last_error());
}

$series = array();
while ($row = monetdb_fetch_assoc($result)) {
    extract($row);
    $series[$id] = $serie_title;
}

$query = "
        SELECT
            series.title as serie_title,
            series.id as id
        FROM
            comparison_series
        LEFT JOIN
            series
        ON 
            comparison_series.serie_id = series.id
        WHERE
            comparison_series.set_id = $dataset
        ORDER BY
            title
    ";

$result = monetdb_query($conn, $query);

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

/*
 * the construction below is responsible for creating a chart and then querying a corresponding part of API
 *     to populate it with data; the pipeline is:
 * 1) gather all info about *what* has to be shown
 * 2) form a request to `explore_query.php`
 * 2.1) explore_query parses all the info and constructs a JSON with the response
 * 2.2) JSON is deserialized into a proper JS object
 * 3) all the data is pulled from this object to populate the chart
 */

?>

<div class="container-fluid">
    <p><b><?php echo $title; ?></b><?php echo $desc; ?> - Source: <?php echo $source_title; ?> <a
                href="<?php echo $source_url; ?>"><span class="glyphicon glyphicon-link" aria-hidden="true"></span></a>
        Unit: <?php echo $unit; ?></p>
    <div class="form-group" style="position:absolute; top: 77px; right:30px; z-index: 1;">
        Data:
        <div class="btn-group" role="group">
            <button id="rawButton" type="button" class="btn btn-default btn-sm active">Raw</button>
            <button id="zButton" type="button" class="btn btn-default btn-sm">Z-Score</button>
            <button id="minMaxButton" type="button" class="btn btn-default btn-sm">Min-Max</button>
        </div>
    </div>
    <div class="row" style="height: 75vh;">
        <div id="chart" class="col-md-12">
            <div id="container" style="width:100%; height: 100%; margin: 0 auto"></div>
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
            if (this.value === "manual") {
                $('#manual_params').show();
                $('#correlated_params').hide();
            }
            if (this.value === "correlated") {
                $('#manual_params').hide();
                $('#correlated_params').show();
            }
        });

        $('#retrieveForm').click(function () {
            var chart = $('#container').highcharts();
            $('#hiddenMin').val(Math.round(chart.xAxis[0].min));
            $('#hiddenMax').val(Math.round(chart.xAxis[0].max));
        });

        var norm = 0;
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
            var query = 'explore_query.php?dataset=<?php echo $dataset; ?>&norm=' + norm + '&start=' + min + '&end=' + max + '&callback=?';
            loadChart(query);
        }

        function reloadEntireChart() {
            var query = 'explore_query.php?dataset=<?php echo $dataset; ?>&norm=' + norm + '&callback=?';
            loadChart(query);
        }

        // See source code from the JSONP handler at https://github.com/highcharts/highcharts/blob/master/samples/data/from-sql.php
        $.getJSON('explore_query.php?dataset=<?php echo $dataset; ?>&norm=' + norm + '&callback=?', function (data) {
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

            Highcharts.setOptions({
                colors: ["#7cb5ec", "#434348", "#a6c96a", "#2b908f", "#8f10ba", "#f15c80", "#f7a35c", "#876d5d", "#910000", "#8085e9", "#365e0c", "#90ed7d"]
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
                        style: { "fontSize" : "15px" }
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
