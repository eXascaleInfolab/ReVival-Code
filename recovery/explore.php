<?php session_start();

$dataset = @$_GET['dataset'];

if (!isset($dataset)) {
    header("Location: datasets.php");
}

include '../connect.php';
include '../logger.php';

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
            <p>
                <span>Source: </span>
                <a href="<?php echo $source_url; ?>"><?php echo $source_title; ?></a><br>Unit: <?php echo $unit; ?>
            </p>
            <h3>Recovery of missing values</h3>
            <form id="retrieveForm">
                <input type="hidden" name="dataset" value='<?php echo $dataset; ?>'>
                <input type="hidden" name="truncation" value='0'>
                <input type="hidden" name="start" id="hiddenMin" value="">
                <input type="hidden" name="end" id="hiddenMax" value="">
                <div class="form-group">
                    <label>Recover missing values for:</label>
                    <select class="form-control" id="base" name="base_serie" multiple>
                        <?php foreach ($series as $id => $serie_title) {
                            echo "<option value='" . $id . "'>" . $serie_title . "</option>";
                        } ?>
                    </select>
                </div>
                <!-- <div class="form-group">
                    <label>Choice of reference time-series: <a data-container="body" data-toggle="popover"
                                                               data-placement="top"
                                                               data-content="Manual mode let's you manually choose the reference series. Globally correlated mode automatically takes the n most correlated series as reference series. ">
                            <span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span>
                        </a></label>
                    <select id="modeSelect" name="mode" class="form-control" onchange="changeMode(this.value)">
                        <option value="correlated" selected="selected">Automatically (based on correlation)</option>
                        <option value="manual">Manually (select below)</option>
                    </select>
                </div> -->
                <!-- <div id="manual_params" style="display: none;">
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
                </div> -->
                <!-- <div id="correlated_params">
                    <div class="form-group">
                        <label>Amount of reference time-series:</label>
                        <select id="amount" class="form-control" name="amount">
                            <option value="2">2</option>
                            <option value="3" selected>3</option>
                            <option value="4">4</option>
                            <option value="5">5</option>
                        </select>
                    </div>
                </div> -->
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
                <!-- <div class="form-group">
                    <label>Time range:</label>
                    <select class="form-control" id="range" name="range">
                        <option value="7">week</option>
                        <option value="30" selected>month</option>
                        <option value="365">year</option>
                        <option value="0">as selected in chart</option>
                    </select>
                </div> -->
                <div class="form-group">
                    <label>Threshold epsilon for CD:</label>
                    <select class="form-control" id="threshold" name="threshold">
                        <option>0.1</option>
                        <option selected="selected">0.01</option>
                        <option>0.001</option>
                        <option>0.0001</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Drop values by:</label>
                    <select class="form-control" name="drop">
                        <option value="0">0%</option>
                        <option value="0.10">10%</option>
                        <option value="0.20">20%</option>
                        <option value="0.40">40%</option>
                        <option value="0.60">60%</option>
                        <option value="0.80">80%</option>
                    </select>
                    <!-- <input name="missingperc" class="form-control" title="missing" value="10%" maxlength="3"> -->
                </div>
<<<<<<< HEAD
                <!-- <button type="submit" name="action" value="apply" class="btn btn-default pull-left">Apply</button>
                <button type="submit" name="action" value="recover"  class="btn btn-default pull-right">Recover</button> -->
                <input id="applyBtn" type="submit" formaction="/api/drop" value="Apply" class="btn btn-default pull-left" />
                <input id="recoverBtn" type="submit" formaction="/api/recover" value="Recover" class="btn btn-default pull-right" />
=======
                <button type="submit" class="btn btn-default pull-left">Apply</button>
                <button type="submit" class="btn btn-default pull-right">Recover</button>
>>>>>>> master
            </form>
        </div>
    </div>
</div>

