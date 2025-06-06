<?php
/** @package Dreports::Model::DAO */

/** import supporting libraries */
require_once("verysimple/Phreeze/Phreezable.php");
require_once("UserMap.php");

/**
 * UserDAO provides object-oriented access to the user table.  This
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
class UserDAO extends Phreezable
{
	/** @var int */
	public $Id;

	/** @var int */
	public $RoleId;

	/** @var string */
	public $Username;

	/** @var string */
	public $Password;

	/** @var string */
	public $FirstName;

	/** @var string */
	public $LastName;


	/**
	 * Returns the foreign object based on the value of RoleId
	 * @return Role
	 */
	public function GetRole()
	{
		return $this->_phreezer->GetManyToOne($this, "u_role");
	}


}
?>