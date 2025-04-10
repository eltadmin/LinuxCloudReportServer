<?php
/** @package    DREPORTS::Controller */

/** import supporting libraries */
require_once("AppBaseController.php");
require_once("Model/RestIP.php");



/**
 * RestIPController is the controller class for the RestIP object.  The
 * controller is responsible for processing input from the user, reading/updating
 * the model as necessary and displaying the appropriate view.
 *
 * @package DREPORTS::Controller
 * @author ClassBuilder
 * @version 1.0
 */
class RestIPController extends AppBaseController
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
        'Admin permission is required to configure Rest IPs');
	}

	/**
	 * Displays a list view of RestIP objects
	 */
	public function ListView()
	{
		$this->Render();
	}

	/**
	 * API Method queries for RestIP records and render as JSON
	 */
	public function Query()
	{
		try
		{
			$criteria = new RestIPCriteria();
			
			// TODO: this will limit results based on all properties included in the filter list 
			$filter = RequestUtil::Get('filter');
			if ($filter) $criteria->AddFilter(
				new CriteriaFilter('Id,Ip,Comment'
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

				$restips = $this->Phreezer->Query('RestIP',$criteria)->GetDataPage($page, $pagesize);
				$output->rows = $restips->ToObjectArray(true,$this->SimpleObjectParams());
				$output->totalResults = $restips->TotalResults;
				$output->totalPages = $restips->TotalPages;
				$output->pageSize = $restips->PageSize;
				$output->currentPage = $restips->CurrentPage;
			}
			else
			{
				// return all results
				$restips = $this->Phreezer->Query('RestIP',$criteria);
				$output->rows = $restips->ToObjectArray(true, $this->SimpleObjectParams());
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
	 * API Method retrieves a single RestIP record and render as JSON
	 */
	public function Read()
	{
		try
		{
			$pk = $this->GetRouter()->GetUrlParam('id');
			$restip = $this->Phreezer->Get('RestIP',$pk);
			$this->RenderJSON($restip, $this->JSONPCallback(), true, $this->SimpleObjectParams());
            
		}
		catch (Exception $ex)
		{
			$this->RenderExceptionJSON($ex);
		}
	}

	/**
	 * API Method inserts a new RestIP record and render response as JSON
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

			$restip = new RestIP($this->Phreezer);

			// TODO: any fields that should not be inserted by the user should be commented out

			// this is an auto-increment.  uncomment if updating is allowed
			// $restip->Id = $this->SafeGetVal($json, 'id');

			$restip->Ip = $this->SafeGetVal($json, 'ip');
			$restip->Comment = $this->SafeGetVal($json, 'comment');

			$restip->Validate();
			$errors = $restip->GetValidationErrors();

			if (count($errors) > 0)
			{
				$this->RenderErrorJSON('Please check the form for errors',$errors);
			}
			else
			{
				$restip->Save();
				$this->RenderJSON($restip, $this->JSONPCallback(), true, $this->SimpleObjectParams());
                
                $this->saveLog('115','RestIP add IP: '.$restip->Ip);
			}

		}
		catch (Exception $ex)
		{
			$this->RenderExceptionJSON($ex);
		}
	}

	/**
	 * API Method updates an existing RestIP record and render response as JSON
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
			$restip = $this->Phreezer->Get('RestIP',$pk);

			// TODO: any fields that should not be updated by the user should be commented out

			// this is a primary key.  uncomment if updating is allowed
			// $restip->Id = $this->SafeGetVal($json, 'id', $restip->Id);

			$restip->Ip = $this->SafeGetVal($json, 'ip', $restip->Ip);
			$restip->Comment = $this->SafeGetVal($json, 'comment', $restip->Comment);

			$restip->Validate();
			$errors = $restip->GetValidationErrors();

            
			if (count($errors) > 0)
			{
				$this->RenderErrorJSON('Please check the form for errors',$errors);
			}
			else
			{
				$restip->Save();
				$this->RenderJSON($restip, $this->JSONPCallback(), true, $this->SimpleObjectParams());
                
                $this->saveLog('116','RestIP edit Id: '.$pk);
			}
            
            //$this->saveLog('101','username','update rest ip id='.$pk);

		}
		catch (Exception $ex)
		{


			$this->RenderExceptionJSON($ex);
		}
	}

	/**
	 * API Method deletes an existing RestIP record and render response as JSON
	 */
	public function Delete()
	{
		try
		{
						
			// TODO: if a soft delete is prefered, change this to update the deleted flag instead of hard-deleting

			$pk = $this->GetRouter()->GetUrlParam('id');
			$restip = $this->Phreezer->Get('RestIP',$pk);

			$restip->Delete();

			$output = new stdClass();

			$this->RenderJSON($output, $this->JSONPCallback());
            
            $this->saveLog('117','RestIP delete Id: '.$pk);

		}
		catch (Exception $ex)
		{
			$this->RenderExceptionJSON($ex);
		}
	}

} 

?>
