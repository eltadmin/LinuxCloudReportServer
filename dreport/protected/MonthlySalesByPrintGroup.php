<?php
session_start();
if (!isset($_SESSION['s_authenticated']) || empty($_SESSION['s_authenticated'])) {
    header("location:../index.php");
}

define("C_DEBUG", false);  // Set to true for debugging
define("C_BAR_HEIGHT", 20);
define("C_CANVAS_HEIGHT", 30);
define("C_RPTNAME", "monthlysalesbyprintgroup");
define("C_TIMEOFFSET", 0);

// param START_DATE, END_DATE
$C_SQL = '{ "Id":"DatabaseId", "Pass":"1234", "MonthlySalesByPrintGroup": { "Type":"Query", "SQL":"SELECT PLUES.PLU_PRINTDEVICE_ID as \\"NoГрупа\\", N_PRINTERGROUPS.PRN_NAME as \\"Група печат\\", SUM(SALES_PLUES.SPLU_SOLDQUANT * (SALES_PLUES.SPLU_SELLPRICE + (SALES_PLUES.SPLU_SELLPRICE * SALES_PLUES.SPLU_SELLDISCOUNT / 100))) as \\"Сум.Прод.Цена\\" FROM SALES_PLUES LEFT JOIN PLUES ON SALES_PLUES.SPLU_PLUNUMB = PLUES.PLU_NUMB LEFT JOIN N_PRINTERGROUPS ON PLUES.PLU_PRINTDEVICE_ID = N_PRINTERGROUPS.PRN_ID WHERE SPLU_DATETIME >= START_DATE AND SPLU_DATETIME < END_DATE AND SPLU_REVOKED_ = 0  GROUP BY 1, 2 ORDER BY 3 DESC" } }';

$otimeoffset = C_TIMEOFFSET;
$rname = C_RPTNAME;

include_once 'language.php';
// show loader for slow connections
require_once('class.loading.div.php');
$divLoader = new loadingDiv;
$divLoader->loader();

$objectid = "";
if (isset($_GET["id"])) {
    $objectid = $_GET["id"];
} else {
    $objectid = $_SESSION['s_objectid'];
}
$_SESSION['s_objectid'] = $objectid;

if (isset($_SESSION['s_deviceid'])) {
    $deviceid = $_SESSION['s_deviceid'];
} else {
    $deviceid = "0";
}

// get date parameter for sql
$rptdate = "";
if (isset($_GET["date"])) {
    $rptdate = $_GET["date"];
}

$chartBars = 1;
$TurnoverLabel = '';
$TurnoverData = '';
$TurnoverCurrency = '';

include('database.class.php');
$pDatabase = Database::getInstance();
$result = $pDatabase->query("set names 'utf8'");

//check expire date
$qry = $pDatabase->query("SELECT s_customername, s_expiredate FROM t_subscriptions WHERE s_objectid = '" . sql_safe($_SESSION['s_objectid']) . "'");
while ($row = mysqli_fetch_assoc($qry)) {
    $expiredate = strtotime($row['s_expiredate']);
    $customername = $row['s_customername'];
}

$objectpswd = "";
$rptsql = "";

$rname = C_RPTNAME;

$rptsql = $C_SQL;

$qry = $pDatabase->query("select d_objectpswd, d_timeoffset from t_devices where d_deviceid = '$deviceid' and d_objectid ='" . sql_safe($_SESSION['s_objectid']) . "';");

while ($row = mysqli_fetch_assoc($qry)) {
    $objectpswd = $row['d_objectpswd'];
    $otimeoffset = $row['d_timeoffset'];
}
if (C_DEBUG) {
    echo "Time offset: ";
    var_dump($otimeoffset);
    echo "<br/>";
}

//replace timoffset parameter in SQL
$rptsql = str_replace('TIMEOFFSET', '\'' . $otimeoffset . '\'', $rptsql);

//replace date parameters in SQL
if ($rptdate == ''){
    $dt = new DateTime();
    $rptdate = $dt->format('Y-m-d');
}
$start_date = new DateTime($rptdate);
$start_date->modify('first day of this month');
$end_date = clone $start_date;
$end_date->modify('last day of this month');

$rptsql = str_replace('START_DATE', '\'' . $start_date->format('Y-m-d 00:00:00') . '\'', $C_SQL);
$rptsql = str_replace('END_DATE', '\'' . $end_date->format('Y-m-d 23:59:59') . '\'', $rptsql);


