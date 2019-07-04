<?php
include 'header.php';
?>
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div class="page-header">
                <h1>ReVival
                    <small>Recovery of missing values using Centroid Decomposition</small>
                </h1>
            </div>
            <p>
                ReVival is an online tool to recover missing blocks in batches and streams of time series using the
                Centroid Decomposition (CD) and to visualize the properties of the CD algorithm.
            </p>
            <p>
                This tool was created at the <a href="http://exascale.info/">eXascale Infolab</a>, a research group
                in the <a href="http://www.unifr.ch/">University of Fribourg</a>, Switzerland.
            </p>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <h3>Centroid Decomposition</h3>

            <p>The <a href="https://ieeexplore.ieee.org/document/6816643/">Centroid Decomposition</a> (CD) is a matrix
                decomposition technique that factorizes an input matrix <b>X</b> (consisting of multiple time-series
                as columns) into the product of two matrices <b>L</b> (loading matrix) and <b>R</b>
                (relevance matrix), such that <b>X</b> = <b>L</b> * <b>R<sup>T</sup></b>. CD allows to efficiently
                perform recovery of missing values in large time series, both in batch mode and streaming mode. The
                batch recovery uses SSV algorithm introduced <a href="https://ieeexplore.ieee.org/document/6816643/">here</a>.
                The streaming recovery uses an efficient incremental version of the CD algorithm.
            </p>
            <p>
                In addition to the recovery, ReVival can be used as an online calculator to compute the Centroid
                Cecomposition and to visualize different sign vector maximization strategies that can be used by CD.
            </p>
            <hr/>

            <p>ReVival consists of the following components:</p>

            <div class="row">
                <div class="col-md-3">
                    <p><b>Display:</b></p>
                    <ul>
                        <li><a href="/display/datasets.php">Data sets</a></li>
                        <li><a href="/display/datastream.php">Data stream</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <p><b>Recovery:</b></p>
                    <ul>
                        <li><a href="/cd/recovery.php">Matrix data</a></li>
                        <li><a href="/recovery/static.php">Synthetic data</a></li>
                        <li><a href="/recovery/datasets.php">Real-world batch data</a></li>
                        <li><a href="/streaming/datastream.php">Real-world streaming data</a></li>
                    </ul>
                </div>
                <div class="col-md-5">
                    <p><b>Centroid Decomposition:</b></p>
                    <ul>
                        <li><a href="/cd/matrixdec.php">Decomposition calculator</a></li>
                        <li><a href="/cd/streaming.php">Streaming decomposition</a></li>
                        <li><a href="/cd/signvectors.php">Sign vector strategies</a></li>
                    </ul>
                </div>
            </div>

            <h3>Publications</h3>

            <ul>
                <li>Ines Arous, Mourad Khayati, Philippe Cudré-Mauroux, Ying Zhang, Martin Kersten, and Svetlin Stalinlov. <strong>“RecovDB: Accurate and Efficient Missing Blocks Recovery for Large Time Series.”</strong> In <i>35th IEEE International Conference on Data Engineering (ICDE 2019)</i>. Macau, China, 2019.</li>
                <li>Mourad Khayati, Michael H. Böhlen, and Johann Gamper. <strong>“Memory-Efficient Centroid Decomposition for Long Time Series.”</strong> In <i>IEEE 30th International Conference on Data Engineering (ICDE 2014)</i>, Chicago, ICDE 2014, IL, USA, March 31 - April 4, 2014, 100–111, 2014.</li>
            </ul>
        </div>
        <div class="col-md-4">
            <h3>Teams involved:</h3>

            <div style="text-align: center;">
                <a href="https://exascale.info/"><img style="width: 60%;height: auto;"
                                                      src="/resources/Xi_logo.svg"/></a>
                &nbsp;&nbsp;
                <a href="http://monetdb.org/"><img style="width: 35%;height: auto;"
                                                   src="/resources/mdbs_logo.png"/></a>
            </div>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>
