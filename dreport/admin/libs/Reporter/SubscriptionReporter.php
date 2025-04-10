<?php
/** @package    Dreports::Reporter */

/** import supporting libraries */
require_once("verysimple/Phreeze/Reporter.php");

/**
 * This is an example Reporter based on the Subscription object.  The reporter object
 * allows you to run arbitrary queries that return data which may or may not fith within
 * the data access API.  This can include aggregate data or subsets of data.
 *
 * Note that Reporters are read-only and cannot be used for saving data.
 *
 * @package Dreports::Model::DAO
 * @author ClassBuilder
 * @version 1.0
 */
class SubscriptionReporter extends Reporter
{

	// the properties in this class must match the columns returned by GetCustomQuery().
	// 'CustomFieldExample' is an example that is not part of the `t_subscriptions` table
	public $CustomFieldExample;

	public $Objectid;
	public $Objectname;
	public $Expiredate;
	public $Customername;
	public $Eik;
	public $Address;
	public $Hostname;
	public $Appip;
	public $Apptype;
	public $Appver;
	public $Appdbtype;
	public $Active;
	public $Createdate;
	public $Lastupdatedate;
	public $Comment;

	/*
	* GetCustomQuery returns a fully formed SQL statement.  The result columns
	* must match with the properties of this reporter object.
	*
	* @see Reporter::GetCustomQuery
	* @param Criteria $criteria
	* @return string SQL statement
	*/
	static function GetCustomQuery($criteria)
	{
		$sql = "select
			'custom value here...' as CustomFieldExample
			,`t_subscriptions`.`s_objectid` as Objectid
			,`t_subscriptions`.`s_objectname` as Objectname
			,`t_subscriptions`.`s_expiredate` as Expiredate
			,`t_subscriptions`.`s_customername` as Customername
			,`t_subscriptions`.`s_eik` as Eik
			,`t_subscriptions`.`s_address` as Address
			,`t_subscriptions`.`s_hostname` as Hostname
			,`t_subscriptions`.`s_appip` as Appip
			,`t_subscriptions`.`s_apptype` as Apptype
			,`t_subscriptions`.`s_appver` as Appver
			,`t_subscriptions`.`s_appdbtype` as Appdbtype
			,`t_subscriptions`.`s_active` as Active
			,`t_subscriptions`.`s_createdate` as Createdate
			,`t_subscriptions`.`s_lastupdatedate` as Lastupdatedate
			,`t_subscriptions`.`s_comment` as Comment
		from `t_subscriptions`";

		// the criteria can be used or you can write your own custom logic.
		// be sure to escape any user input with $criteria->Escape()
		$sql .= $criteria->GetWhere();
        
		$sql .= $criteria->GetOrder();

		return $sql;
	}
	
	/*
	* GetCustomCountQuery returns a fully formed SQL statement that will count
	* the results.  This query must return the correct number of results that
	* GetCustomQuery would, given the same criteria
	*
	* @see Reporter::GetCustomCountQuery
	* @param Criteria $criteria
	* @return string SQL statement
	*/
	static function GetCustomCountQuery($criteria)
	{
		$sql = "select count(1) as counter from `t_subscriptions`";

		// the criteria can be used or you can write your own custom logic.
		// be sure to escape any user input with $criteria->Escape()
		$sql .= $criteria->GetWhere();

		return $sql;
	}
}

?>