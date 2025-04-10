<?php
/** @package    Dreports::Model::DAO */

/** import supporting libraries */
require_once("verysimple/Phreeze/IDaoMap.php");
require_once("verysimple/Phreeze/IDaoMap2.php");

/**
 * ReportMap is a static class with functions used to get FieldMap and KeyMap information that
 * is used by Phreeze to map the ReportDAO to the t_reports datastore.
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
class ReportMap implements IDaoMap, IDaoMap2
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
			self::$FM["Id"] = new FieldMap("Id","t_reports","r_id",true,FM_TYPE_INT,11,null,false);
			self::$FM["Objectid"] = new FieldMap("Objectid","t_reports","r_objectid",false,FM_TYPE_VARCHAR,20,null,false);
			self::$FM["Name"] = new FieldMap("Name","t_reports","r_name",false,FM_TYPE_VARCHAR,250,null,false);
			self::$FM["FriendlynameBg"] = new FieldMap("FriendlynameBg","t_reports","r_friendlyname_bg",false,FM_TYPE_VARCHAR,254,null,false);
			self::$FM["FriendlynameEn"] = new FieldMap("FriendlynameEn","t_reports","r_friendlyname_en",false,FM_TYPE_VARCHAR,254,null,false);
			self::$FM["Href"] = new FieldMap("Href","t_reports","r_href",false,FM_TYPE_VARCHAR,254,null,false);
			self::$FM["SqlBg"] = new FieldMap("SqlBg","t_reports","r_sql_bg",false,FM_TYPE_VARCHAR,2048,null,false);
			self::$FM["SqlEn"] = new FieldMap("SqlEn","t_reports","r_sql_en",false,FM_TYPE_VARCHAR,2048,null,false);
			self::$FM["Appdbtype"] = new FieldMap("Appdbtype","t_reports","r_appdbtype",false,FM_TYPE_VARCHAR,20,null,false);
			self::$FM["Order"] = new FieldMap("Order","t_reports","r_order",false,FM_TYPE_INT,11,null,false);
			self::$FM["Color"] = new FieldMap("Color","t_reports","r_color",false,FM_TYPE_VARCHAR,254,null,false);
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