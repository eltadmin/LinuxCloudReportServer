<?php
/** @package    Dreports::Model::DAO */

/** import supporting libraries */
require_once("verysimple/Phreeze/Criteria.php");

/**
 * SubscriptionCriteria allows custom querying for the Subscription object.
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
class SubscriptionCriteriaDAO extends Criteria
{

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
	public $Objectname_Equals;
	public $Objectname_NotEquals;
	public $Objectname_IsLike;
	public $Objectname_IsNotLike;
	public $Objectname_BeginsWith;
	public $Objectname_EndsWith;
	public $Objectname_GreaterThan;
	public $Objectname_GreaterThanOrEqual;
	public $Objectname_LessThan;
	public $Objectname_LessThanOrEqual;
	public $Objectname_In;
	public $Objectname_IsNotEmpty;
	public $Objectname_IsEmpty;
	public $Objectname_BitwiseOr;
	public $Objectname_BitwiseAnd;
	public $Expiredate_Equals;
	public $Expiredate_NotEquals;
	public $Expiredate_IsLike;
	public $Expiredate_IsNotLike;
	public $Expiredate_BeginsWith;
	public $Expiredate_EndsWith;
	public $Expiredate_GreaterThan;
	public $Expiredate_GreaterThanOrEqual;
	public $Expiredate_LessThan;
	public $Expiredate_LessThanOrEqual;
	public $Expiredate_In;
	public $Expiredate_IsNotEmpty;
	public $Expiredate_IsEmpty;
	public $Expiredate_BitwiseOr;
	public $Expiredate_BitwiseAnd;
	public $Customername_Equals;
	public $Customername_NotEquals;
	public $Customername_IsLike;
	public $Customername_IsNotLike;
	public $Customername_BeginsWith;
	public $Customername_EndsWith;
	public $Customername_GreaterThan;
	public $Customername_GreaterThanOrEqual;
	public $Customername_LessThan;
	public $Customername_LessThanOrEqual;
	public $Customername_In;
	public $Customername_IsNotEmpty;
	public $Customername_IsEmpty;
	public $Customername_BitwiseOr;
	public $Customername_BitwiseAnd;
	public $Eik_Equals;
	public $Eik_NotEquals;
	public $Eik_IsLike;
	public $Eik_IsNotLike;
	public $Eik_BeginsWith;
	public $Eik_EndsWith;
	public $Eik_GreaterThan;
	public $Eik_GreaterThanOrEqual;
	public $Eik_LessThan;
	public $Eik_LessThanOrEqual;
	public $Eik_In;
	public $Eik_IsNotEmpty;
	public $Eik_IsEmpty;
	public $Eik_BitwiseOr;
	public $Eik_BitwiseAnd;
	public $Address_Equals;
	public $Address_NotEquals;
	public $Address_IsLike;
	public $Address_IsNotLike;
	public $Address_BeginsWith;
	public $Address_EndsWith;
	public $Address_GreaterThan;
	public $Address_GreaterThanOrEqual;
	public $Address_LessThan;
	public $Address_LessThanOrEqual;
	public $Address_In;
	public $Address_IsNotEmpty;
	public $Address_IsEmpty;
	public $Address_BitwiseOr;
	public $Address_BitwiseAnd;
	public $Hostname_Equals;
	public $Hostname_NotEquals;
	public $Hostname_IsLike;
	public $Hostname_IsNotLike;
	public $Hostname_BeginsWith;
	public $Hostname_EndsWith;
	public $Hostname_GreaterThan;
	public $Hostname_GreaterThanOrEqual;
	public $Hostname_LessThan;
	public $Hostname_LessThanOrEqual;
	public $Hostname_In;
	public $Hostname_IsNotEmpty;
	public $Hostname_IsEmpty;
	public $Hostname_BitwiseOr;
	public $Hostname_BitwiseAnd;
	public $Appip_Equals;
	public $Appip_NotEquals;
	public $Appip_IsLike;
	public $Appip_IsNotLike;
	public $Appip_BeginsWith;
	public $Appip_EndsWith;
	public $Appip_GreaterThan;
	public $Appip_GreaterThanOrEqual;
	public $Appip_LessThan;
	public $Appip_LessThanOrEqual;
	public $Appip_In;
	public $Appip_IsNotEmpty;
	public $Appip_IsEmpty;
	public $Appip_BitwiseOr;
	public $Appip_BitwiseAnd;
	public $Apptype_Equals;
	public $Apptype_NotEquals;
	public $Apptype_IsLike;
	public $Apptype_IsNotLike;
	public $Apptype_BeginsWith;
	public $Apptype_EndsWith;
	public $Apptype_GreaterThan;
	public $Apptype_GreaterThanOrEqual;
	public $Apptype_LessThan;
	public $Apptype_LessThanOrEqual;
	public $Apptype_In;
	public $Apptype_IsNotEmpty;
	public $Apptype_IsEmpty;
	public $Apptype_BitwiseOr;
	public $Apptype_BitwiseAnd;
	public $Appver_Equals;
	public $Appver_NotEquals;
	public $Appver_IsLike;
	public $Appver_IsNotLike;
	public $Appver_BeginsWith;
	public $Appver_EndsWith;
	public $Appver_GreaterThan;
	public $Appver_GreaterThanOrEqual;
	public $Appver_LessThan;
	public $Appver_LessThanOrEqual;
	public $Appver_In;
	public $Appver_IsNotEmpty;
	public $Appver_IsEmpty;
	public $Appver_BitwiseOr;
	public $Appver_BitwiseAnd;
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
	public $Active_Equals;
	public $Active_NotEquals;
	public $Active_IsLike;
	public $Active_IsNotLike;
	public $Active_BeginsWith;
	public $Active_EndsWith;
	public $Active_GreaterThan;
	public $Active_GreaterThanOrEqual;
	public $Active_LessThan;
	public $Active_LessThanOrEqual;
	public $Active_In;
	public $Active_IsNotEmpty;
	public $Active_IsEmpty;
	public $Active_BitwiseOr;
	public $Active_BitwiseAnd;
	public $Createdate_Equals;
	public $Createdate_NotEquals;
	public $Createdate_IsLike;
	public $Createdate_IsNotLike;
	public $Createdate_BeginsWith;
	public $Createdate_EndsWith;
	public $Createdate_GreaterThan;
	public $Createdate_GreaterThanOrEqual;
	public $Createdate_LessThan;
	public $Createdate_LessThanOrEqual;
	public $Createdate_In;
	public $Createdate_IsNotEmpty;
	public $Createdate_IsEmpty;
	public $Createdate_BitwiseOr;
	public $Createdate_BitwiseAnd;
	public $Lastupdatedate_Equals;
	public $Lastupdatedate_NotEquals;
	public $Lastupdatedate_IsLike;
	public $Lastupdatedate_IsNotLike;
	public $Lastupdatedate_BeginsWith;
	public $Lastupdatedate_EndsWith;
	public $Lastupdatedate_GreaterThan;
	public $Lastupdatedate_GreaterThanOrEqual;
	public $Lastupdatedate_LessThan;
	public $Lastupdatedate_LessThanOrEqual;
	public $Lastupdatedate_In;
	public $Lastupdatedate_IsNotEmpty;
	public $Lastupdatedate_IsEmpty;
	public $Lastupdatedate_BitwiseOr;
	public $Lastupdatedate_BitwiseAnd;
	public $Comment_Equals;
	public $Comment_NotEquals;
	public $Comment_IsLike;
	public $Comment_IsNotLike;
	public $Comment_BeginsWith;
	public $Comment_EndsWith;
	public $Comment_GreaterThan;
	public $Comment_GreaterThanOrEqual;
	public $Comment_LessThan;
	public $Comment_LessThanOrEqual;
	public $Comment_In;
	public $Comment_IsNotEmpty;
	public $Comment_IsEmpty;
	public $Comment_BitwiseOr;
	public $Comment_BitwiseAnd;

}

?>