<script type='text/javascript'>

    $(function () {
        $('[data-toggle="popover"]').popover()
    })

    // document ready
    $(function () {
        var norm = 1;
        var min, max;

        // object to hold chart settings mutations;
        let store = {
            norm,
        };

        /**
        Makes call to backend
         */
        function requestSeries(url, data) {
            const headers = new Headers (
                {
                    'Content-Type': 'application/json',
                },
            );
            const options = {
                method: 'POST',
                headers,
                body: JSON.stringify(data),
            };
            return fetch(new Request(url, options));
        };

        function updateStore(args) {
            store = {
                ...store,
                ...args,
            };
            return store;
        }

        $('#applyBtn').on('click', function(e) {
            const form = e.target.form;
            form.action = e.target.formAction;
        });

        $('#recoverBtn').on('click', function(e) {
            const form = e.target.form;
            form.action = e.target.formAction;
        });

        $('#retrieveForm').on('submit', function(e) {
            e.preventDefault();
            const form = e.target;
            const selectedOptions = form['base_serie'].selectedOptions;
            const series = Array.from(selectedOptions).map(next => parseInt(next.value, 10));
            const data = {
                norm: store.norm,
                start: store.min,
                end: store.max,
                series,
                drop: parseFloat(form['drop'].value, 10),
            };
            const url = form.action;
            requestSeries(url, data)
                .then(response => {
                    if (response.status === 200) {
                        return response.json();
                    }
                    return Promise.reject(response.statusText);
                })
                .then((json) => {
                    console.log(json);
                    updateSeries(json.series);
                })
                .catch(err => console.error(err));
        });

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

       
        function setNorm(i) {
            norm = i;
            updateStore({norm});
            // if no extremes set => load entire range
            if (min == null || max == null) {
                reloadEntireChart();
            }
            // otherwise =>
            else {
                reloadChartWithExtremes(min, max);
            }
        }

        function updateSeries(series) {
            const chart = $('#container').highcharts();
            chart.showLoading('<img src="https://upload.wikimedia.org/wikipedia/commons/b/b1/Loading_icon.gif">');
            series.forEach((serie, i) => {
                chart.series[i].setData(serie.points);
            });
            chart.hideLoading();
        }

        function loadChart(query) {
            var chart = $('#container').highcharts();
            chart.showLoading('<img src="https://upload.wikimedia.org/wikipedia/commons/b/b1/Loading_icon.gif">');
            $.getJSON(query, function (data) {
                var explore_object = data[0];
                // var i = 0;
                console.log('RESPONSE: ', explore_object);
                explore_object.series.forEach(function (series, i) {
                    chart.series[i].setData(series.points);
                    // i += 1;
                });
                chart.hideLoading();
            });
        }

    
        function reloadChartWithExtremes(min, max) {
            var query = 'explore_query.php?dataset=<?php echo $dataset; ?>&norm=' + norm + '&start=' + min + '&end=' + max + '&callback=?';
            loadChart(query);
        }

        function reloadEntireChart() {
            var query = 'explore_query.php?dataset=<?php echo $dataset; ?>&norm=' + norm + '&callback=?';
            loadChart(query);
        }


        /// CHART EVENT HANDLERS
        ////////////////////////

        // handles chart legend item clicks
        function legendItemClickHandler(e) {
            console.log(`${e.target.name} click!`);
            // TODO handle logic with togged serie
        }

        /**
         * Load new data depending on the selected min and max
         */
        function afterSetExtremesHandler(e) {
            console.log('[afterSetExtremesHandler] -- ', e);
            min = Math.round(e.min);
            max = Math.round(e.max);
            store = {
                ...store,
                min,
                max,
            };
            reloadChartWithExtremes(min, max);
        }

        // See source code from the JSONP handler at https://github.com/highcharts/highcharts/blob/master/samples/data/from-sql.php
        $.getJSON('explore_query.php?dataset=<?php echo $dataset; ?>&norm=' + norm + '&callback=?', function (data) {
            var explore_object = data[0];
            var renderedSeries = [];
            var visibility = true;

            explore_object.series.forEach(function (series) {
                renderedSeries.push({
                    id: parseInt(series.id, 10),
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
                    zoomType: 'x',
                    events: {
                        load: function (e) {
                            try {
                                const firstSerie = e.target.series[0]
                                const min = firstSerie.xAxis.dataMin;
                                const max = firstSerie.xAxis.dataMax;
                                updateStore({min, max});
                            } catch(err) {
                                console.error(err);
                            }
                        }
                    }
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

                plotOptions: {
                    series: {
                        events: {
                            legendItemClick: legendItemClickHandler,
                        },
                    },
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
                            count: 5,
                            text: '5y'
                        },
                        {
                            type: 'all',
                            text: 'All'
                        }
                    ],
                    inputEnabled: false,
                    selected: 3 // 5y
                },

                exporting: {
                    enabled: false
                },

                xAxis: {
                    events: {
                        afterSetExtremes: afterSetExtremesHandler
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
