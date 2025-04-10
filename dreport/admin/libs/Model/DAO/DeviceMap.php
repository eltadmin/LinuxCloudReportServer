<?php
/** @package    Dreports::Model::DAO */

/** import supporting libraries */
require_once("verysimple/Phreeze/IDaoMap.php");
require_once("verysimple/Phreeze/IDaoMap2.php");

/**
 * DeviceMap is a static class with functions used to get FieldMap and KeyMap information that
 * is used by Phreeze to map the DeviceDAO to the t_devices datastore.
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
class DeviceMap implements IDaoMap, IDaoMap2
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
			self::$FM["Id"] = new FieldMap("Id","t_devices","d_id",true,FM_TYPE_INT,11,null,true);
			self::$FM["Deviceid"] = new FieldMap("Deviceid","t_devices","d_deviceid",false,FM_TYPE_VARCHAR,30,null,false);
			self::$FM["Objectname"] = new FieldMap("Objectname","t_devices","d_objectname",false,FM_TYPE_VARCHAR,30,null,false);
			self::$FM["Objectid"] = new FieldMap("Objectid","t_devices","d_objectid",false,FM_TYPE_VARCHAR,20,null,false);
			self::$FM["Objectpswd"] = new FieldMap("Objectpswd","t_devices","d_objectpswd",false,FM_TYPE_VARCHAR,20,null,false);
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