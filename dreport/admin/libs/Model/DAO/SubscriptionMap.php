<?php
/** @package    Dreports::Model::DAO */

/** import supporting libraries */
require_once("verysimple/Phreeze/IDaoMap.php");
require_once("verysimple/Phreeze/IDaoMap2.php");

/**
 * SubscriptionMap is a static class with functions used to get FieldMap and KeyMap information that
 * is used by Phreeze to map the SubscriptionDAO to the t_subscriptions datastore.
 *
 * WARNING: THIS IS AN AUTO-GENERATED FILE
 *
 * This file should generally not be edited by hand except in special circumstances.
 * You can override the default fetching strategies for KeyMaps in _config.php.
 * Leaving this file alone will allow easy re-generation of all DAOs in the event of schema changes
 *
 * @package Dreports::Model::DAO
 * @author ClassBuilder
 * @version 1.0
 */
class SubscriptionMap implements IDaoMap, IDaoMap2
{

	private static $KM;
	private static $FM;
	
	/**
	 * {@inheritdoc}
	 */
	public static function AddMap($property,FieldMap $map)
	{
		self::GetFieldMaps();
		self::$FM[$property] = $map;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public static function SetFetchingStrategy($property,$loadType)
	{
		self::GetKeyMaps();
		self::$KM[$property]->LoadType = $loadType;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function GetFieldMaps()
	{
		if (self::$FM == null)
		{
			self::$FM = Array();
			self::$FM["Objectid"] = new FieldMap("Objectid","t_subscriptions","s_objectid",true,FM_TYPE_VARCHAR,20,null,false);
			self::$FM["Objectname"] = new FieldMap("Objectname","t_subscriptions","s_objectname",false,FM_TYPE_VARCHAR,255,null,false);
			self::$FM["Expiredate"] = new FieldMap("Expiredate","t_subscriptions","s_expiredate",false,FM_TYPE_DATE,null,null,false);
			self::$FM["Customername"] = new FieldMap("Customername","t_subscriptions","s_customername",false,FM_TYPE_VARCHAR,255,null,false);
			self::$FM["Eik"] = new FieldMap("Eik","t_subscriptions","s_eik",false,FM_TYPE_VARCHAR,30,null,false);
			self::$FM["Address"] = new FieldMap("Address","t_subscriptions","s_address",false,FM_TYPE_VARCHAR,255,null,false);
			self::$FM["Hostname"] = new FieldMap("Hostname","t_subscriptions","s_hostname",false,FM_TYPE_VARCHAR,255,null,false);
			self::$FM["Appip"] = new FieldMap("Appip","t_subscriptions","s_appip",false,FM_TYPE_VARCHAR,50,null,false);
			self::$FM["Apptype"] = new FieldMap("Apptype","t_subscriptions","s_apptype",false,FM_TYPE_VARCHAR,50,null,false);
			self::$FM["Appver"] = new FieldMap("Appver","t_subscriptions","s_appver",false,FM_TYPE_VARCHAR,20,null,false);
			self::$FM["Appdbtype"] = new FieldMap("Appdbtype","t_subscriptions","s_appdbtype",false,FM_TYPE_VARCHAR,20,null,false);
			self::$FM["Active"] = new FieldMap("Active","t_subscriptions","s_active",false,FM_TYPE_TINYINT,1,"1",false);
			self::$FM["Createdate"] = new FieldMap("Createdate","t_subscriptions","s_createdate",false,FM_TYPE_TIMESTAMP,null,"CURRENT_TIMESTAMP",false);
			self::$FM["Lastupdatedate"] = new FieldMap("Lastupdatedate","t_subscriptions","s_lastupdatedate",false,FM_TYPE_TIMESTAMP,null,"CURRENT_TIMESTAMP",false);
			self::$FM["Comment"] = new FieldMap("Comment","t_subscriptions","s_comment",false,FM_TYPE_VARCHAR,1024,null,false);
		}
		return self::$FM;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function GetKeyMaps()
	{
		if (self::$KM == null)
		{
			self::$KM = Array();
		}
		return self::$KM;
	}

}

?>