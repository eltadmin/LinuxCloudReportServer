<?php
/** @package    DREPORTS::Controller */

/** import supporting libraries */
require_once("AppBaseController.php");
require_once("Model/Statistic.php");

/**
 * StatisticController is the controller class for the Statistic object.  The
 * controller is responsible for processing input from the user, reading/updating
 * the model as necessary and displaying the appropriate view.
 *
 * @package DREPORTS::Controller
 * @author ClassBuilder
 * @version 1.0
 */
class StatisticController extends AppBaseController
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
			$criteria = new StatisticCriteria();
            $filter = RequestUtil::Get('filter');
            //Add start and end date parameters       
            $filterDateFrom = RequestUtil::Get('filterDateFrom');
            $filterDateTo   = RequestUtil::Get('filterDateTo');
            $filterDateFrom .= ' 00:00:00';
            $filterDateTo   .= ' 23:59:59';            
            
            // vll utf8 support, like in SQL can not be used with non UTF* fields. Bug in SQL
            if (strlen($filter) != strlen(utf8_decode($filter))) { 
                //is unicde
                if ($filter) $criteria->AddFilter(new CriteriaFilter('OperationType, Description','%'.$filter.'%'));
             } else {
                // not unicode
                if ($filter) $criteria->AddFilter(new CriteriaFilter('OperationType,Id,Operid,Datetime,Description','%'.$filter.'%'));
             }

/*            
            if ($filter) $criteria->AddFilter(
                new CriteriaFilter('Id,Opertype,Operid,Datetime,Description'
                , '%'.$filter.'%')
            );
*/            
            $criteria->Datetime_GreaterThanOrEqual = $filterDateFrom;
            $criteria->Datetime_LessThanOrEqual = $filterDateTo;
            
            //throw new Exception("filter:".$filter." from:".$filterDateFrom." to:".$filterDateTo);