if (C_DEBUG) {
    echo "report date: ";
    var_dump($rptdate);
    echo "<br/>";
}

$pDatabase->logevent(OPER_COMMAND, $objectid, 'report: ' . $rname . ' objectid=' . $objectid);

$url = 'http://' . $_SESSION['s_rpt_server_host'] . ':' . $_SESSION['s_rpt_server_port'] . '/report/' . $rname . '/?id=' . $objectid . '&u=' . $_SESSION['s_rpt_server_user'] . '&p=' . $_SESSION['rpt_server_pswd'];
if (C_DEBUG) {
    echo "url: ";
    var_dump($url);
    echo "<br/>";
}

// set ObjectId and password
$obj = json_decode($rptsql);
$obj->{"Id"} = $objectid;
$obj->{"Pass"} = $objectpswd;
$rptsql = json_encode($obj);

if (C_DEBUG) {
    echo "content: ";
    var_dump($rptsql);
    echo "<br/>";
}
$options = array(
    'http' => array(
        'timeout' => 45, //timeout in seconds
        'header'  => "Content-type: text/xml\r\n",
        'method'  => 'GET',
        'content' => $rptsql
    ),
);
$context  = stream_context_create($options);
$str = @file_get_contents($url, false, $context);
if (C_DEBUG) {
    echo "response: ";
    var_dump($str);
    echo "<br/>";
}

