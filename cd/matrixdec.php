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
                    <h2>Matrix Decomposition</h2>
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
                    $start_compute = microtime(true);
                    $result = CD($mat);
                    $time_elapsed = (microtime(true) - $start_compute) * 1000;
                    ?>
                    <div class="col-md-<?php echo $m > 6 ? 12 : 6 ?>">
                        <h3>Input matrix:</h3>
                        <h4>X:</h4>
                        <pre style="font-size:9pt;"><?php print_array_pre($mat); ?></pre>
                        <br/>
                    </div>
                    <div class="col-md-<?php echo $m > 6 ? 12 : 6 ?>">
                        <h3>Centroid Decomposition:</h3>
                        <h4><strong>L:</strong></h4>
                        <pre style="background:#FCFCF0;font-size:9pt;"><?php print_array_pre($result[0]); ?></pre>
                        <br/>
                        <h4><strong>R<sup>T</sup></strong>:</h4>
                        <pre style="background:#FCFCF0;font-size:9pt;"><?php print_array_pre(trsp($result[1])); ?></pre>
                        <br/>
                        <h4>Max. sign vectors</h4>
                        <?php
                        echo "<pre style=\"background:#FCFCF0;font-size:9pt;\">";
                        print_pre_headers("Z", count($result[2][0]));
                        echo "\n";
                        print_array_pre($result[2]);
                        echo "</pre>";
                        ?>
                    </div>
                    <br/>
                    <p>Execution time: <?php echo round($time_elapsed, 3) ?>ms.</p>
                    <br/>
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
                        echo "<p>length of row 1 (= <span style=\"color: #0000FF;\">$m</span>) doesn't match length of row $problemrow ( = <span style=\"color: #FF0000;\">". count($mat[$problemrow-1]) . "</span>)</p>";
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
                    <h2>Matrix Decomposition</h2>
                </div>
            </div>
            <div class="col-md-12">
                <h3>Matrix</h3>
                <h4>
                    <button id="descr-show" type="button" class="btn btn-default btn-xs">
                        <span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span> show description
                    </button>
                    <button style="display: none;" id="descr-hide" type="button" class="btn btn-default btn-xs">
                        <span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span> hide description
                    </button>
                </h4>
                <div style="display:none;" id="descr-options">
                    <p>Matrix is</p>
                    <ul>
                        <li>comma-separated;</li>
                        <li>maximum size is 100x100.</li>
                    </ul>
                </div>
                <form id="query" action="">
            <textarea name="matrix" form="query" class="form-control" rows=15 title="matrixinput">2,-1,3
-1,3,-1
2,-6,-4
-1,3,3</textarea>
                    <br>
                    <input class="btn" type="submit" value="Decompose!"/>
                    <script>
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