/* vll			
			// TODO: this will limit results based on all properties included in the filter list 
			$filter = RequestUtil::Get('filter');
			if ($filter) $criteria->AddFilter(
				new CriteriaFilter('Id,Opertype,Operid,Datetime,Description'
				, '%'.$filter.'%')
			);
*/
			// TODO: this is generic query filtering based only on criteria properties
			foreach (array_keys($_REQUEST) as $prop)
			{
				$prop_normal = ucfirst($prop);
				$prop_equals = $prop_normal.'_Equals';

				if (property_exists($criteria, $prop_normal))
				{
					$criteria->$prop_normal = RequestUtil::Get($prop);
				}
				elseif (property_exists($criteria, $prop_equals))
				{
					// this is a convenience so that the _Equals suffix is not needed
					$criteria->$prop_equals = RequestUtil::Get($prop);
				}
			}

			$output = new stdClass();

			// if a sort order was specified then specify in the criteria
 			$output->orderBy = RequestUtil::Get('orderBy');
 			$output->orderDesc = RequestUtil::Get('orderDesc') != '';
 			if ($output->orderBy) $criteria->SetOrder($output->orderBy, $output->orderDesc);

			$page = RequestUtil::Get('page');

			if ($page != '')
			{
				// if page is specified, use this instead (at the expense of one extra count query)
				$pagesize = $this->GetDefaultPageSize();

				$statisticses = $this->Phreezer->Query('StatisticReporter',$criteria)->GetDataPage($page, $pagesize);
				$output->rows = $statisticses->ToObjectArray(true,$this->SimpleObjectParams());
				$output->totalResults = $statisticses->TotalResults;
				$output->totalPages = $statisticses->TotalPages;
				$output->pageSize = $statisticses->PageSize;
				$output->currentPage = $statisticses->CurrentPage;
			}
			else
			{
				// return all results
				$statisticses = $this->Phreezer->Query('StatisticReporter',$criteria);
				$output->rows = $statisticses->ToObjectArray(true, $this->SimpleObjectParams());
				$output->totalResults = count($output->rows);
				$output->totalPages = 1;
				$output->pageSize = $output->totalResults;
				$output->currentPage = 1;
			}


			$this->RenderJSON($output, $this->JSONPCallback());
            
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
		try
		{
			$pk = $this->GetRouter()->GetUrlParam('id');
			$statistic = $this->Phreezer->Get('Statistic',$pk);
			$this->RenderJSON($statistic, $this->JSONPCallback(), true, $this->SimpleObjectParams());
		}
		catch (Exception $ex)
		{
			$this->RenderExceptionJSON($ex);
		}
	}

	/**
	 * API Method inserts a new Statistic record and render response as JSON
	 */
	public function Create()
	{
        // create requires permission
        $this->RequirePermission(User::$PERMISSION_ADMIN,'SecureExample.LoginForm');
        
		try
		{
						
			$json = json_decode(RequestUtil::GetBody());

			if (!$json)
			{
				throw new Exception('The request body does not contain valid JSON');
			}

			$statistic = new Statistic($this->Phreezer);

			// TODO: any fields that should not be inserted by the user should be commented out

			// this is an auto-increment.  uncomment if updating is allowed
			// $statistic->Id = $this->SafeGetVal($json, 'id');

			$statistic->Opertype = $this->SafeGetVal($json, 'opertype');
			$statistic->Operid = $this->SafeGetVal($json, 'operid');
			$statistic->Datetime = date('Y-m-d H:i:s',strtotime($this->SafeGetVal($json, 'datetime')));
			$statistic->Description = $this->SafeGetVal($json, 'description');

			$statistic->Validate();
			$errors = $statistic->GetValidationErrors();

			if (count($errors) > 0)
			{
				$this->RenderErrorJSON('Please check the form for errors',$errors);
			}
			else
			{
				$statistic->Save();
				$this->RenderJSON($statistic, $this->JSONPCallback(), true, $this->SimpleObjectParams());
			}

		}
		catch (Exception $ex)
		{
			$this->RenderExceptionJSON($ex);
		}
	}

	/**
	 * API Method updates an existing Statistic record and render response as JSON
	 */
	public function Update()
	{
        // create requires permission
        $this->RequirePermission(User::$PERMISSION_ADMIN,'SecureExample.LoginForm');


		try
		{
						
			$json = json_decode(RequestUtil::GetBody());

			if (!$json)
			{
				throw new Exception('The request body does not contain valid JSON');
			}

			$pk = $this->GetRouter()->GetUrlParam('id');
			$statistic = $this->Phreezer->Get('Statistic',$pk);

			// TODO: any fields that should not be updated by the user should be commented out

			// this is a primary key.  uncomment if updating is allowed
			// $statistic->Id = $this->SafeGetVal($json, 'id', $statistic->Id);

			$statistic->Opertype = $this->SafeGetVal($json, 'opertype', $statistic->Opertype);
			$statistic->Operid = $this->SafeGetVal($json, 'operid', $statistic->Operid);
			$statistic->Datetime = date('Y-m-d H:i:s',strtotime($this->SafeGetVal($json, 'datetime', $statistic->Datetime)));
			$statistic->Description = $this->SafeGetVal($json, 'description', $statistic->Description);

			$statistic->Validate();
			$errors = $statistic->GetValidationErrors();

			if (count($errors) > 0)
			{
				$this->RenderErrorJSON('Please check the form for errors',$errors);
			}
			else
			{
				$statistic->Save();
				$this->RenderJSON($statistic, $this->JSONPCallback(), true, $this->SimpleObjectParams());
			}


		}
		catch (Exception $ex)
		{


			$this->RenderExceptionJSON($ex);
		}
	}

	/**
	 * API Method deletes an existing Statistic record and render response as JSON
	 */
	public function Delete()
	{
        // create requires permission
        $this->RequirePermission(User::$PERMISSION_ADMIN,'SecureExample.LoginForm');

		try
		{
						
			// TODO: if a soft delete is prefered, change this to update the deleted flag instead of hard-deleting

			$pk = $this->GetRouter()->GetUrlParam('id');
			$statistic = $this->Phreezer->Get('Statistic',$pk);

			$statistic->Delete();

			$output = new stdClass();

			$this->RenderJSON($output, $this->JSONPCallback());

		}
		catch (Exception $ex)
		{
			$this->RenderExceptionJSON($ex);
		}
	}
    
    

}

?>
