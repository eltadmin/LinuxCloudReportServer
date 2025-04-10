<?php
/** @package    DREPORTS::Controller */

/** import supporting libraries */
require_once("AppBaseController.php");
require_once("Model/Subscription.php");



/**
 * SubscriptionController is the controller class for the Subscription object.  The
 * controller is responsible for processing input from the user, reading/updating
 * the model as necessary and displaying the appropriate view.
 *
 * @package DREPORTS::Controller
 * @author ClassBuilder
 * @version 1.0
 */
class SubscriptionController extends AppBaseController
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
                'Admin permission is required to configure subscriptions');        

	}

	/**
	 * Displays a list view of Subscription objects
	 */
	public function ListView()
	{
		$this->Render();
/*        
        $this->StartObserving();
        $criteria = new SubscriptionCriteria(); 
        //$criteria->Comment_IsLike = '%111%';
        $text = '%асд%';
        //$text= iconv(mb_detect_encoding($text), "UTF-8", $text);
        
        if (strlen($text) != strlen(utf8_decode($text)))
         { 
            $text = 'is unicode';
         } else {
            $text = 'not unicode';  
         }
        
        
        
        $criteria->AddFilter(new CriteriaFilter('Expiredate', $text));
        
        //select * from `t_subscriptions` where `t_subscriptions`.`s_comment` COLLATE utf8_bin like '%ком%'
        $subscriptions = $this->Phreezer->Query('Subscription',$criteria);
        foreach ($subscriptions as $subscription) {}
*/        
	}

	/**
	 * API Method queries for Subscription records and render as JSON
	 */
	public function Query()
	{
		try
		{
			$criteria = new SubscriptionCriteria();
			
			// TODO: this will limit results based on all properties included in the filter list 
			$filter = RequestUtil::Get('filter');
  
            // vll utf8 support, like in SQL can not be used with non UTF* fields. Bug in SQL
            if (strlen($filter) != strlen(utf8_decode($filter)))
             { 
                //is unicde
                if ($filter) $criteria->AddFilter(
                    new CriteriaFilter('Objectid,Objectname,Customername,Eik,Address,Hostname,Appip,Apptype,Appver,Appdbtype,Comment'
                    , '%'.$filter.'%')
                );             
             } else {
                // not unicode
                if ($filter) $criteria->AddFilter(
                    new CriteriaFilter('Objectid,Objectname,Expiredate,Customername,Eik,Address,Hostname,Appip,Apptype,Appver,Appdbtype,Active,Createdate,Lastupdatedate,Comment'
                    , '%'.$filter.'%')
                );
             }



  
        //$filter = utf8_encode($filter);  
        //$filter = 'ком';
        //$filter = iconv(mb_detect_encoding($filter), "UTF-8", $filter);
        //$criteria->AddFilter(new CriteriaFilterCustom('Comment,Objectname,Expiredate', '%'.$filter.'%'));
 //   
/*            
			if ($filter) $criteria->AddFilter(
				new CriteriaFilter('Objectid,Objectname,Expiredate,Customername,Eik,Address,Hostname,Appip,Apptype,Appver,Appdbtype,Active,Createdate,Lastupdatedate,Comment'
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

				$subscriptions = $this->Phreezer->Query('Subscription',$criteria)->GetDataPage($page, $pagesize);
				$output->rows = $subscriptions->ToObjectArray(true,$this->SimpleObjectParams());
				$output->totalResults = $subscriptions->TotalResults;
				$output->totalPages = $subscriptions->TotalPages;
				$output->pageSize = $subscriptions->PageSize;
				$output->currentPage = $subscriptions->CurrentPage;
			}
			else
			{
				// return all results
				$subscriptions = $this->Phreezer->Query('Subscription',$criteria);
				$output->rows = $subscriptions->ToObjectArray(true, $this->SimpleObjectParams());
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
	 * API Method retrieves a single Subscription record and render as JSON
	 */
	public function Read()
	{
		try
		{
			$pk = $this->GetRouter()->GetUrlParam('objectid');
			$subscription = $this->Phreezer->Get('Subscription',$pk);
			$this->RenderJSON($subscription, $this->JSONPCallback(), true, $this->SimpleObjectParams());
		}
		catch (Exception $ex)
		{
			$this->RenderExceptionJSON($ex);
		}
	}

	/**
	 * API Method inserts a new Subscription record and render response as JSON
	 */
	public function Create()
	{
		try
		{
/*						
			$json = json_decode(RequestUtil::GetBody());

			if (!$json)
			{
				throw new Exception('The request body does not contain valid JSON');
			}

			$subscription = new Subscription($this->Phreezer);

			// TODO: any fields that should not be inserted by the user should be commented out

			$subscription->Objectid = $this->SafeGetVal($json, 'objectid');
			$subscription->Objectname = $this->SafeGetVal($json, 'objectname');
			$subscription->Expiredate = date('Y-m-d H:i:s',strtotime($this->SafeGetVal($json, 'expiredate')));
			$subscription->Customername = $this->SafeGetVal($json, 'customername');
			$subscription->Eik = $this->SafeGetVal($json, 'eik');
			$subscription->Address = $this->SafeGetVal($json, 'address');
			$subscription->Hostname = $this->SafeGetVal($json, 'hostname');
			$subscription->Appip = $this->SafeGetVal($json, 'appip');
			$subscription->Apptype = $this->SafeGetVal($json, 'apptype');
			$subscription->Appver = $this->SafeGetVal($json, 'appver');
			$subscription->Appdbtype = $this->SafeGetVal($json, 'appdbtype');
			$subscription->Active = $this->SafeGetVal($json, 'active');
			$subscription->Createdate = date('Y-m-d H:i:s',strtotime($this->SafeGetVal($json, 'createdate')));
			$subscription->Lastupdatedate = date('Y-m-d H:i:s',strtotime($this->SafeGetVal($json, 'lastupdatedate')));
			$subscription->Comment = $this->SafeGetVal($json, 'comment');

			$subscription->Validate();
			$errors = $subscription->GetValidationErrors();

			if (count($errors) > 0)
			{
				$this->RenderErrorJSON('Please check the form for errors',$errors);
			}
			else
			{
				// since the primary key is not auto-increment we must force the insert here
				$subscription->Save(true);
				$this->RenderJSON($subscription, $this->JSONPCallback(), true, $this->SimpleObjectParams());
			}
*/            

		}
		catch (Exception $ex)
		{
			$this->RenderExceptionJSON($ex);
		}
	}

	/**
	 * API Method updates an existing Subscription record and render response as JSON
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

			$pk = $this->GetRouter()->GetUrlParam('objectid');
			$subscription = $this->Phreezer->Get('Subscription',$pk);

			// TODO: any fields that should not be updated by the user should be commented out

			// this is a primary key.  uncomment if updating is allowed
			// $subscription->Objectid = $this->SafeGetVal($json, 'objectid', $subscription->Objectid);

			$subscription->Objectname = $this->SafeGetVal($json, 'objectname', $subscription->Objectname);
			$subscription->Expiredate = date('Y-m-d H:i:s',strtotime($this->SafeGetVal($json, 'expiredate', $subscription->Expiredate)));
			$subscription->Customername = $this->SafeGetVal($json, 'customername', $subscription->Customername);
			$subscription->Eik = $this->SafeGetVal($json, 'eik', $subscription->Eik);
			$subscription->Address = $this->SafeGetVal($json, 'address', $subscription->Address);
			$subscription->Hostname = $this->SafeGetVal($json, 'hostname', $subscription->Hostname);
			$subscription->Appip = $this->SafeGetVal($json, 'appip', $subscription->Appip);
			$subscription->Apptype = $this->SafeGetVal($json, 'apptype', $subscription->Apptype);
			$subscription->Appver = $this->SafeGetVal($json, 'appver', $subscription->Appver);
			$subscription->Appdbtype = $this->SafeGetVal($json, 'appdbtype', $subscription->Appdbtype);
			$subscription->Active = $this->SafeGetVal($json, 'active', $subscription->Active);
			$subscription->Createdate = date('Y-m-d H:i:s',strtotime($this->SafeGetVal($json, 'createdate', $subscription->Createdate)));
			$subscription->Lastupdatedate = date('Y-m-d H:i:s',strtotime($this->SafeGetVal($json, 'lastupdatedate', $subscription->Lastupdatedate)));
			$subscription->Comment = $this->SafeGetVal($json, 'comment', $subscription->Comment);

			$subscription->Validate();
			$errors = $subscription->GetValidationErrors();

			if (count($errors) > 0)
			{
				$this->RenderErrorJSON('Please check the form for errors',$errors);
			}
			else
			{
				$subscription->Save();
				$this->RenderJSON($subscription, $this->JSONPCallback(), true, $this->SimpleObjectParams());
                $this->saveLog('136','Subscription update objectid:'.$pk);
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
	 * API Method deletes an existing Subscription record and render response as JSON
	 */
	public function Delete()
	{
		try
		{
						
			// TODO: if a soft delete is prefered, change this to update the deleted flag instead of hard-deleting

			$pk = $this->GetRouter()->GetUrlParam('objectid');
			$subscription = $this->Phreezer->Get('Subscription',$pk);

			$subscription->Delete();

			$output = new stdClass();

			$this->RenderJSON($output, $this->JSONPCallback());
            $this->saveLog('137','Subscription delete objectid:'.$pk);

		}
		catch (Exception $ex)
		{
			$this->RenderExceptionJSON($ex);
		}
	}
}

?>
