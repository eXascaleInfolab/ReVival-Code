<?php
$page_title = "Matrix Decomposition";
include '../header.php';

$matrix = @$_GET['matrix'];

// CASE 1 - there's a request to decompose a matrix
if (isset($matrix)) {
    include '../algebra.php';
    $mat = array_map('str_getcsv', str_getcsv($matrix, "\n"));
    ?>

    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="page-header">
                    <h2>Recovery
                        <small>of missing values inside a matrix</small>
                    </h2>
                </div>
            </div>
            <div class="col-md-12">
                <?php

                $n = count($mat);
                $m = count($mat[0]);

                $x = init_array($n, $m);

                $match = true;
                $problemrow = 0;

                for ($i = 1; $i < $n; $i++) {
                    if (count($mat[$i]) != $m) {
                        $match = false;
                        $problemrow = $i;
                        break;
                    }
                }

                if ($n <= 100 && $m <= 100 && $match) {

                    for ($i = 0; $i < $n; $i++) {
                        for ($j = 0; $j < $m; $j++) {
                            if ($mat[$i][$j] === "null" || $mat[$i][$j] === "NULL" || $mat[$i][$j] === "NaN" || $mat[$i][$j] === "nan" || $mat[$i][$j] === "NAN") {
                                $x[$i][$j] = NULL;
                            } else {
                                $x[$i][$j] = floatval($mat[$i][$j]);
                            }
                        }
                    }

                    $threshold = @$_GET['threshold'];
                    $truncation = @$_GET['truncation'];

                    if (!isset($threshold)) $threshold = 0.01;
                    if (!isset($truncation)) $truncation = 1;

                    $start_compute = microtime(true);
                    $result = RMV_all($x, $threshold, $truncation);

                    $time_elapsed = (microtime(true) - $start_compute) * 1000;
                    ?>
                    <div class="col-md-<?php echo $m > 6 ? 12 : 6 ?>">
                        <h3>Input matrix:</h3>
                        <pre style="font-size:9pt;"><?php print_array_pre($x); ?></pre>
                        <br/>
                    </div>
                    <div class="col-md-<?php echo $m > 6 ? 12 : 6 ?>">
                        <h3>Recovered matrix:</h3>
                        <pre style="background:#FCFCF0;font-size:9pt;"><?php print_array_pre($result[0]); ?></pre>
                        <br/>
                    </div>
                    <p>Execution time: <?php echo round($time_elapsed, 3) ?>ms.</p>
                    <?php
                } else if ($match) {
                    ?>
                    <h3>No results</h3>
                    <p>Matrix has exceeded capacity.</p>
                    <p>
                        <?php
                        if ($m > 100) {
                            echo "<p>M = <span style=\"color: #FF0000;\">$m</span> > <span style=\"color: #0000FF;\">100</span></p>";
                        }
                        if ($n > 100) {
                            echo "<p>N = <span style=\"color: #FF0000;\">$n</span> > <span style=\"color: #0000FF;\">100</span></p>";
                        }
                        ?>
                    </p>
                    <?php
                } else {
                    ?>
                    <h3>No results</h3>
                    <p>Matrix rows don't have the same length.</p>
                    <p>
                        <?php
                        $problemrow++;
                        echo "<p>length of row 1 (= <span style=\"color: #0000FF;\">$m</span>) doesn't match length of row $problemrow ( = <span style=\"color: #FF0000;\">" . count($mat[$problemrow - 1]) . "</span>)</p>";
                        ?>
                    </p>
                    <?php
                }
                ?>
            </div>
        </div>
    </div>

    <?php
} // CASE 2:
else {
    ?>
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="page-header">
                    <h2>Recovery
                        <small>of missing values inside a matrix</small>
                    </h2>
                </div>
            </div>
            <div class="col-md-12">
                <h3 style="padding-left: 15px">Matrix</h3>
                <h4 style="padding-left: 15px">
                    <button id="descr-show" type="button" class="btn btn-default btn-xs">
                        <span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span> show description
                    </button>
                    <button style="display: none;" id="descr-hide" type="button" class="btn btn-default btn-xs">
                        <span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span> hide description
                    </button>
                </h4>
                <div style="display:none;padding-left: 15px" id="descr-options">
                    <p>Matrix is</p>
                    <ul>
                        <li>comma-separated;</li>
                        <li>missing values are designated as "null" or "NaN";</li>
                        <li>maximum size is 100x100.</li>
                    </ul>
                </div>
                <form id="query" action="">
                    <div class="col-md-5" style="padding-top: 30px;">
                        <textarea name="matrix" form="query" class="form-control" rows=15 title="matrixinput">-12,8,-4,-8
0,0,0,0
-48,32,-16,-32
null,64,-32,-64
null,24,-12,-24
null,64,-32,-64
null,16,-8,-16
null,8,-4,-8
null,-32,16,32
null,8,-4,-8
12,-8,4,8
null,-24,12,24
null,16,-8,-16
-12,8,-4,-8
24,-16,8,16</textarea>
                    </div>
                    <div class="col-md-4" style="padding-top: 30px;">
                    </div>
                    <div class="col-md-3" style="padding-top: 30px;">
                        <h4>
                            <button id="prm-show" type="button" class="btn btn-default btn-xs">
                                <span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span> show parameters
                            </button>
                            <button style="display: none;" id="prm-hide" type="button" class="btn btn-default btn-xs">
                                <span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span> hide parameters
                            </button>
                        </h4>
                        <div style="display: none" id="prm-options">
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
                                <label>Truncation factor for CD:</label>
                                <select class="form-control" id="truncation" name="truncation">
                                    <option selected="selected">1</option>
                                    <option>2</option>
                                    <option>3</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-12" style="padding-top: 30px;">
                        <input class="btn" type="submit" value="Recover!"/>
                    </div>
                    <script>
                        $('#prm-show').click(function () {
                            $('#prm-show').hide();
                            $('#prm-hide').show();
                            $('#prm-options').show();
                        });
                        $('#prm-hide').click(function () {
                            $('#prm-hide').hide();
                            $('#prm-options').hide();
                            $('#prm-show').show();
                        });
                        $('#descr-show').click(function () {
                            $('#descr-show').hide();
                            $('#descr-hide').show();
                            $('#descr-options').show();
                        });
                        $('#descr-hide').click(function () {
                            $('#descr-hide').hide();
                            $('#descr-options').hide();
                            $('#descr-show').show();
                        });
                    </script>
                </form>
            </div>
        </div>
    </div>
    <?php
}
?>

<?php include '../footer.php'; ?>
