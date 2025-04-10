<?php
/** @package    DREPORTS::Controller */

/** import supporting libraries */
require_once("AppBaseController.php");
require_once("Model/Report.php");

/**
 * ReportController is the controller class for the Report object.  The
 * controller is responsible for processing input from the user, reading/updating
 * the model as necessary and displaying the appropriate view.
 *
 * @package DREPORTS::Controller
 * @author ClassBuilder
 * @version 1.0
 */
class ReportController extends AppBaseController
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
        $this->RequirePermission(User::$PERMISSION_ADMIN,
                'SecureExample.LoginForm',
                'Please login to access this page',
                'Admin permission is required to configure settings');        

	}

	/**
	 * Displays a list view of Report objects
	 */
	public function ListView()
	{
		$this->Render();
	}

	/**
	 * API Method queries for Report records and render as JSON
	 */
	public function Query()
	{
		try
		{
			$criteria = new ReportCriteria();
			
			// TODO: this will limit results based on all properties included in the filter list 
			$filter = RequestUtil::Get('filter');
			if ($filter) $criteria->AddFilter(
				new CriteriaFilter('Id,Objectid,Name,FriendlynameBg,FriendlynameEn,Href,SqlBg,SqlEn,Appdbtype,Order,Color'
				, '%'.$filter.'%')
			);

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

				$reports = $this->Phreezer->Query('Report',$criteria)->GetDataPage($page, $pagesize);
				$output->rows = $reports->ToObjectArray(true,$this->SimpleObjectParams());
				$output->totalResults = $reports->TotalResults;
				$output->totalPages = $reports->TotalPages;
				$output->pageSize = $reports->PageSize;
				$output->currentPage = $reports->CurrentPage;
			}
			else
			{
				// return all results
				$reports = $this->Phreezer->Query('Report',$criteria);
				$output->rows = $reports->ToObjectArray(true, $this->SimpleObjectParams());
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
	 * API Method retrieves a single Report record and render as JSON
	 */
	public function Read()
	{
		try
		{
			$pk = $this->GetRouter()->GetUrlParam('id');
			$report = $this->Phreezer->Get('Report',$pk);
			$this->RenderJSON($report, $this->JSONPCallback(), true, $this->SimpleObjectParams());
		}
		catch (Exception $ex)
		{
			$this->RenderExceptionJSON($ex);
		}
	}

	/**
	 * API Method inserts a new Report record and render response as JSON
	 */
	public function Create()
	{
		try
		{
						
			$json = json_decode(RequestUtil::GetBody());

			if (!$json)
			{
				throw new Exception('The request body does not contain valid JSON');
			}

			$report = new Report($this->Phreezer);

			// TODO: any fields that should not be inserted by the user should be commented out

			$report->Id = $this->SafeGetVal($json, 'id');
			$report->Objectid = $this->SafeGetVal($json, 'objectid');
			$report->Name = $this->SafeGetVal($json, 'name');
			$report->FriendlynameBg = $this->SafeGetVal($json, 'friendlynameBg');
			$report->FriendlynameEn = $this->SafeGetVal($json, 'friendlynameEn');
			$report->Href = $this->SafeGetVal($json, 'href');
			$report->SqlBg = $this->SafeGetVal($json, 'sqlBg');
			$report->SqlEn = $this->SafeGetVal($json, 'sqlEn');
			$report->Appdbtype = $this->SafeGetVal($json, 'appdbtype');
			$report->Order = $this->SafeGetVal($json, 'order');
			$report->Color = $this->SafeGetVal($json, 'color');

			$report->Validate();
			$errors = $report->GetValidationErrors();

			if (count($errors) > 0)
			{
				$this->RenderErrorJSON('Please check the form for errors',$errors);
			}
			else
			{
				// since the primary key is not auto-increment we must force the insert here
				$report->Save(true);
				$this->RenderJSON($report, $this->JSONPCallback(), true, $this->SimpleObjectParams());
                $this->saveLog('130','Report add id:'.$report->Id);
			}

		}
		catch (Exception $ex)
		{
			$this->RenderExceptionJSON($ex);
		}
	}

	/**
	 * API Method updates an existing Report record and render response as JSON
	 */
	public function Update()
	{
		try
		{
						
			$json = json_decode(RequestUtil::GetBody());

			if (!$json)
			{
				throw new Exception('The request body does not contain valid JSON');
			}

			$pk = $this->GetRouter()->GetUrlParam('id');
			$report = $this->Phreezer->Get('Report',$pk);

			// TODO: any fields that should not be updated by the user should be commented out

			// this is a primary key.  uncomment if updating is allowed
			// $report->Id = $this->SafeGetVal($json, 'id', $report->Id);

			$report->Objectid = $this->SafeGetVal($json, 'objectid', $report->Objectid);
			$report->Name = $this->SafeGetVal($json, 'name', $report->Name);
			$report->FriendlynameBg = $this->SafeGetVal($json, 'friendlynameBg', $report->FriendlynameBg);
			$report->FriendlynameEn = $this->SafeGetVal($json, 'friendlynameEn', $report->FriendlynameEn);
			$report->Href = $this->SafeGetVal($json, 'href', $report->Href);
			$report->SqlBg = $this->SafeGetVal($json, 'sqlBg', $report->SqlBg);
			$report->SqlEn = $this->SafeGetVal($json, 'sqlEn', $report->SqlEn);
			$report->Appdbtype = $this->SafeGetVal($json, 'appdbtype', $report->Appdbtype);
			$report->Order = $this->SafeGetVal($json, 'order', $report->Order);
			$report->Color = $this->SafeGetVal($json, 'color', $report->Color);

			$report->Validate();
			$errors = $report->GetValidationErrors();

			if (count($errors) > 0)
			{
				$this->RenderErrorJSON('Please check the form for errors',$errors);
			}
			else
			{
				$report->Save();
				$this->RenderJSON($report, $this->JSONPCallback(), true, $this->SimpleObjectParams());
                $this->saveLog('131','Report update id:'.$pk);
			}


		}
		catch (Exception $ex)
		{

			// this table does not have an auto-increment primary key, so it is semantically correct to
			// issue a REST PUT request, however we have no way to know whether to insert or update.
			// if the record is not found, this exception will indicate that this is an insert request
			if (is_a($ex,'NotFoundException'))
			{
				return $this->Create();
			}

			$this->RenderExceptionJSON($ex);
		}
	}

	/**
	 * API Method deletes an existing Report record and render response as JSON
	 */
	public function Delete()
	{
		try
		{
						
			// TODO: if a soft delete is prefered, change this to update the deleted flag instead of hard-deleting

			$pk = $this->GetRouter()->GetUrlParam('id');
			$report = $this->Phreezer->Get('Report',$pk);

			$report->Delete();

			$output = new stdClass();

			$this->RenderJSON($output, $this->JSONPCallback());
            $this->saveLog('132','Report delete id:'.$pk);

		}
		catch (Exception $ex)
		{
			$this->RenderExceptionJSON($ex);
		}
	}
}

?>
