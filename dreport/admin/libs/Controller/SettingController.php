<?php
/** @package    DREPORTS::Controller */

/** import supporting libraries */
require_once("AppBaseController.php");
require_once("Model/Setting.php");

/**
 * SettingController is the controller class for the Setting object.  The
 * controller is responsible for processing input from the user, reading/updating
 * the model as necessary and displaying the appropriate view.
 *
 * @package DREPORTS::Controller
 * @author ClassBuilder
 * @version 1.0
 */
class SettingController extends AppBaseController
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
	 * Displays a list view of Setting objects
	 */
	public function ListView()
	{
		$this->Render();
	}

	/**
	 * API Method queries for Setting records and render as JSON
	 */
	public function Query()
	{
		try
		{
			$criteria = new SettingCriteria();
			
			// TODO: this will limit results based on all properties included in the filter list 
			$filter = RequestUtil::Get('filter');
			if ($filter) $criteria->AddFilter(
				new CriteriaFilter('Name,Value'
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

				$settings = $this->Phreezer->Query('Setting',$criteria)->GetDataPage($page, $pagesize);
				$output->rows = $settings->ToObjectArray(true,$this->SimpleObjectParams());
				$output->totalResults = $settings->TotalResults;
				$output->totalPages = $settings->TotalPages;
				$output->pageSize = $settings->PageSize;
				$output->currentPage = $settings->CurrentPage;
			}
			else
			{
				// return all results
				$settings = $this->Phreezer->Query('Setting',$criteria);
				$output->rows = $settings->ToObjectArray(true, $this->SimpleObjectParams());
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
	 * API Method retrieves a single Setting record and render as JSON
	 */
	public function Read()
	{
		try
		{
			$pk = $this->GetRouter()->GetUrlParam('name');
			$setting = $this->Phreezer->Get('Setting',$pk);
			$this->RenderJSON($setting, $this->JSONPCallback(), true, $this->SimpleObjectParams());
		}
		catch (Exception $ex)
		{
			$this->RenderExceptionJSON($ex);
		}
	}

	/**
	 * API Method inserts a new Setting record and render response as JSON
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

			$setting = new Setting($this->Phreezer);

			// TODO: any fields that should not be inserted by the user should be commented out

			$setting->Name = $this->SafeGetVal($json, 'name');
			$setting->Value = $this->SafeGetVal($json, 'value');

			$setting->Validate();
			$errors = $setting->GetValidationErrors();

			if (count($errors) > 0)
			{
				$this->RenderErrorJSON('Please check the form for errors',$errors);
			}
			else
			{
				// since the primary key is not auto-increment we must force the insert here
				$setting->Save(true);
				$this->RenderJSON($setting, $this->JSONPCallback(), true, $this->SimpleObjectParams());
                $this->saveLog('120','Settings add name:'.$setting->Name);
			}

		}
		catch (Exception $ex)
		{
			$this->RenderExceptionJSON($ex);
		}
	}

	/**
	 * API Method updates an existing Setting record and render response as JSON
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

			$pk = $this->GetRouter()->GetUrlParam('name');
			$setting = $this->Phreezer->Get('Setting',$pk);

			// TODO: any fields that should not be updated by the user should be commented out

			// this is a primary key.  uncomment if updating is allowed
			// $setting->Name = $this->SafeGetVal($json, 'name', $setting->Name);

			$setting->Value = $this->SafeGetVal($json, 'value', $setting->Value);

			$setting->Validate();
			$errors = $setting->GetValidationErrors();

			if (count($errors) > 0)
			{
				$this->RenderErrorJSON('Please check the form for errors',$errors);
			}
			else
			{
				$setting->Save();
				$this->RenderJSON($setting, $this->JSONPCallback(), true, $this->SimpleObjectParams());
                $this->saveLog('121','Settings update name:'.$pk);
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
	 * API Method deletes an existing Setting record and render response as JSON
	 */
	public function Delete()
	{
		try
		{
						
			// TODO: if a soft delete is prefered, change this to update the deleted flag instead of hard-deleting

			$pk = $this->GetRouter()->GetUrlParam('name');
			$setting = $this->Phreezer->Get('Setting',$pk);

			$setting->Delete();

			$output = new stdClass();

			$this->RenderJSON($output, $this->JSONPCallback());
            $this->saveLog('122','Settings delete name:'.$setting->Name);

		}
		catch (Exception $ex)
		{
			$this->RenderExceptionJSON($ex);
		}
	}
}

?>
