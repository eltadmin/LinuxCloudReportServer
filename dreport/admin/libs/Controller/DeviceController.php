<?php
/** @package    dadmin::Controller */

/** import supporting libraries */
require_once("AppBaseController.php");
require_once("Model/Device.php");

/**
 * DeviceController is the controller class for the Device object.  The
 * controller is responsible for processing input from the user, reading/updating
 * the model as necessary and displaying the appropriate view.
 *
 * @package dadmin::Controller
 * @author ClassBuilder
 * @version 1.0
 */
class DeviceController extends AppBaseController
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
                'Admin permission is required to configure devices');        

	}

	/**
	 * Displays a list view of Device objects
	 */
	public function ListView()
	{
		$this->Render();
	}

	/**
	 * API Method queries for Device records and render as JSON
	 */
	public function Query()
	{
		try
		{
			$criteria = new DeviceCriteria();
			
			// TODO: this will limit results based on all properties included in the filter list 
			$filter = RequestUtil::Get('filter');
			if ($filter) $criteria->AddFilter(
				new CriteriaFilter('Id,Deviceid,Objectname,Objectid,Objectpswd'
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

				$devices = $this->Phreezer->Query('Device',$criteria)->GetDataPage($page, $pagesize);
				$output->rows = $devices->ToObjectArray(true,$this->SimpleObjectParams());
				$output->totalResults = $devices->TotalResults;
				$output->totalPages = $devices->TotalPages;
				$output->pageSize = $devices->PageSize;
				$output->currentPage = $devices->CurrentPage;
			}
			else
			{
				// return all results
				$devices = $this->Phreezer->Query('Device',$criteria);
				$output->rows = $devices->ToObjectArray(true, $this->SimpleObjectParams());
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
	 * API Method retrieves a single Device record and render as JSON
	 */
	public function Read()
	{
		try
		{
			$pk = $this->GetRouter()->GetUrlParam('id');
			$device = $this->Phreezer->Get('Device',$pk);
			$this->RenderJSON($device, $this->JSONPCallback(), true, $this->SimpleObjectParams());
		}
		catch (Exception $ex)
		{
			$this->RenderExceptionJSON($ex);
		}
	}

	/**
	 * API Method inserts a new Device record and render response as JSON
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

			$device = new Device($this->Phreezer);

			// TODO: any fields that should not be inserted by the user should be commented out

			// this is an auto-increment.  uncomment if updating is allowed
			// $device->Id = $this->SafeGetVal($json, 'id');

			$device->Deviceid = $this->SafeGetVal($json, 'deviceid');
			$device->Objectname = $this->SafeGetVal($json, 'objectname');
			$device->Objectid = $this->SafeGetVal($json, 'objectid');
			$device->Objectpswd = $this->SafeGetVal($json, 'objectpswd');

			$device->Validate();
			$errors = $device->GetValidationErrors();

			if (count($errors) > 0)
			{
				$this->RenderErrorJSON('Please check the form for errors',$errors);
			}
			else
			{
				$device->Save();
				$this->RenderJSON($device, $this->JSONPCallback(), true, $this->SimpleObjectParams());
                $this->saveLog('125','Device add deviceid:'.$device->Deviceid.' objectid:'.$device->Objectid);

			}

		}
		catch (Exception $ex)
		{
			$this->RenderExceptionJSON($ex);
		}
	}

	/**
	 * API Method updates an existing Device record and render response as JSON
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
			$device = $this->Phreezer->Get('Device',$pk);

			// TODO: any fields that should not be updated by the user should be commented out

			// this is a primary key.  uncomment if updating is allowed
			// $device->Id = $this->SafeGetVal($json, 'id', $device->Id);

			$device->Deviceid = $this->SafeGetVal($json, 'deviceid', $device->Deviceid);
			$device->Objectname = $this->SafeGetVal($json, 'objectname', $device->Objectname);
			$device->Objectid = $this->SafeGetVal($json, 'objectid', $device->Objectid);
			$device->Objectpswd = $this->SafeGetVal($json, 'objectpswd', $device->Objectpswd);

			$device->Validate();
			$errors = $device->GetValidationErrors();

			if (count($errors) > 0)
			{
				$this->RenderErrorJSON('Please check the form for errors',$errors);
			}
			else
			{
				$device->Save();
				$this->RenderJSON($device, $this->JSONPCallback(), true, $this->SimpleObjectParams());
                $this->saveLog('126','Device update deviceid:'.$device->Deviceid.' objectid:'.$pk);

			}


		}
		catch (Exception $ex)
		{


			$this->RenderExceptionJSON($ex);
		}
	}

	/**
	 * API Method deletes an existing Device record and render response as JSON
	 */
	public function Delete()
	{
		try
		{
						
			// TODO: if a soft delete is prefered, change this to update the deleted flag instead of hard-deleting

			$pk = $this->GetRouter()->GetUrlParam('id');
			$device = $this->Phreezer->Get('Device',$pk);

			$device->Delete();

			$output = new stdClass();

			$this->RenderJSON($output, $this->JSONPCallback());
            $this->saveLog('127','Device delete deviceid:'.$device->Deviceid.' objectid:'.$pk);

            

		}
		catch (Exception $ex)
		{
			$this->RenderExceptionJSON($ex);
		}
	}
}

?>
