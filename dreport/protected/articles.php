<?php
session_start();
if (!isset($_SESSION['s_authenticated']) || empty($_SESSION['s_authenticated'])) {
    header("location:../index.php");
    exit;
}

define("C_DEBUG", false);
define("C_BAR_HEIGHT", 20);
define("C_CANVAS_HEIGHT", 30);
define("C_RPTNAME", "articlesinfo");
define("C_TIMEOFFSET", 0);
$otimeoffset = C_TIMEOFFSET;
$rname = C_RPTNAME;

$pGrpId = '';
$pluTaxgroupId = '';
$pluLocalPrice = '';

$searchQuery = isset($_GET['search']) ? strtolower($_GET['search']) : '';
$searchFilter = isset($_GET['filter']) ? strtolower($_GET['filter']) : 'plu_name';

$filterConditions = [
    'plu_numb' => "p.PLU_NUMB = '{$searchQuery}'",
    'plu_name' => "UPPER(p.PLU_NAME) LIKE UPPER('%{$searchQuery}%')",
    'plu_sell_price' => "p.PLU_SELL_PRICE = '{$searchQuery}'",
    'plu_barcode' => "b.PLU_BARCODE = '{$searchQuery}' AND b.PLU_ISUSED = 1"
];

$whereClause = isset($filterConditions[$searchFilter]) ? "WHERE " . $filterConditions[$searchFilter] : '';

$selectFields = "p.PLU_NUMB, p.PLU_NAME, p.PLU_GROUP_ID, p.PLU_SELL_PRICE, p.PLU_ECR_NAME, p.PLU_BUY_PRICE, p.PLU_TAXGROUP_ID, p.PLU_PROMOTION_, p.PLU_LOCAL_PRICE_,p.PLU_SELL_DISABLED_, b.PLU_BARCODE";
$joinClause = "LEFT JOIN BARCODES b ON p.PLU_NUMB = b.PLU_NUMB AND b.PLU_ISUSED = 1";

$C_SQL1 = '{"Id":"DatabaseId","Pass":"1234","PLUESQuery":{"Type":"Query","SQL":"SELECT ' . $selectFields . ' FROM PLUES p ' . $joinClause . ' ' . $whereClause . ' ORDER BY p.PLU_NUMB"}}';

// new C_SQL4 for checking if the database is central
$C_SQL4 = '{"Id":"DatabaseId","Pass":"1234","CheckCentralDb":{"Type":"Query","SQL":"SELECT GEN_ID(NN_OFFICE_TYPE, 0) AS OFFICE_TYPE FROM RDB$DATABASE;"}}';

include_once 'language.php';
require_once('class.loading.div.php');
$divLoader = new loadingDiv;
$divLoader->loader();

$objectid = isset($_GET["id"]) ? $_GET["id"] : $_SESSION['s_objectid'];
$_SESSION['s_objectid'] = $objectid;

$deviceid = isset($_SESSION['s_deviceid']) ? $_SESSION['s_deviceid'] : "0";
$rptdate = isset($_GET["date"]) ? $_GET["date"] : '';

include('database.class.php');
$pDatabase = Database::getInstance();
$pDatabase->query("set names 'utf8'");

