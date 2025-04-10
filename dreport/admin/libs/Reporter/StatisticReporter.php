<?php
/** @package    Dreports::Reporter */

/** import supporting libraries */
require_once("verysimple/Phreeze/Reporter.php");

/**
 * This is an example Reporter based on the Statistic object.  The reporter object
 * allows you to run arbitrary queries that return data which may or may not fith within
 * the data access API.  This can include aggregate data or subsets of data.
 *
 * Note that Reporters are read-only and cannot be used for saving data.
 *
 * @package Dreports::Model::DAO
 * @author ClassBuilder
 * @version 1.0
 */
class StatisticReporter extends Reporter
{

	// the properties in this class must match the columns returned by GetCustomQuery().
	// 'CustomFieldExample' is an example that is not part of the `t_statistics` table
	public $OperationType;

	public $Id;
	public $Opertype;
	public $Operid;
	public $Datetime;
	public $Description;

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
			`t_opertype`.`o_opertype_descr` as OperationType
			,`t_statistics`.`s_id` as Id
			,`t_statistics`.`s_opertype` as Opertype
			,`t_statistics`.`s_operid` as Operid
			,`t_statistics`.`s_datetime` as Datetime
			,`t_statistics`.`s_description` as Description
		from `t_statistics` 
        inner join `t_opertype` on `t_opertype`.`o_opertype_id` = `t_statistics`.`s_opertype`";
        

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
		$sql = "select count(1) as counter from `t_statistics` 
                inner join `t_opertype` on `t_opertype`.`o_opertype_id` = `t_statistics`.`s_opertype`";
        
                
		// the criteria can be used or you can write your own custom logic.
		// be sure to escape any user input with $criteria->Escape()
		$sql .= $criteria->GetWhere();

		return $sql;
	}
}

?>