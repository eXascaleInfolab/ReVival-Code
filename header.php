<html>
<head>
    <meta http-equiv="content-type" content="text/html; charset=UTF-8">
    <!-- JavaScript sources-->
    <script src="//code.jquery.com/jquery-1.10.2.js"></script>
    <script src="https://code.highcharts.com/stock/highstock.js"></script>
    <script src="https://code.highcharts.com/stock/modules/exporting.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js"
            integrity="sha384-0mSbJDEHialfmuBBQP6A4Qrprq5OVfW37PRR3j5ELqxss1yVqOtnepnHVP9aJ7xS"
            crossorigin="anonymous"></script>
    <script src="//code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
    <script src="/functions.js"></script>
    <!-- CSS sources -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css"
          integrity="sha384-1q8mTJOASx8j1Au+a5WDVnPi2lkFfwwEAa8hDDdjZlpLegxhjVME1fgjWPGmkzs7" crossorigin="anonymous">
    <link rel="stylesheet" href="/css/custom.css">
    <link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css" integrity="sha384-50oBUHEmvpQ+1lW4y57PTFmhCaXp0ML5d60M1M7uH2+nqUivzIebhndOJK28anvf" crossorigin="anonymous">
    <title>ReVival</title>
</head>
<body>
<!-- Navigation -->
<nav class="navbar navbar-inverse navbar-fixed-top" role="navigation">
    <div class="container">
        <!-- Brand and toggle get grouped for better mobile display -->
        <div class="navbar-header">
            <button type="button" class="navbar-toggle" data-toggle="collapse"
                    data-target="#bs-example-navbar-collapse-1">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="/">ReVival</a>
        </div>
        <!-- Collect the nav links, forms, and other content for toggling -->
        <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
            <ul class="nav navbar-nav">
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true"
                       aria-expanded="false">
                        Display<span class="caret"></span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="/display/datasets.php">Datasets</a></li>
                        <li><a href="/display/datastream.php">Data stream</a></li>
                    </ul>
                </li>
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true"
                       aria-expanded="false">
                        Recovery<span class="caret"></span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="/recovery/static.php">Toy example</a></li>
                        <li><a href="/recovery/recovdb.php">Database recovery (RecovDB)</a></li>
                        <li><a href="/recovery/datasets.php">Real-world (batch)</a></li>
                        <li><a href="/streaming/datastream.php">Real-world (streaming)</a></li>
                        <li><a href="/cd/recovery.php">Matrix</a></li>
                    </ul>
                </li>
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true"
                       aria-expanded="false">
                        Centroid Decomposition<span class="caret"></span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="/cd/matrixdec.php">Decomposition calculator</a></li>
                        <li><a href="/cd/streaming.php">Streaming decomposition</a></li>
                        <li><a href="/cd/signvectors.php">Sign vector strategies</a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/about.php">
                        About
                    </a>
                </li>
            </ul>
        </div>
        <!-- /.navbar-collapse -->
    </div>
    <!-- /.container -->
</nav>
