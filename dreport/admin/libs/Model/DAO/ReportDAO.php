<?php
/** @package Dreports::Model::DAO */

/** import supporting libraries */
require_once("verysimple/Phreeze/Phreezable.php");
require_once("ReportMap.php");

/**
 * ReportDAO provides object-oriented access to the t_reports table.  This
 * class is automatically generated by ClassBuilder.
 *
 * WARNING: THIS IS AN AUTO-GENERATED FILE
 *
 * This file should generally not be edited by hand except in special circumstances.
 * Add any custom business logic to the Model class which is extended from this DAO class.
 * Leaving this file alone will allow easy re-generation of all DAOs in the event of schema changes
 *
 * @package Dreports::Model::DAO
 * @author ClassBuilder
 * @version 1.0
 */
class ReportDAO extends Phreezable
{
	/** @var int */
	public $Id;

	/** @var string */
	public $Objectid;

	/** @var string */
	public $Name;

	/** @var string */
	public $FriendlynameBg;

	/** @var string */
	public $FriendlynameEn;

	/** @var string */
	public $Href;

	/** @var string */
	public $SqlBg;

	/** @var string */
	public $SqlEn;

	/** @var string */
	public $Appdbtype;

	/** @var int */
	public $Order;
	
	/** @var string */
	public $Color;

}
?>