<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$title = 'DREPORTS | Statistics';
$nav = 'statistics';

include __DIR__ . '/_Header.tpl.php';
?>

<script type="text/javascript">
    $LAB.script("").wait(function(){
        $(document).ready(function(){
            page.init();
        });

        // hack for IE9 which may respond inconsistently with document.ready
        setTimeout(function(){
            if (!page.isInitialized) page.init();
        },1000);
    });
</script>

<div class="container">
    <h1>
        <i class="icon-th-list"></i> Statistics
    </h1>

    <?php
    // Connect to the database
    include('../protected/database.class.php');
    $pDatabase = Database::getInstance();
    $pDatabase->query("set names 'utf8'");

    // Fetch data from t_statistics
    $qry = $pDatabase->query("SELECT * FROM t_statistics");

    if (!$qry) {
        // Show error message
        echo '<div id="alert_3" class="alert alert-error"><a class="close" data-dismiss="alert">×</a>';
        echo '<span>Error fetching data from database.</span></div>';
    } else {
        $statistics = [];
        while ($row = mysqli_fetch_assoc($qry)) {
            $statistics[] = $row;
        }
    ?>
        <p><b>Total: <?= count($statistics) ?></b></p>

        <table class="collection table table-bordered table-hover">
            <thead>
                <tr>
                    <th id="header_Id">Id</th>
                    <th id="header_Opertype">Opertype</th>
                    <th id="header_Operid">Operid</th>
                    <th id="header_Datetime">Datetime</th>
                    <th id="header_Description">Description</th>
                </tr>
            </thead>
            <tbody>
    <?php
        foreach ($statistics as $item) {
            echo '<tr>';
            echo '<td>' . $item['s_id'] . '</td>';
            echo '<td>' . $item['s_opertype'] . '</td>';
            echo '<td>' . $item['s_operid'] . '</td>';
            echo '<td>' . $item['s_datetime'] . '</td>';
            echo '<td>' . $item['s_description'] . '</td>';
            echo '</tr>';
        }
    ?>
            </tbody>
        </table>
    <?php
    } // end else !$qry
    ?>
</div> <!-- /container -->

<?php
include __DIR__ . '/_Footer.tpl.php';
?>
