<?php
$page_title = "Data stream example";
include '../header.php';
?>

<div class="container">
    <div id="chart" class="col-md-12">
        <div class="page-header">
            <h2>Continuous
                <small>recovery of missing values in time series</small>
            </h2>
        </div>
        <div id="container" style="width:100%; height: 70%; margin: 0 auto"></div>
    </div>

</div>
<script src="../algebra.js"></script>
<script type='text/javascript'>

    // seeded PRNG, code from: https://stackoverflow.com/questions/521295/seeding-the-random-number-generator-in-javascript
    var m_w = 123456789;
    var m_z = 987654321;
    var mask = 0xffffffff;

    // Takes any integer
    function seed(i) {
        m_w = i;
        m_z = 987654321;
    }

    // Returns number between 0 (inclusive) and 1.0 (exclusive),
    // just like Math.random().
    function pseudorandom() {
        m_z = (36969 * (m_z & 65535) + (m_z >> 16)) & mask;
        m_w = (18000 * (m_w & 65535) + (m_w >> 16)) & mask;
        let result = ((m_z << 16) + m_w) & mask;
        result /= 4294967296;
        return result + 0.5;
    }

    // globals
    var matrix = [];
    var matrixMask = [];
    const updateTickSize = 5;
    var intervalId;

    $(function () {
        let retrieve_object;

        let counter = 50; // before any updates this is "initial size"
        let chartSize = counter;
        let chartCut = 0;

        let query = 'datastream_query.php?callback=?';

        $.getJSON(query, function (data) {
            retrieve_object = data[0];
            let renderedSeries = [];
            let visibility = true;

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

            renderedSeries.push({
                type: 'line',
                visible: visibility,

                name: "Recovered block",
                data: retrieve_object.reference_series[0].points.slice(0, counter).map((arr) => [arr[0], null]),
                dashStyle: 'shortdot',
                color: 'red',
                marker: {
                    enabled: false
                },
                dataGrouping: {enabled: false}
            });

            /*
            renderedSeries.push({
                type: 'line',
                visible: false,
                name: retrieve_object.reference_series[0].title + " (ground truth)",
                data: retrieve_object.reference_series[0].points.slice(0, counter).map((arr) => [arr[0], null]),
                color: 'red',
                marker: {
                    enabled: false
                },
                dataGrouping: {enabled: false}
            });
            */

            // create the chart
            $('#container').highcharts('StockChart', {
                colors: ['#ff0000', '#50B432', '#4450df', '#24CBE5', '#64E572', '#FF9655', '#FFF263', '#6AF9C4'],

                chart: {
                    type: 'line',
                    events: {
                        load: function () {
                            let removeOnce = () => {
                                this.series[0].data[0].remove(false, false);//TS1
                                this.series[1].data[0].remove(false, false);//TS2
                                this.series[2].data[0].remove(false, false);//TS3
                                this.series[3].data[0].remove(false, false);//TS1-rec
                                chartSize--;
                                chartCut++;
                            };

                            // step 1 - make a matrix to decompose
                            for (let i = 0; i < retrieve_object.reference_series[0].points.length; i++) {
                                matrix.push([]);
                                for (let j = 0; j < 3; j++) {
                                    matrix[i].push(retrieve_object.reference_series[j].points[i][1]);
                                    matrixMask.push(true);
                                }
                            }

                            // step 2 - mutilate matrix
                            function missTheValue(i) {
                                matrix[i][0] = null;
                                matrixMask[i] = false;
                            }

                            seed(1711);
                            for (let i = counter; i < matrix.length; i++) {
                                let mod = matrixMask[i - 1] ? 0.1 : 0.0;
                                let mod2 = matrixMask[i - 2] && matrixMask[i - 3] && matrixMask[i - 4] ? -0.12 : 0;

                                if (counter < 80) {
                                    if (pseudorandom() < 0.17 + mod - mod2) missTheValue(i);
                                } else if (counter < 120) {
                                    if (pseudorandom() < 0.20 + mod - mod2) missTheValue(i);
                                } else {
                                    if (pseudorandom() < 0.27 + mod - mod2) missTheValue(i);
                                }
                            }

                            // step 3 set up the updating of the chart each second
                            let series = this.series;
                            var chartUpdateTickCallback = function () {
                                // step 3.1 - we recover everything that's missing in the matrix[0...current+next_tick]
                                //            this way the values are already recovered for future use

                                RMV(matrix.slice(0, counter + updateTickSize), 0, 0.01, 1);
                                //        [!] inner arrays in matrix are passed by reference

                                // step 3.2 - fill in the chart
                                for (let i = 0; i < updateTickSize; i++) {
                                    series[1].addPoint(retrieve_object.reference_series[1].points[counter]);
                                    series[2].addPoint(retrieve_object.reference_series[2].points[counter]);

                                    let point_main = retrieve_object.reference_series[0].points[counter];
                                    let point_rec = [point_main[0], point_main[1]];

                                    if (!matrixMask[counter]) { // missing
                                        point_rec[1] = matrix[counter][0];
                                        point_main[1] = null;

                                        if (matrixMask[counter - 1]) // last one isn't missing; need to concat "missing TSs"
                                        {
                                            let time = series[3].points[counter - 1 - chartCut].x;
                                            series[3].removePoint(counter - 1 - chartCut);
                                            series[3].addPoint([time, matrix[counter - 1][0]]);
                                        }
                                    } else {
                                        if (matrixMask[counter - 1]) // last one was missing; need to duplicate "missing TSs" here
                                        {
                                            point_rec[1] = null;
                                        }
                                    }

                                    series[0].addPoint(point_main);
                                    series[3].addPoint(point_rec);

                                    counter++;
                                    chartSize++;
                                }

                                if (chartSize >= updateTickSize * 33) {
                                    for (let i = 0; i < updateTickSize * 6; i++) {
                                        removeOnce();
                                    }
                                }

                                if (counter >= retrieve_object.reference_series[0].points.length - updateTickSize * 2) clearInterval(intervalId);
                            };

                            intervalId = setInterval(chartUpdateTickCallback, 1250);
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
                    opposite: false
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
