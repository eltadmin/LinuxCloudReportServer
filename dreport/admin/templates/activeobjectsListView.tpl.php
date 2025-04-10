<?php
    $this->assign('title','DREPORTS | Active objects');
    $this->assign('nav','activeobjects');

    $this->display('_Header.tpl.php');
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
    <i class="icon-th-list"></i> Active objects
</h1>
    <!-- underscore template for the collection -->
<?php
//send rest and get data
include('../protected/database.class.php');
$pDatabase = Database::getInstance();
$pDatabase->query("set names 'utf8'");

$qry = $pDatabase->query("select * from t_settings");
while ($row = mysqli_fetch_assoc($qry)) {
    switch ($row['s_name']) {
        case "rpt_server_host":
            $server_host = $row['s_value'];
            break;
        case "rpt_server_port":
            $server_port = $row['s_value'];
            break;
        case "rpt_server_user":
            $server_user = $row['s_value'];
            break;
        case "rpt_server_pswd":
            $server_pswd = $row['s_value'];
            break;
    } //end swithch
  } // while

$url = 'http://'.$server_host.':'.$server_port.'/server/clientlist/?u='.$server_user.'&p='.$server_pswd;
//$url = 'http://127.0.0.1:8080/server/clientlist/?u=user&p=pass$123';
$str = @file_get_contents($url);
//$str = '{"ResultCode":0,"ResultMessage":"OK","Clients":[{"Id":"149e9677","Host":"RESTAURANT","IP":"192.168.147.121","Conn":"2016-01-28 18:32:26","Act":"2016-01-29 11:14:05","App":"EBOCloudReportSvc.exe","Ver":"1.0.0.5","Db":"R","Name":"САСИ 80 ЕООД"},{"Id":"a1255c9d","Host":"CLOUD1","IP":"127.0.0.1","Conn":"2016-01-29 10:09:44","Act":"2016-01-29 11:14:13","App":"EBOCloudReportSvc.exe","Ver":"1.0.0.5","Db":"L","Name":"Име на фирма"},{"Id":"ec151b6e","Host":"RESTAURANT12R2","IP":"192.168.147.121","Conn":"2016-01-29 10:09:51","Act":"2016-01-29 11:14:21","App":"EBOCloudReportSvc.exe","Ver":"1.0.0.5","Db":"R","Name":"Име на фирма"},{"Id":"f2a5e399","Host":"RESTAURANT12R2","IP":"192.168.147.121","Conn":"2016-01-29 10:09:51","Act":"2016-01-29 11:14:24","App":"EBOCloudReportSvc.exe","Ver":"1.0.0.5","Db":"R","Name":"САСИ 80 ЕООД"},{"Id":"76bba5ee","Host":"AION","IP":"95.168.234.188","Conn":"2016-01-29 10:36:33","Act":"2016-01-29 11:14:19","App":"EBOCloudReportSvc.exe","Ver":"1.0.0.5","Db":"R","Name":"Хотел Аква Варвара"}]}';

if (!$str) {
  //show error essage
  echo '<div id="alert_3" class="alert alert-error"><a class="close" data-dismiss="alert">×</a>';
  echo '<span>Host not found or data not received.</span></div>';
} else {
    $rptdata = @json_decode($str, false);
    if ($rptdata == null && json_last_error() !== JSON_ERROR_NONE) {
        //show error essage
        echo '<div id="alert_3" class="alert alert-error"><a class="close" data-dismiss="alert">×</a>';
        echo '<span>Data not valid.</span></div>';
    } else {
?>
				<p><b>Total: <?=count($rptdata->Clients)?></b></p>

        <table class="collection table table-bordered table-hover">
        <thead>
            <tr>
                <th id="header_Id">Id</th>
                <th id="header_Host">Host</th>
                <th id="header_IP">IP</th>
                <th id="header_Conn">Conn</th>
                <th id="header_Act">Act</th>
                <th id="header_App">App</th>
                <th id="header_Ver">Ver</th>
                <th id="header_Db">Db</th>
                <th id="header_Name">Name</th>
                <th id="header_Exp">Exp Date</th>				
            </tr>
        </thead>
        <tbody>
<?php
      //continue only if "ResultCode": 0,
      if ($rptdata->ResultCode == 0) {
          foreach($rptdata->Clients as $item) {
           echo '<tr>';
           echo '<td>'.$item->Id.'</td>';
           echo '<td>'.$item->Host.'</td>';
           echo '<td>'.$item->IP.'</td>';
           echo '<td>'.$item->Conn.'</td>';
           echo '<td>'.$item->Act.'</td>';
           echo '<td>'.$item->App.'</td>';
           echo '<td>'.$item->Ver.'</td>';
           echo '<td>'.$item->Db.'</td>';
           echo '<td>'.$item->Name.'</td>';
//           echo '<td>'.$item->Exp.'</td>';		              
           echo '<td nowrap="nowrap">'. DateTime::createFromFormat('Y-m-d H:i:s', $item->Exp)->format('Y-m-d')  .'</td>';		   
           echo '</tr>';
          }
      } else {
          echo '<div id="alert_3" class="alert alert-error"><a class="close" data-dismiss="alert">×</a>';
          echo '<span>Error result code: '.$rptdata->ResultCode.'</span></div>';
      } // end else ResultCode=
?>
        </tbody>
        </table>
<?php
    } // end JSON error
} // end else !$str

?>
</div> <!-- /container -->


<?php
    $this->display('_Footer.tpl.php');
?>