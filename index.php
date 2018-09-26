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
        <div class="col-md-4">
            <h3>About this tool</h3>

            <p><b>Technology</b><br>
                ReVival is a client-server application. The client-side is implemented using <a
                        href="https://www.javascript.com/">JavaScript</a>, <a href="https://jquery.com/">jQuery</a>, <a
                        href="http://www.highcharts.com/">Highcharts</a> (a JavaScript library for charts) and
                HTML/CSS. The server-side consists of a <a href="http://monetdb.org/">MonetDB</a> database
                and uses <a href="http://php.org/">PHP</a> for preprocessing the data. </p>
            <p><b>Contact</b><br>
                <a href="https://exascale.info/members/mourad-khayati/">Mourad Khayati, PhD</a>,
                <a href="mailto:zakhar.tymchenko@unifr.ch">Zakhar Tymchenko</a>,
                Oliver Stapleton.</p>
            <div style="text-align: center;">
                <a href="https://exascale.info/"><img style="width: 60%;height: auto;"
                                                      src="/resources/Xi_logo.svg"/></a>
                &nbsp;&nbsp;
                <a href="http://monetdb.org/"><img style="width: 35%;height: auto;"
                                                   src="/resources/monetdb-final-500.png"/></a>
            </div>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>
