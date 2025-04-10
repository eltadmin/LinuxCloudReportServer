<?php
/** @package    Dreports::Reporter */

/** import supporting libraries */
require_once("verysimple/Phreeze/Reporter.php");

/**
 * This is an example Reporter based on the Report object.  The reporter object
 * allows you to run arbitrary queries that return data which may or may not fith within
 * the data access API.  This can include aggregate data or subsets of data.
 *
 * Note that Reporters are read-only and cannot be used for saving data.
 *
 * @package Dreports::Model::DAO
 * @author ClassBuilder
 * @version 1.0
 */
class ReportReporter extends Reporter
{

	// the properties in this class must match the columns returned by GetCustomQuery().
	// 'CustomFieldExample' is an example that is not part of the `t_reports` table
	public $CustomFieldExample;

	public $Id;
	public $Objectid;
	public $Name;
	public $FriendlynameBg;
	public $FriendlynameEn;
	public $Href;
	public $SqlBg;
	public $SqlEn;
	public $Appdbtype;
	public $Order;
	public $Color;

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
			,`t_reports`.`r_id` as Id
			,`t_reports`.`r_objectid` as Objectid
			,`t_reports`.`r_name` as Name
			,`t_reports`.`r_friendlyname_bg` as FriendlynameBg
			,`t_reports`.`r_friendlyname_en` as FriendlynameEn
			,`t_reports`.`r_href` as Href
			,`t_reports`.`r_sql_bg` as SqlBg
			,`t_reports`.`r_sql_en` as SqlEn
			,`t_reports`.`r_appdbtype` as Appdbtype
			,`t_reports`.`r_order` as Order
			,`t_reports`.`r_color` as Color
		from `t_reports`";

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
		$sql = "select count(1) as counter from `t_reports`";

		// the criteria can be used or you can write your own custom logic.
		// be sure to escape any user input with $criteria->Escape()
		$sql .= $criteria->GetWhere();

		return $sql;
	}
}

?>