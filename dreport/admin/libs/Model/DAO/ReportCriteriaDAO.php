<?php
/** @package    Dreports::Model::DAO */

/** import supporting libraries */
require_once("verysimple/Phreeze/Criteria.php");

/**
 * ReportCriteria allows custom querying for the Report object.
 *
 * WARNING: THIS IS AN AUTO-GENERATED FILE
 *
 * This file should generally not be edited by hand except in special circumstances.
 * Add any custom business logic to the ModelCriteria class which is extended from this class.
 * Leaving this file alone will allow easy re-generation of all DAOs in the event of schema changes
 *
 * @inheritdocs
 * @package Dreports::Model::DAO
 * @author ClassBuilder
 * @version 1.0
 */
class ReportCriteriaDAO extends Criteria
{

	public $Id_Equals;
	public $Id_NotEquals;
	public $Id_IsLike;
	public $Id_IsNotLike;
	public $Id_BeginsWith;
	public $Id_EndsWith;
	public $Id_GreaterThan;
	public $Id_GreaterThanOrEqual;
	public $Id_LessThan;
	public $Id_LessThanOrEqual;
	public $Id_In;
	public $Id_IsNotEmpty;
	public $Id_IsEmpty;
	public $Id_BitwiseOr;
	public $Id_BitwiseAnd;
	public $Objectid_Equals;
	public $Objectid_NotEquals;
	public $Objectid_IsLike;
	public $Objectid_IsNotLike;
	public $Objectid_BeginsWith;
	public $Objectid_EndsWith;
	public $Objectid_GreaterThan;
	public $Objectid_GreaterThanOrEqual;
	public $Objectid_LessThan;
	public $Objectid_LessThanOrEqual;
	public $Objectid_In;
	public $Objectid_IsNotEmpty;
	public $Objectid_IsEmpty;
	public $Objectid_BitwiseOr;
	public $Objectid_BitwiseAnd;
	public $Name_Equals;
	public $Name_NotEquals;
	public $Name_IsLike;
	public $Name_IsNotLike;
	public $Name_BeginsWith;
	public $Name_EndsWith;
	public $Name_GreaterThan;
	public $Name_GreaterThanOrEqual;
	public $Name_LessThan;
	public $Name_LessThanOrEqual;
	public $Name_In;
	public $Name_IsNotEmpty;
	public $Name_IsEmpty;
	public $Name_BitwiseOr;
	public $Name_BitwiseAnd;
	public $FriendlynameBg_Equals;
	public $FriendlynameBg_NotEquals;
	public $FriendlynameBg_IsLike;
	public $FriendlynameBg_IsNotLike;
	public $FriendlynameBg_BeginsWith;
	public $FriendlynameBg_EndsWith;
	public $FriendlynameBg_GreaterThan;
	public $FriendlynameBg_GreaterThanOrEqual;
	public $FriendlynameBg_LessThan;
	public $FriendlynameBg_LessThanOrEqual;
	public $FriendlynameBg_In;
	public $FriendlynameBg_IsNotEmpty;
	public $FriendlynameBg_IsEmpty;
	public $FriendlynameBg_BitwiseOr;
	public $FriendlynameBg_BitwiseAnd;
	public $FriendlynameEn_Equals;
	public $FriendlynameEn_NotEquals;
	public $FriendlynameEn_IsLike;
	public $FriendlynameEn_IsNotLike;
	public $FriendlynameEn_BeginsWith;
	public $FriendlynameEn_EndsWith;
	public $FriendlynameEn_GreaterThan;
	public $FriendlynameEn_GreaterThanOrEqual;
	public $FriendlynameEn_LessThan;
	public $FriendlynameEn_LessThanOrEqual;
	public $FriendlynameEn_In;
	public $FriendlynameEn_IsNotEmpty;
	public $FriendlynameEn_IsEmpty;
	public $FriendlynameEn_BitwiseOr;
	public $FriendlynameEn_BitwiseAnd;
	public $Href_Equals;
	public $Href_NotEquals;
	public $Href_IsLike;
	public $Href_IsNotLike;
	public $Href_BeginsWith;
	public $Href_EndsWith;
	public $Href_GreaterThan;
	public $Href_GreaterThanOrEqual;
	public $Href_LessThan;
	public $Href_LessThanOrEqual;
	public $Href_In;
	public $Href_IsNotEmpty;
	public $Href_IsEmpty;
	public $Href_BitwiseOr;
	public $Href_BitwiseAnd;
	public $SqlBg_Equals;
	public $SqlBg_NotEquals;
	public $SqlBg_IsLike;
	public $SqlBg_IsNotLike;
	public $SqlBg_BeginsWith;
	public $SqlBg_EndsWith;
	public $SqlBg_GreaterThan;
	public $SqlBg_GreaterThanOrEqual;
	public $SqlBg_LessThan;
	public $SqlBg_LessThanOrEqual;
	public $SqlBg_In;
	public $SqlBg_IsNotEmpty;
	public $SqlBg_IsEmpty;
	public $SqlBg_BitwiseOr;
	public $SqlBg_BitwiseAnd;
	public $SqlEn_Equals;
	public $SqlEn_NotEquals;
	public $SqlEn_IsLike;
	public $SqlEn_IsNotLike;
	public $SqlEn_BeginsWith;
	public $SqlEn_EndsWith;
	public $SqlEn_GreaterThan;
	public $SqlEn_GreaterThanOrEqual;
	public $SqlEn_LessThan;
	public $SqlEn_LessThanOrEqual;
	public $SqlEn_In;
	public $SqlEn_IsNotEmpty;
	public $SqlEn_IsEmpty;
	public $SqlEn_BitwiseOr;
	public $SqlEn_BitwiseAnd;
	public $Appdbtype_Equals;
	public $Appdbtype_NotEquals;
	public $Appdbtype_IsLike;
	public $Appdbtype_IsNotLike;
	public $Appdbtype_BeginsWith;
	public $Appdbtype_EndsWith;
	public $Appdbtype_GreaterThan;
	public $Appdbtype_GreaterThanOrEqual;
	public $Appdbtype_LessThan;
	public $Appdbtype_LessThanOrEqual;
	public $Appdbtype_In;
	public $Appdbtype_IsNotEmpty;
	public $Appdbtype_IsEmpty;
	public $Appdbtype_BitwiseOr;
	public $Appdbtype_BitwiseAnd;
	public $Order_Equals;
	public $Order_NotEquals;
	public $Order_IsLike;
	public $Order_IsNotLike;
	public $Order_BeginsWith;
	public $Order_EndsWith;
	public $Order_GreaterThan;
	public $Order_GreaterThanOrEqual;
	public $Order_LessThan;
	public $Order_LessThanOrEqual;
	public $Order_In;
	public $Order_IsNotEmpty;
	public $Order_IsEmpty;
	public $Order_BitwiseOr;
	public $Order_BitwiseAnd;
	public $Color_Equals;
	public $Color_NotEquals;
	public $Color_IsLike;
	public $Color_IsNotLike;
	public $Color_BeginsWith;
	public $Color_EndsWith;
	public $Color_GreaterThan;
	public $Color_GreaterThanOrEqual;
	public $Color_LessThan;
	public $Color_LessThanOrEqual;
	public $Color_In;
	public $Color_IsNotEmpty;
	public $Color_IsEmpty;
	public $Color_BitwiseOr;
	public $Color_BitwiseAnd;

}

?>