// Check for subscription details
$qry = $pDatabase->query("SELECT s_customername, s_expiredate FROM t_subscriptions WHERE s_objectid = '" . sql_safe($_SESSION['s_objectid']) . "'");
while ($row = mysqli_fetch_assoc($qry)) {
    $expiredate = strtotime($row['s_expiredate']);
    $customername = $row['s_customername'];
    if ($expiredate <= time()) {
        // Account expired, show message and exit
        echo '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
            <title>Account Expired</title>
            <link rel="stylesheet" href="css/style.css">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <link rel="stylesheet" href="css/w3.css">
            <script src="js/Chart.min.js"></script>
            <link rel="stylesheet" href="css/box.css">
            <script type="text/javascript" src="js/jquery.min.js" ></script>
            <script type="text/javascript" src="js/jquery.alerts.js"></script>
        </head>
        <body>
            <div class="login-card">
                <div class="login-help">';
        $pDatabase->show_alert(ALERT_WARNING, $lang["AlertWarning"], $lang["errObjectExpired"] . date("d.m.Y", $expiredate), false, false);
        echo '<style>
    .button {
        display: inline-block;  
        border-radius: 20px;
        font-size: 14px;
        text-align: center; 
        color: white;
        transition: background-color 0.3s ease;
        box-shadow: rgb(38, 70, 83) 0px 11px 8px -5px;
    }
    .color-5 { background-color: #FBB03B; }
    .color-5:hover, .color-5:active  {
        background-color: #e18e0a;  
    } 
</style>';
		echo '<div><center><a href="rptlist.php" class="medium color-5 button">' . $lang["btnExit"] . '</a></center></div>';
        $pDatabase->logevent(OPER_ERROR, $objectid, 'report: ' . $rname . ' error: ' . $lang["errObjectExpired"] . date("d.m.Y", $expiredate));
        echo '</div>
            </div>
        </body>
        </html>';
        exit;
    }
}

$qry = $pDatabase->query("select d_objectpswd,d_timeoffset from t_devices where d_deviceid = '$deviceid' and d_objectid='" . sql_safe($objectid) . "' ;");
while ($row = mysqli_fetch_assoc($qry)) {
    $objectpswd = $row['d_objectpswd'];
    $otimeoffset = $row['d_timeoffset'];
}
if (C_DEBUG) {
    echo "time offset: ";
    var_dump($otimeoffset);
    echo "<br/>";
}

// execute the new C_SQL4 request
$url = 'http://' . $_SESSION['s_rpt_server_host'] . ':' . $_SESSION['s_rpt_server_port'] . '/report/' . $rname . '/?id=' . $objectid . '&u=' . $_SESSION['s_rpt_server_user'] . '&p=' . $_SESSION['rpt_server_pswd'];
$obj = json_decode($C_SQL4);
$obj->{"Id"} = $objectid;
$obj->{"Pass"} = $objectpswd;
$C_SQL4 = json_encode($obj);

$options = [
    'http' => [
        'timeout' => 45,
        'header' => "Content-type: text/xml\r\n",
        'method' => 'GET',
        'content' => $C_SQL4
    ]
];
$context = stream_context_create($options);
$str = @file_get_contents($url, false, $context);

if ($str === false) {
    echo "Error fetching data";
} else {
    $response = json_decode($str, true);
}

$isCentralDb = isset($response['CheckCentralDb'][0]['OFFICE_TYPE']) && $response['CheckCentralDb'][0]['OFFICE_TYPE'] == 1 ? '1' : '0';

$qry = $pDatabase->query("SELECT s_customername, s_expiredate FROM t_subscriptions WHERE s_objectid = '" . sql_safe($_SESSION['s_objectid']) . "'");
while ($row = mysqli_fetch_assoc($qry)) {
    $expiredate = strtotime($row['s_expiredate']);
    $customername = $row['s_customername'];
    if ($expiredate <= time()) {
        echo "Account expired";
        exit;
    }
}

$objectpswd = "";
$rptsql = "";
$rname = C_RPTNAME;

$qry = $pDatabase->query("select d_objectpswd,d_timeoffset from t_devices where d_deviceid = '$deviceid' and d_objectid='" . sql_safe($objectid) . "' ;");
while ($row = mysqli_fetch_assoc($qry)) {
    $objectpswd = $row['d_objectpswd'];
    $otimeoffset = $row['d_timeoffset'];
}
if (C_DEBUG) {
    echo "time offset: ";
    var_dump($otimeoffset);
    echo "<br/>";
}

if ($rptdate == '') {
    $dt = new DateTime();
    $rptdate = $dt->format('Y-m-d');
}
$rptsql = str_replace('PARAMDATE', '\'' . $rptdate . '\'', $rptsql);
if (C_DEBUG) {
    echo "report date: ";
    var_dump($rptdate);
    echo "<br/>";
}

$pDatabase->logevent(OPER_COMMAND, $objectid, 'report: ' . $rname . ' objectid=' . $objectid);

$combinedItems = [];
$totalItems = 0;

if (!empty($searchQuery)) {
    $url = 'http://' . $_SESSION['s_rpt_server_host'] . ':' . $_SESSION['s_rpt_server_port'] . '/report/' . $rname . '/?id=' . $objectid . '&u=' . $_SESSION['s_rpt_server_user'] . '&p=' . $_SESSION['rpt_server_pswd'];
    if (C_DEBUG) {
        echo "url: ";
        var_dump($url);
        echo "<br/>";
    }
    $obj = json_decode($C_SQL1);
    $obj->{"Id"} = $objectid;
    $obj->{"Pass"} = $objectpswd;
    $C_SQL1 = json_encode($obj);

    if (C_DEBUG) {
        echo "content: ";
        var_dump($C_SQL1);
        echo "<br/>";
    }

    $options = [
        'http' => [
            'timeout' => 45,
            'header' => "Content-type: text/xml\r\n",
            'method' => 'GET',
            'content' => $C_SQL1
        ]
    ];
    $context = stream_context_create($options);
    $str = @file_get_contents($url, false, $context);
    if (C_DEBUG) {
        echo "response: ";
        var_dump($str);
        echo "<br/>";
    }

    if ($str === false) {
        echo "Error fetching data";
    } else {
        $response = json_decode($str, true);
    }

    if ($str !== false) {
        $items = isset($response['PLUESQuery']) ? $response['PLUESQuery'] : [];
        
        // Group items by PLU_NUMB
        $groupedItems = [];
        foreach ($items as $item) {
            $pluNumb = $item['PLU_NUMB'];
            if (!isset($groupedItems[$pluNumb])) {
                $groupedItems[$pluNumb] = $item;
                $groupedItems[$pluNumb]['PLU_BARCODES'] = [];
            }
            $groupedItems[$pluNumb]['PLU_BARCODES'][] = $item['PLU_BARCODE'];
        }

        $combinedItems = array_values($groupedItems);
        $totalItems = count($combinedItems);

        if (!empty($combinedItems)) {
            $pluTaxgroupId = $combinedItems[0]['PLU_TAXGROUP_ID'];
            $pGrpId = $combinedItems[0]['PLU_GROUP_ID'];
        }
    }

    $taxGroupDescr = "";
    if (!empty($pluTaxgroupId)) {
        $C_SQL3 = '{"Id":"DatabaseId","Pass":"1234","PLUESQuery":{"Type":"Query","SQL":"SELECT TAXGRP_DESCR, TAXGRP_NAME FROM N_WAT WHERE TAXGRP_NUMB = \'' . $pluTaxgroupId . '\'"}}';
        $obj = json_decode($C_SQL3);
        $obj->{"Id"} = $objectid;
        $obj->{"Pass"} = $objectpswd;
        $C_SQL3 = json_encode($obj);

        if (C_DEBUG) {
            echo "content: ";
            var_dump($C_SQL3);
            echo "<br/>";
        }

        $options['http']['content'] = $C_SQL3;
        $context = stream_context_create($options);
        $str = @file_get_contents($url, false, $context);
        if (C_DEBUG) {
            echo "response: ";
            var_dump($str);
            echo "<br/>";
        }

        if ($str === false) {
            echo "Error fetching data";
        } else {
            $response = json_decode($str, true);
            if (isset($response['PLUESQuery'][0]['TAXGRP_DESCR']) && isset($response['PLUESQuery'][0]['TAXGRP_NAME'])) {
                $taxGroupDescr = $response['PLUESQuery'][0]['TAXGRP_DESCR'] . '(' . $response['PLUESQuery'][0]['TAXGRP_NAME'] . ')';
            }
        }
    }

    $pGrpName = "";
    if (!empty($pGrpId)) {
        $C_SQL2 = '{"Id":"DatabaseId","Pass":"1234","PLUESQuery":{"Type":"Query","SQL":"SELECT PGRP_NAME FROM N_PLUGROUPS WHERE PGRP_ID = \'' . $pGrpId . '\'"}}';
        $obj = json_decode($C_SQL2);
        $obj->{"Id"} = $objectid;
        $obj->{"Pass"} = $objectpswd;
        $C_SQL2 = json_encode($obj);

        if (C_DEBUG) {
            echo "content: ";
            var_dump($C_SQL2);
            echo "<br/>";
        }

        $options['http']['content'] = $C_SQL2;
        $context = stream_context_create($options);
        $str = @file_get_contents($url, false, $context);
        if (C_DEBUG) {
            echo "response: ";
            var_dump($str);
            echo "<br/>";
        }

        if ($str === false) {
            echo "Error fetching data";
        } else {
            $response = json_decode($str, true);
            if (isset($response['PLUESQuery'][0]['PGRP_NAME'])) {
                $pGrpName = $response['PLUESQuery'][0]['PGRP_NAME'];
            }
        }
    }
}

$itemsPerPage = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$startIndex = ($page - 1) * $itemsPerPage;
$totalPages = ceil($totalItems / $itemsPerPage);
$itemsToShow = array_slice($combinedItems, $startIndex, $itemsPerPage);
$visiblePages = 3;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$startPage = max(1, min($currentPage - 1, $totalPages - ($visiblePages - 1)));
$endPage = min($totalPages, $startPage + ($visiblePages - 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
    <title>Article List</title>
    <link rel="stylesheet" href="css/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="css/w3.css">
    <script src="js/Chart.min.js"></script>
    <link rel="stylesheet" href="css/box.css">
    <script type="text/javascript" src="js/jquery.min.js" ></script>
    <script type="text/javascript" src="js/jquery.alerts.js"></script>
<style>
.login-help{ 
    color: #797979;
    }
  .button {
    display: inline-block;  
    border-radius: 20px;
    font-size: 14px;
    text-align: center; 
    color: white;
    transition: background-color 0.3s ease;
    box-shadow: rgb(38, 70, 83) 0px 11px 8px -5px;
  }
  .color-1 { background-color: #36752d; }
  .color-5 { background-color: #FBB03B; }
  
  .color-1:hover, .color-1:active  {
    background-color: #1e5516;  
  }  
  .color-5:hover, .color-5:active  {
    background-color: #e18e0a;  
  }
  
</style>
</head>
<body>
 <?php
     $appdbtype='';
     $qry = $pDatabase->query("SELECT s_customername, s_expiredate, s_appdbtype FROM t_subscriptions WHERE s_objectid = '".sql_safe($_SESSION['s_objectid'])."'");
     if (mysqli_num_rows($qry) == 0) {
          // Object not subscribed
          $pDatabase->show_alert(ALERT_ERROR,$lang["AlertError"],$lang["errObjectNotSubscribed"],false,false);
          $expiredate = 0;
          $pDatabase->logevent(OPER_ERROR,$deviceid,'object: '.$objectid.' error: '.$lang["errObjectNotSubscribed"]);
      } else {
          while ($row = mysqli_fetch_assoc($qry)) {
           $expiredate = strtotime($row['s_expiredate']);
           $appdbtype = $row['s_appdbtype'];
 

          }
      }
     //show message for expired account
     if ($expiredate < time()){
      echo '<meta http-equiv="X-UA-Compatible" content="IE=EmulateIE11" />';
      echo '<div class="alert-box warning" id="alert-box-exp" style= "display : none">';
      echo '<span>'.$lang["AlertWarning"].'</span>'.$lang["errObjectExpired"].date("d.m.Y", $expiredate);
      echo '</div>';
      echo '<script>';
      //echo 'setTimeout(function(){var element = document.getElementById("alert-box-exp").style.display = "none";},5000);';
      echo 'function show_exp() {document.getElementById("alert-box-exp").removeAttribute("style"); setTimeout(function(){document.getElementById("alert-box-exp").style.display = "none";},5000); }';
      echo '</script>';
     }
 ?>
    <div  class="login-card"  ">
        <div>
            <div class="login-help">
                <a href="http://eltrade.com">www.eltrade.com</a> • <a href="http://eltrade.com/bg/contacts"><?php echo $lang['contacts']; ?></a> •
                <?php
                echo htmlspecialchars($customername);
                if ($_SESSION['lang'] == 'bg') {
                    echo '<a href="articles.php?lang=en&search=' . urlencode($searchQuery) . '&filter=' . urlencode($searchFilter) . '&page=' . urlencode($page) . '"><img src="images/en.png" /></a>';
                } else {
                    echo '<a href="articles.php?lang=bg&search=' . urlencode($searchQuery) . '&filter=' . urlencode($searchFilter) . '&page=' . urlencode($page) . '"><img src="images/bg.png" /></a>';
                }
                ?>
            </div>

            <h1 align="center"> <?php echo $_SESSION['s_objectname']; ?> </h1>
            <h1><?php echo $lang['objArticles']; ?></h1><br>
                        
<div style="margin-bottom: 20px;">
    <table style="width: 100%;">
        <tr>
            <td style="padding: 0 5px;">
                <div style="display: flex; align-items: center;">
                    <form id="searchForm" action="javascript:void(0);" method="get" style="display: flex; align-items: center; width: 100%;">
                        <div style="position: relative; display: flex; align-items: center; margin-right: 10px;">
                            <select id="filter" name="filter" style="width: 120.5px; height: 40px; border-radius: 5px; border: 1px solid #36752d; color: #36752d; appearance: none; -webkit-appearance: none; -moz-appearance: none;">
                                <option value="plu_numb" <?= $searchFilter == 'plu_numb' ? 'selected' : '' ?>><?php echo $lang['SearchByNumber']; ?></option>
                                <option value="plu_name" <?= $searchFilter == 'plu_name' ? 'selected' : '' ?>><?php echo $lang['SearchByName']; ?></option>
                                <option value="plu_sell_price" <?= $searchFilter == 'plu_sell_price' ? 'selected' : '' ?>><?php echo $lang['SearchByPrice']; ?></option>
                                <option value="plu_barcode" <?= $searchFilter == 'plu_barcode' ? 'selected' : '' ?>><?php echo $lang['SearchByBarcode']; ?></option>
                            </select>
                            <img src="images/expandArrow.png" style="position: absolute; right: 0px; pointer-events: none;">
                        </div>
                        <div style="position: relative; flex-grow: 1;">
                            <input type="search" id="search" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($searchQuery); ?>" style="height: 40px; padding: 5px 40px 5px 10px; width: 100%; box-sizing: border-box; border-radius: 5px; border: 1px solid #36752d;">
                            <button id="searchButton" type="submit" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); width: 30px; height: 30px; background: url('images/search1.png') no-repeat center; background-size: 20px 20px; border: none; cursor: pointer; opacity: 0.7; border-radius: 5px;"></button>
                        </div>
                    </form>
                </div>
            </td>
        </tr>
    </table>
</div>

<script type="text/javascript">
document.getElementById('searchForm').attachEvent ? 
    document.getElementById('searchForm').attachEvent('onsubmit', function(event) {
        event.returnValue = false;
        var filter = document.getElementById('filter').value;
        var search = document.getElementById('search').value;
        if (filter === 'plu_sell_price' && !/^\d*\.?\d*$/.test(search)) {
            alert('Please enter a valid price');
            return false;
        }
        if (filter === 'plu_barcode' && !/^[0-9]*$/.test(search)) {
            alert('Please enter a valid barcode');
            return false;
        }
        var url = window.location.href.split('?')[0];
        var newUrl = url + '?filter=' + encodeURIComponent(filter) + '&search=' + encodeURIComponent(search);
        window.location.href = newUrl;
    }) : 
    document.getElementById('searchForm').addEventListener('submit', function(event) {
        event.preventDefault();
        var filter = document.getElementById('filter').value;
        var search = document.getElementById('search').value;
        if (filter === 'plu_sell_price' && !/^\d*\.?\d*$/.test(search)) {
            alert('Please enter a valid price');
            return false;
        }
        if (filter === 'plu_barcode' && !/^[0-9]*$/.test(search)) {
            alert('Please enter a valid barcode');
            return false;
        }
        var url = window.location.href.split('?')[0];
        var newUrl = url + '?filter=' + encodeURIComponent(filter) + '&search=' + encodeURIComponent(search);
        window.location.href = newUrl;
    });

document.getElementById('search').addEventListener('input', function(event) {
    var filter = document.getElementById('filter').value;
    if (filter === 'plu_sell_price') {
        this.value = this.value.replace(/[^0-9.]/g, '');
    } else if (filter === 'plu_barcode') {
        this.value = this.value.replace(/[^0-9]/g, '');
    }
});
</script>



            <?php if (!empty($searchQuery) && !empty($combinedItems)): ?>
            <table border="0" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: separate; text-align: center;">
                <tr style="background-color: #36752d;">
                    <th style="color: white; font-size: 12px; font-family: Arial, Helvetica, sans-serif;"><?php echo $lang['objAricleNumber']; ?></th>
                    <th style="color: white; font-size: 12px; font-family: Arial, Helvetica, sans-serif;"><?php echo $lang['objArticleName']; ?></th>
                    <th style="color: white; font-size: 12px; font-family: Arial, Helvetica, sans-serif;"><?php echo $lang['objPrice']; ?></th>
                </tr>
<?php foreach ($itemsToShow as $item): ?>
<tr>
    <td style="border-left: 1px solid #36752d; border-right: 1px solid #36752d; padding: 8px; padding-left: 10px; font-size: 12px; font-family: Arial, Helvetica, sans-serif;"><?= isset($item['PLU_NUMB']) ? htmlspecialchars($item['PLU_NUMB']) : '' ?></td>
    <td style="border-left: 1px solid #36752d; border-right: 1px solid #36752d; padding: 8px; cursor: pointer; transition: color 0.3s ease; font-size: 12px; font-family: Arial, Helvetica, sans-serif;">
        <a href="#" onclick="showItemDetails(
            '<?= isset($item['PLU_NUMB']) ? htmlspecialchars($item['PLU_NUMB']) : '' ?>',
            '<?= isset($item['PLU_NAME']) ? htmlspecialchars(addslashes($item['PLU_NAME'])) : '' ?>',
            '<?= isset($item['PLU_GROUP_ID']) ? htmlspecialchars($item['PLU_GROUP_ID']) : '' ?>',
            '<?= isset($item['PLU_SELL_PRICE']) ? htmlspecialchars($item['PLU_SELL_PRICE']) : '' ?>',
            '<?= isset($item['PLU_PROMOTION_']) ? htmlspecialchars($item['PLU_PROMOTION_']) : '' ?>',
            '<?= isset($item['PLU_BARCODES']) ? htmlspecialchars(implode(", ", $item['PLU_BARCODES'])) : '' ?>',
            '<?= isset($item['PLU_ECR_NAME']) ? htmlspecialchars($item['PLU_ECR_NAME']) : '' ?>',
            '<?= isset($item['PLU_BUY_PRICE']) ? htmlspecialchars($item['PLU_BUY_PRICE']) : '' ?>',
            '<?= isset($item['PLU_TAXGROUP_ID']) ? htmlspecialchars($item['PLU_TAXGROUP_ID']) : '' ?>',
            '<?= isset($taxGroupDescr) ? htmlspecialchars($taxGroupDescr) : '' ?>',
            '<?= isset($pGrpName) ? htmlspecialchars($pGrpName) : '' ?>',
            '<?= $isCentralDb ?>',
            '<?= isset($item['PLU_LOCAL_PRICE_']) ? htmlspecialchars($item['PLU_LOCAL_PRICE_']) : '' ?>',
            '<?= isset($item['PLU_SELL_DISABLED_']) ? htmlspecialchars($item['PLU_SELL_DISABLED_']) : '' ?>'
        ); return false;" onmouseover="this.style.color='#36752d'" onmouseout="this.style.color='black'" style="text-decoration: none; color: inherit;">
            <?= isset($item['PLU_NAME']) ? htmlspecialchars($item['PLU_NAME']) : '' ?>
        </a>
    </td>
    <td style="border-left: 1px solid #36752d; border-right: 1px solid #36752d; padding: 8px; font-size: 12px; font-family: Arial, Helvetica, sans-serif;"><?= isset($item['PLU_SELL_PRICE']) ? number_format($item['PLU_SELL_PRICE'], 2) : '' ?>лв.</td>
</tr>
<?php endforeach; ?>

            </table>
            <?php elseif (!empty($searchQuery)): ?>
                <p><?php echo $lang['NoItemsFound']; ?></p>
            <?php endif; ?>
 
            <div style="margin-bottom: 20px;"></div>

        </div>        
        <table style="width:100%;">
            <tr>
                <td align="center" style="width:20%;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($searchQuery) ?>&filter=<?= urlencode($searchFilter) ?>"
                           class="medium color-1 button"><<</a>
                    <?php else: ?>
                        <a href="#" class="medium color-1 button" style="inactive"><<</a>
                    <?php endif; ?>
                </td>
                <td align="center" style="width:60%;">
                    <a href="rptlist.php" class="medium color-5 button" style="width:95%;"><?php echo $lang["btnExit"]; ?></a>
                </td>
                <td align="center" style="width:20%;">
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($searchQuery) ?>&filter=<?= urlencode($searchFilter) ?>"
                           class="medium color-1 button">>></a>
                    <?php else: ?>
                        <a href="#" class="medium color-1 button" style="inactive">>></a>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>
<script type="text/javascript">
function showItemDetails(pluNumb, pluName, groupId, sellPrice, promotion, barcode, pluEcrName, pluBuyPrice, pluTaxgroupId, taxGroupDescr, pGrpName, isCentralDb, pluLocalPrice, pluSellDisabled) {
    var url = "ItemDetails.php?pluNumb=" + encodeURIComponent(pluNumb) +
        "&pluName=" + encodeURIComponent(pluName) +
        "&groupId=" + encodeURIComponent(groupId) +
        "&sellPrice=" + encodeURIComponent(sellPrice) +
        "&promotion=" + encodeURIComponent(promotion) +
        "&barcode=" + encodeURIComponent(barcode) +
        "&pluEcrName=" + encodeURIComponent(pluEcrName) +
        "&pluBuyPrice=" + encodeURIComponent(pluBuyPrice) +
        "&pluTaxgroupId=" + encodeURIComponent(pluTaxgroupId) +
        "&taxGroupDescr=" + encodeURIComponent(taxGroupDescr) +
        "&pGrpName=" + encodeURIComponent(pGrpName) +
        "&search=" + encodeURIComponent('<?= $searchQuery ?>') +
        "&filter=" + encodeURIComponent('<?= $searchFilter ?>') +
        "&page=" + encodeURIComponent('<?= $page ?>') +
        "&isCentralDb=" + encodeURIComponent(isCentralDb) +
        "&pluLocalPrice=" + encodeURIComponent(pluLocalPrice) +
        "&pluSellDisabled=" + encodeURIComponent(pluSellDisabled);  

    window.location.href = url;
}

</script>
</body>
</html>