// initialize default error values
$TurnoverLabel = '""';
$TurnoverData = '"0"';
if (!$str) {
    //show error message
    $pDatabase->show_alert(ALERT_ERROR, $lang["AlertError"], $lang["rptNoReceivedData"], false, false);
    $pDatabase->logevent(OPER_ERROR, $objectid, 'report: ' . $rname . ' error: ' . $lang["rptNoReceivedData"]);
} else {
    $rptdata = @json_decode($str, false);
    if (C_DEBUG) {
        echo "rptdata: ";
        var_dump($rptdata);
        echo "<br/>";
    }

    // Debugging: print the structure of the received data
    if (C_DEBUG) {
        echo "<pre>";
        print_r($rptdata);
        echo "</pre>";
    }

    if ($rptdata == null && json_last_error() !== JSON_ERROR_NONE) {
        $pDatabase->show_alert(ALERT_ERROR, $lang["AlertError"], $lang["rptInvalidData"], false, false);
        $pDatabase->logevent(OPER_ERROR, $objectid, 'report: ' . $rname . ' error: ' . $lang["rptInvalidData"]);
    } else {
        //continue only if "ResultCode": 0,
        if ($rptdata->ResultCode == 0) {
            $chartBars = 1;
            $TurnoverLabel = '';
            $TurnoverData = '';
            $TurnoverCurrency = '';
if (!empty($rptdata->MonthlySalesByPrintGroup)) {
    $chartBars = count($rptdata->MonthlySalesByPrintGroup);
}
foreach ($rptdata->MonthlySalesByPrintGroup as $item) {
    if (isset($item->{"ГРУПА ПЕЧАТ"}) && isset($item->{"СУМ.ПРОД.ЦЕНА"})) {
        $TurnoverLabel .= '"' . str_replace('"', "'", $item->{"ГРУПА ПЕЧАТ"}) . '",';
        $TurnoverData  .= number_format($item->{"СУМ.ПРОД.ЦЕНА"}, 2, '.', '') . ',';
    }
}
$TurnoverLabel = rtrim($TurnoverLabel, ",");
$TurnoverData = rtrim($TurnoverData, ",");


        } else {
            $pDatabase->show_alert(ALERT_ERROR, $lang["AlertError"], $pDatabase->getTCPErrorMessage($rptdata->ResultCode, $lang), false, false);
            $pDatabase->logevent(OPER_ERROR, $objectid, 'report: ' . $rname . ' ResultCode: ' . $rptdata->ResultCode . ' ResultMessage: ' . $rptdata->ResultMessage);
        }
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=EmulateIE11" />
    <title>Detelina Reports</title>
    <link rel="stylesheet" href="css/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="css/w3.css">
    <script src="js/Chart.min.js"></script>
    <link rel="stylesheet" href="css/box.css">
    <script type="text/javascript" src="js/jquery.min.js"></script>
    <script type="text/javascript" src="js/jquery.alerts.js"></script>
	<style>
    .button {
      display: inline-block;
      border-radius: 20px;
      font-size: 14px;
      text-align: center;
      color: white;
      transition: background-color 0.3s ease;
      box-shadow: rgb(38, 70, 83) 0px 11px 8px -5px;
    }
    .color-1 { background-color: #a085e0; }
    .color-5 { background-color: #FBB03B; }
    .color-1:hover, .color-1:active  {
      background-color: #6c55a5;  
    }  
    .color-5:hover, .color-5:active  {
      background-color: #e18e0a;  
    }
  </style>
</head>

<body>
    <?php
    //show message for expired account
    if ($expiredate < time()) {
        echo '<meta http-equiv="X-UA-Compatible" content="IE=EmulateIE11" />';
        echo '<div class="alert-box warning" id="alert-box-exp" style= "display : none">';
        echo '<span>' . $lang["AlertWarning"] . '</span>' . $lang["errObjectExpired"] . date("d.m.Y", $expiredate);
        echo '</div>';
        echo '<script>';
        echo 'function show_exp() {document.getElementById("alert-box-exp").removeAttribute("style"); setTimeout(function(){document.getElementById("alert-box-exp").style.display = "none";},5000); }';
        echo '</script>';
    }
    ?>

    <div class="login-card">
        <div class="login-help">
            <?php
            echo '<a>' . $customername . '</a>';

            if ($_SESSION['lang'] == 'bg') {
                echo '&nbsp;•&nbsp;<a href="monthlysalesbyprintgroup.php?lang=en"><img src="images/en.png" /></a>';
            } else {
                echo '&nbsp;•&nbsp;<a href="monthlysalesbyprintgroup.php?lang=bg"><img src="images/bg.png" /></a>';
            }
            echo '&nbsp;•&nbsp;<a href="javascript:history.go(0)"><img src="images/refresh.png" alt="refresh"></a>';
            ?>
        </div>
		<h1 align="center"> <?php echo $_SESSION['s_objectname']; ?> </h1>
		<h1 align="center"><?php echo $lang["rptMonthlyGroupPrint"]; ?></h1>
		<h4 align="center">
			<?php
			$start_date_display = $start_date->format('d.m.Y');
			$end_date_display = $end_date->format('d.m.Y');
			echo $lang["rptGroupTurnoverToDate"] . $start_date_display . ' - ' . $end_date_display;
			?>
		</h4>


        <div class="chart">
            <div>
                <canvas id="canvas" <?php if ($chartBars == 1) {
                                        $chartBars = 2;
                                    } elseif ($chartBars == 2) {
                                        $chartBars = 3;
                                    } elseif ($chartBars == 3) {
                                        $chartBars = 3.5;
                                    }
                                    echo 'height="' . C_CANVAS_HEIGHT * $chartBars . '"'; ?>></canvas>
            </div>
        </div> 
        <br>

        <center>
            <table style="width:100%; line-height:22px;">
                <tr>
                    <td></td>
                    <td>
                        <?php
                        if (C_DEBUG) {
                            echo "expiredate: " . $expiredate . " current time: " . time() . "<br/>";
                        }
                        if ($expiredate < time()) {
                            // show back button
                            echo '<center><a href="' . $_SERVER['PHP_SELF'] . '" class="medium color-1 button" style="width:95%;">' . $lang["btnBack"] . '</a></center>';
                        }
                        ?>
                    </td>
                    <td></td>
                </tr>
                <tr>
                    <?php
                    if ($expiredate > time()) {
                        echo '<td align="center"><a href="';
                        $urlparams = $_GET;
                        $urlparams['date'] = (new DateTime($rptdate))->modify('-1 month')->format('Y-m-d');
                        $urlparams = http_build_query($urlparams);
                        echo $_SERVER['PHP_SELF'] . "?" . $urlparams;
                        echo '" class="medium color-1 button"><<</a></td>';
                    } else {
                        echo '<td align="center"><a href="#" onclick="show_exp()" class="medium color-1 button"><<</a></td>';
                    }
                    ?>
                    <td align="center" style="width:60%;"><a href="rptlist.php" class="medium color-5 button" style="width:95%;"><?php echo $lang["btnExit"]; ?></a></td>
                    <?php
                    if ($expiredate > time()) {
                        echo '<td align="center"><a href="';
                        $today = new DateTime();
                        $today->setTime(0, 0, 0);

                        $match_date = new DateTime($rptdate);
                        $match_date->setTime(0, 0, 0);

                        $diff = $today->diff($match_date);
                        $diffDays = (integer)$diff->format("%R%a");

                        $urlparams = $_GET;

                        if ($diffDays < 0) {
                            $urlparams['date'] = (new DateTime($rptdate))->modify('+1 month')->format('Y-m-d');
                        }
                        $urlparams = http_build_query($urlparams);
                        echo $_SERVER['PHP_SELF'] . "?" . $urlparams;
                        echo '" class="medium color-1 button">>></a></td>';
                    } else {
                        echo '<td align="center"><a href="#" onclick="show_exp()" class="medium color-1 button">>></a></td>';
                    }
                    ?>
                </tr>
            </table>
        </center>
    </div>

<script>
    var rawData = [<?php echo $TurnoverData; ?>];
    var labels = [<?php echo $TurnoverLabel; ?>];
    var rptRevenueChartLabel = <?php echo '"' . $lang["rptRevenueChartLabel"] . '"'; ?>;

    var config = {
        type: 'horizontalBar',
        data: {
            labels: labels,
            datasets: [{
                label: rptRevenueChartLabel,
                data: rawData,
                borderColor: "rgba(151,187,205,1)",
                backgroundColor: "rgba(151,187,205,0.5)",
                borderWidth: 1,
                hoverBackgroundColor: "rgba(255, 99, 132, 0.2)",
                hoverBorderColor: "#ff6384"
            }]
        },
        options: {
            responsive: true,
            scales: {
                yAxes: [{
                    ticks: {
                        mirror: true
                    },
                    barThickness: <?php echo C_BAR_HEIGHT ?>
                }],
                xAxes: [{
                    ticks: {
                        beginAtZero: true
                    }
                }]
            },
            legend: {
                display: false,
            },
            hover: {
                mode: 'label'
            },
            tooltips: {
                enabled: true,
                mode: 'nearest',
                callbacks: {
                    label: function(tooltipItem, data) {
                        return labels[tooltipItem.index] + ': ' + rptRevenueChartLabel + ' ' + rawData[tooltipItem.index].toLocaleString();  // Show label and actual value in tooltip
                    }
                }
            }
        }
    };

    window.onload = function() {
        var ctx = document.getElementById("canvas").getContext("2d");
        window.myLine = new Chart(ctx, config);

        // Add event listener to handle clicks on labels
        ctx.canvas.addEventListener('click', function(evt) {
            var activePoints = window.myLine.getElementAtEvent(evt);
            if (activePoints.length) {
                var label = window.myLine.data.labels[activePoints[0]._index];
                var value = window.myLine.data.datasets[0].data[activePoints[0]._index];
                // Show tooltip at click position
                window.myLine.tooltip._active = [activePoints[0]];
                window.myLine.tooltip.update(true);
                window.myLine.draw();
            } else {
                // Detect click on label area
                var chartArea = window.myLine.chartArea;
                var yScale = window.myLine.scales['y-axis-0'];
                var mouseY = evt.clientY - ctx.canvas.getBoundingClientRect().top;
                if (mouseY >= chartArea.top && mouseY <= chartArea.bottom) {
                    var labelIndex = Math.floor((mouseY - chartArea.top) / yScale.height * yScale.ticks.length);
                    var label = window.myLine.data.labels[labelIndex];
                    var value = window.myLine.data.datasets[0].data[labelIndex];
                    // Show tooltip at label position
                    var meta = window.myLine.getDatasetMeta(0);
                    var bar = meta.data[labelIndex];
                    window.myLine.tooltip._active = [bar];
                    window.myLine.tooltip.update(true);
                    window.myLine.draw();
                }
            }
        });
    };

    <?php
    if ($expiredate > time()) {
        echo 'var canvas = document.getElementById("canvas");';
        echo 'canvas.onclick = function (evt) {var activePoints = myLine.getElementAtEvent(evt);';
        echo 'if (activePoints[0] != null){';
        echo '} };';
    } else {
        echo 'var canvas = document.getElementById("canvas");';
        echo 'canvas.onclick = function (evt) {var activePoints = myLine.getElementAtEvent(evt);';
        echo 'if (activePoints[0] != null){';
        echo 'show_exp();';
        echo '} };';
    }
    ?>
</script>

</body>

</html>
