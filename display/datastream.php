<?php
$page_title = "Data stream example";
include '../header.php';
?>

<div class="container">
    <div id="chart" class="col-md-12">
        <div class="page-header">
            <h2>Streams
                <small>of time series</small>
            </h2>
        </div>
        <div id="container" style="width:100%; height: 70%; margin: 0 auto"></div>
    </div>

</div>

<script type='text/javascript'>
    $(function () {
        var retrieve_object;

        var counter = 20;

        var query = 'datastream_query.php?callback=?';

        $.getJSON(query, function (data) {
            retrieve_object = data[0];
            var renderedSeries = [];
            var visibility = true;

            retrieve_object.reference_series.forEach(function (series) {

                renderedSeries.push({
                    type: 'line',
                    visible: visibility,
                    name: series.title,
                    data: series.points.slice(0, counter),
                    marker: {
                        enabled: false
                    },
                    dataGrouping: {enabled: false}
                });

            });

            // create the chart
            $('#container').highcharts('StockChart', {
                colors: ['#058DC7', '#50B432', '#DDDF00', '#24CBE5', '#64E572', '#FF9655', '#FFF263', '#6AF9C4'],

                chart: {
                    type: 'line',
                    events: {
                        load: function () {
                            // set up the updating of the chart each second
                            var series = this.series;
                            setInterval(function () {
                                for (var i = 0; i < 5; i++) {
                                    series[0].addPoint(retrieve_object.reference_series[0].points[counter]);
                                    series[1].addPoint(retrieve_object.reference_series[1].points[counter]);
                                    series[2].addPoint(retrieve_object.reference_series[2].points[counter]);
                                    counter++;
                                }
                            }, 1000);
                            //}
                        }
                    }
                },

                navigator: {
                    enabled: false
                },

                rangeSelector: {
                    enabled: false
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
                    align: 'right',
                    verticalAlign: 'top'
                },
                yAxis: {
                    opposite: false,
                    labels : {
                        style : { "fontSize" : "15px", "font-weight" : "bold" }
                    }
                },
                xAxis : {
                    labels : {
                        style: { "fontSize" : "15px" }
                    }
                },
                credits: {
                    enabled: false,
                },
                title: {
                    text: null
                },
                scrollbar: {
                    enabled: false
                }
            });
        });

    });
</script>

<?php
include '../footer.php';
?>
