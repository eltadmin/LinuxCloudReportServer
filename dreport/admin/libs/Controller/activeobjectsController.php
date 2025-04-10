<?php
/** @package    DREPORTS::Controller */

/** import supporting libraries */
require_once("AppBaseController.php");
//require_once("Model/Statistic.php");

/**
 * StatisticController is the controller class for the Statistic object.  The
 * controller is responsible for processing input from the user, reading/updating
 * the model as necessary and displaying the appropriate view.
 *
 * @package DREPORTS::Controller
 * @author ClassBuilder
 * @version 1.0
 */
class ActiveobjectsController extends AppBaseController
{
    
	/**
	 * Override here for any controller-specific functionality
	 *
	 * @inheritdocs
	 */
	protected function Init()
	{
		parent::Init();

		// TODO: add controller-wide bootstrap code
		
		// TODO: if authentiation is required for this entire controller, for example:
		// $this->RequirePermission(ExampleUser::$PERMISSION_USER,'SecureExample.LoginForm');

        $this->RequirePermission(User::$PERMISSION_READ,
                'SecureExample.LoginForm',
                'Please login to access this page',
                'Admin permission is required to configure statistics');        
                
                
	}

	/**
	 * Displays a list view of Statistic objects
	 */
	public function ListView()
	{
        
      //header("Location: http://localhost/dreport/index.php"); 
      //header("Location: http://localhost/dreport/admin/templates/ActiveojectsListView.tpl.php"); 
      //header("Location: http://localhost/dreport/custreport/ActiveojectsListView.tpl.php"); 

      $this->Render();
/*    
        $this->StartObserving();
        $criteria = new StatisticCriteria(); 
        //$criteria->Comment_IsLike = '%111%';
        $text = '%edit%';
        //$text= iconv(mb_detect_encoding($text), "UTF-8", $text);
        
        $criteria->AddFilter(new CriteriaFilter('OperationType', $text));
        
        //select * from `t_subscriptions` where `t_subscriptions`.`s_comment` COLLATE utf8_bin like '%ком%'
        $statistics = $this->Phreezer->Query('StatisticReporter',$criteria);
        foreach ($statistics as $statistics) {}
*/
    
    
	}

	/**
	 * API Method queries for Statistic records and render as JSON
	 */
	public function Query()
	{
        
		try
		{

/*
{"orderBy":"","orderDesc":false,
"rows":[{"id":"1","ip":"0.0.0.127","comment":""},
{"id":"2","ip":"0:0:0:0:0:0:0:12","comment":""},
{"id":"3","ip":"::1","comment":"aa"}],"totalResults":3,"totalPages":1,"pageSize":3,"currentPage":1}

rows:a:3:{i:0;O:8:"stdClass":3:{s:2:"id";s:1:"1";s:2:"ip";s:9:"0.0.0.127";s:7:"comment";s:0:"";}i:1;O:8:"stdClass":3:{s:2:"id";s:1:"2";s:2:"ip";s:16:"0:0:0:0:0:0:0:12";s:7:"comment";s:0:"";}i:2;O:8:"stdClass":3:{s:2:"id";s:1:"3";s:2:"ip";s:3:"::1";s:7:"comment";s:2:"aa";}}

*/            

            
/*            
			$output = new stdClass();

			// if a sort order was specified then specify in the criteria

				// return all results
				//$statisticses = $this->Phreezer->Query('StatisticReporter',$criteria);
				$output->rows = $statisticses->ToObjectArray(true, $this->SimpleObjectParams());
				$output->totalResults = count($output->rows);
				$output->totalPages = 1;
				$output->pageSize = $output->totalResults;
				$output->currentPage = 1;


			$this->RenderJSON($output, $this->JSONPCallback());
*/            
		}
		catch (Exception $ex)
		{
			$this->RenderExceptionJSON($ex);
		}
	}

	/**
	 * API Method retrieves a single Statistic record and render as JSON
	 */
	public function Read()
	{
        
	}

	/**
	 * API Method inserts a new Statistic record and render response as JSON
	 */
	public function Create()
	{
	}

	/**
	 * API Method updates an existing Statistic record and render response as JSON
	 */
	public function Update()
	{
	}

	/**
	 * API Method deletes an existing Statistic record and render response as JSON
	 */
	public function Delete()
	{
	}
    
}

?>
