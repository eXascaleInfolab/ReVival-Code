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
                    <!-- <label>Recover missing values for:</label> -->
                    <!-- <select class="form-control" id="base" name="base_serie" multiple>
                        <?php foreach ($series as $id => $serie_title) {
                            echo "<option value='" . $id . "'>" . $serie_title . "</option>";
                        } ?>
                    </select> -->
                    <div class="accordion" id="seriesAccordion">
                        <div class="card dropdown">
                            <div class="card-header" id="headingOne">
                            <h2 class="mb-0">
                                <button
                                    class="btn collapsed dropdown-toggle"
                                    type="button"
                                    data-toggle="collapse"
                                    data-target="#collapseOne"
                                    aria-expanded="false"
                                    aria-controls="collapseOne"
                                >
                                    <span>Recover missing values for: <i class="fa fa-chevron-down"></i></span>
                                </button>
                            </h2>
                            </div>
                            <div id="collapseOne" class="collapse in" aria-labelledby="headingOne" data-parent="#seriesAccordion">
                                <div class="card-body" style="max-height: 150px; overflow: scroll;">
                                    <ul style="list-style: none;">
                                        <?php 
                                            foreach ($series as $id => $serie_title) {
                                                echo "<li>
                                                    <label for=\"$id\">
                                                        <input id=\"$id\" type=\"checkbox\" name=\"series\" value=\"$id\"/>
                                                        <span> $serie_title</span>
                                                    </label>
                                                </li>";
                                            } 
                                        ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="accordion" id="seriesAccordion2">
                        <div class="card dropdown">
                            <div class="card-header" id="headingTwo">
                                <h2 class="mb-0">
                                    <button
                                            class="btn collapsed dropdown-toggle"
                                            type="button"
                                            data-toggle="collapse"
                                            data-target="#collapseTwo"
                                            aria-expanded="false"
                                            aria-controls="collapseTwo"
                                    >
                                        <span>Use the following time series for reference: <i class="fa fa-chevron-down"></i></span>
                                    </button>
                                </h2>
                            </div>
                            <div id="collapseTwo" class="collapse in" aria-labelledby="headingTwo" data-parent="#seriesAccordion2">
                                <div class="card-body" style="max-height: 150px; overflow: scroll;">
                                    <ul style="list-style: none;">
                                        <?php
                                        foreach ($series as $id => $serie_title) {
                                            echo "<li>
                                                    <label for=\"$id-ref\">
                                                        <input id=\"$id\" type=\"checkbox\" name=\"reference\" value=\"$id\"/>
                                                        <span> $serie_title</span>
                                                    </label>
                                                </li>";
                                        }
                                        ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
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
                </div>
                <div class="form-group clearfix">
                    <input
                        id="applyBtn"
                        type="submit"
                        formaction="/api/drop.php"
                        value="Apply"
                        class="btn btn-default pull-left"
                    />
                    <div id="recovery" class="hidden">
                        <input
                            id="recoverBtn"
                            type="submit"
                            formaction="/api/recover.php"
                            value="Recover" class="btn btn-default pull-right"
                        />
                        <div class="text-center">
                            <label for="udfCheck">
                                <input id="udfCheck" type="checkbox" name="udf">
                                <span> use PHP</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div id='metrics' class="form-group hidden">
                        <small>
                            <label for="">
                                Runtime:
                                <span id="runtime"></span>
                            </label>
                            <br/>
                            <label for="">
                                RMSE (raw):
                                <span id="rmse"></span>
                            </label><br/>
                            <label for="">
                                RMSE (normal):
                                <span id="rmseNorm"></span>
                            </label><br/>
                            <label for="">
                                MAE (raw):
                                <span id="mae"></span>
                            </label><br/>
                            <label for="">
                                MAE (normal):
                                <span id="maeNorm"></span>
                            </label>
                        </small>
                    </div>
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
        // object to hold chart settings mutations;
        let store = {
            norm: 1,
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

        $('#applyBtn').on('click', function(e) {
            const form = e.target.form;
            form.action = e.target.formAction;
        });

        $('#recoverBtn').on('click', function(e) {
            const form = e.target.form;
            form.action = e.target.formAction;
        });

        function showRecover() {
            $('#recovery').removeClass('hidden');
        }

        function hideRecover() {
            $('#recovery').addClass('hidden');
            hideMetrics();
        }

        function removeComputedLines() {
            const chart = $('#container').highcharts();
            // Important! separate the ones to be deleted
            // https://stackoverflow.com/questions/6604291/proper-way-to-remove-all-series-data-from-a-highcharts-chart
            const toBeRemoved = chart.series.filter((serie, i) => /ground|recovered/i.test(serie.name));
            toBeRemoved.forEach((serie) => {
                if (serie !== undefined) {
                    console.log(`removing ${serie.name} loop 1`);
                    serie.remove(false);
                }
            });
        }

        function showMetrics() {
            $('#metrics').removeClass('hidden');
        }

        function hideMetrics() {
            $('#metrics').addClass('hidden');
        }

        function setMetrics(runtime, rmse, rmse_normal, mae, mae_normal) {
            $('#runtime').text(runtime || 'n/a');
            $('#rmse').text(rmse || 'n/a');
            $('#rmseNorm').text(rmse_normal || 'n/a');
            $('#mae').text(mae || 'n/a');
            $('#maeNorm').text(mae_normal || 'n/a');
        }

        $('#retrieveForm').on('submit', function(e) {
            e.preventDefault();
            const form = e.target;
            const selectedSeries = Array.from(form['series']).filter((next) => next.checked);
            const selectedRefSeries = Array.from(form['reference']).filter((next) => next.checked);
            const series = selectedSeries.map((next) => parseInt(next.value, 10));
            const refSeries = selectedRefSeries.map((next) => parseInt(next.value, 10));
            const visible = store.series.filter((next) => next.visible);
            const { min, max, norm } = store;
            const data = {
                norm,
                start: min,
                end: max,
                series,
                visible,
                threshold: parseFloat(form['threshold'].value, 10),
                drop: parseFloat(form['drop'].value, 10),
                udf: form['udf'].checked,
                reference: refSeries,
            };
            const url = form.action;
            const chart = $('#container').highcharts();
            chart.showLoading('<img src="https://upload.wikimedia.org/wikipedia/commons/b/b1/Loading_icon.gif">');
            requestSeries(url, data)
                .then(response => {
                    if (response.status === 200) {
                        return response.json();
                    }
                    return Promise.reject(response.statusText);
                })
                .then((json) => {
                    console.log(json);
                    removeComputedLines();
                    const {runtime, rmse, rmse_norm, mae, mae_norm} = json;
                    if (runtime || rmse || rmse_norm || mae || mae_norm) {
                        setMetrics(runtime, rmse, rmse_norm, mae, mae_norm);
                        showMetrics();
                    }
                    setSeries(chart, json.series);
                    showRecover();
                    // hack
                    // this is a hack to avoid another api call in afterSetExtremesHandler
                    store = {
                        ...store,
                        flag: true,
                    };
                    chart.redraw();
                    store = {
                        ...store,
                        flag: undefined,
                    };
                    // end hack
                })
                .catch(err => console.error(err))
                .finally(() => chart.hideLoading());
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

       
        function setNorm(norm) {
            store = {
                ...store,
                norm
            };
            const {min, max} = store;
            if (min === undefined || max === undefined) {
                reloadEntireChart(norm);
            } else {
                reloadChartWithExtremes(min, max, norm);
            }
        }

        function setSeries(chart, series, rmse=null, runtime=null, rmse_normal=null, mae=null, mae_normal=null) {
            // adding continues lines
            series.forEach((serie, i) => {
                if(chart.series[i]) {
                    chart.series[i].setData(serie.points, false);
                }
            });
           
            const dropped = series.filter((next) => next.ground !== undefined);
            for (const serie of dropped) {
                const serieId = `${serie.id}-ground`;
                let chartSerie;
                try {
                    chartSerie = chart.get(serieId);
                } catch (err) {
                   console.error(err);
                }
                const data = serie.ground;
                if (chartSerie !== undefined) {
                    console.log(`setData ${serie.title}-ground`);
                    chartSerie.setData(data, false);
                } else {
                    console.log(`adding ${serie.title}-ground`);
                    const color = chart.get(parseInt(serie.id, 10)).color; // maintain color
                    chart.addSeries({
                        id: serieId,
                        name: `${serie.title}-ground`,
                        color,
                        visible: false,
                        dashStyle: 'ShortDot',
                        data, 
                    }, false);
                }
            }
            const recovered = series.filter((next) => next.recovered !== undefined && next.ground !== undefined);
            for (const serie of recovered) {
                const serieId = `${serie.id}-recovered`;
                let chartSerie;
                try {
                    chartSerie = chart.get(serieId);
                } catch (err) {
                    console.error(`chart.get failed for id: ${serieId}`);
                }
                const data = serie.recovered;
                if (chartSerie !== undefined) {
                    console.log(`setData ${serie.title}-recovered`);
                    chartSerie.setData(data, false);
                } else {
                    console.log(`adding ${serie.title}-recovered`);
                    const color = 'red';
                    chart.addSeries({
                        id: serieId,
                        name: `${serie.title}-recovered`,
                        color,
                        dashStyle: 'ShortDot',
                        data, 
                    }, false);
                }
            }
        }

        function loadChart(query) {
            var chart = $('#container').highcharts();
            chart.showLoading('<img src="https://upload.wikimedia.org/wikipedia/commons/b/b1/Loading_icon.gif">');
            $.getJSON(query, function (data) {
                var explore_object = data[0];
                console.log('Explore Query Response: ', explore_object);
                explore_object.series.forEach(function (series, i) {
                    chart.series[i].setData(series.points, false);
                });
                chart.hideLoading();
                hideRecover();
                removeComputedLines();
                chart.redraw();
            });
        }

    
        function reloadChartWithExtremes(min, max, norm) {
            var query = 'explore_query.php?dataset=<?php echo $dataset; ?>&norm=' + norm + '&start=' + min + '&end=' + max + '&callback=?';
            loadChart(query);
        }

        function reloadEntireChart(norm) {
            var query = 'explore_query.php?dataset=<?php echo $dataset; ?>&norm=' + norm + '&callback=?';
            loadChart(query);
        }


        /// CHART EVENT HANDLERS
        ////////////////////////

        // handles chart legend item clicks
        function legendItemClickHandler(e) {
            const visible = !e.target.visible; // toggle
            for (const serie of store.series) {
                if (serie.name === e.target.name) {
                    serie.visible = visible;
                    break;
                }
            }
            console.log(`${e.target.name} click!`);
        }

        /**
         * Updates min and max
         */
        function afterSetExtremesHandler(e) {
            console.log('[afterSetExtremesHandler] -- ', e);
            const min = Math.round(e.min);
            const max = Math.round(e.max);
            store = {
                ...store,
                min,
                max,
            };
            const { norm, flag } = store;
            // flag set after drop or recovery has being called
            // avoids overlaying new lines
            if (flag === undefined) {
                reloadChartWithExtremes(min, max, norm);
            }
        }

        // See source code from the JSONP handler at https://github.com/highcharts/highcharts/blob/master/samples/data/from-sql.php
        $.getJSON('explore_query.php?dataset=<?php echo $dataset; ?>&norm=' + store.norm + '&callback=?', function (data) {
            var explore_object = data[0];
            var renderedSeries = [];
            var visibility = true;
            const storeSeries = [];

            explore_object.series.forEach(function (series) {
                storeSeries.push({
                    id: parseInt(series.id, 10),
                    name: series.title,
                    visible: true,
                });
                renderedSeries.push({
                    id: parseInt(series.id, 10),
                    type: 'line',
                    visible: visibility,
                    name: series.title,
                    data: series.points,
                    dataGrouping: {enabled: false}
                });

            });

            store = {
                ...store,
                series: storeSeries,
            };

            // create the chart
            var chart = $('#container').highcharts('StockChart', {

                chart: {
                    type: 'line',
                    zoomType: 'x',
                    events: {
                        load: function (e) {
                            try {
                                console.log('[load]')
                                console.log(e);
                                const firstSerie = e.target.series[0]
                                const min = firstSerie.xAxis.dataMin;
                                const max = firstSerie.xAxis.dataMax;
                                console.log(`[load] -- min=${min} max=${max}`);
                                store = {
                                    ...store,
                                    min,
                                    max,
                                };
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
