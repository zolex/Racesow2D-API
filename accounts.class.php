<?php

require('../encrypter.php'); // Encrypter

/**
 * Class to manage accounts
 *
 */
class Accounts {

    /**
	 * PDO databasehandler
	 */
	public static $dbh;

	/**
	 * See if the given name is available
	 * 
	 * @param String $name
	 */
	public static function isNameAvailable($name, $password = NULL) {
	
		if (empty($name)) {
		
			throw new Exception("No name given");
		}
		
		if ($name == 'player') {
		
			return false;
		}
		
		$stmt = self::$dbh->prepare("SELECT `password`, `salt` FROM `players` WHERE `name` = :name LIMIT 1");
		$stmt->bindValue("name", $name);
		if (!$stmt->execute()) {
		
			throw new Exception("Could not select player");
		}
		
		$player = $stmt->fetchObject();
		if ($player === false) {
		
			return true;
			
		} else {
		
			if ($player->password === NULL) {
			
				return true;
				
			} else {
			
				return $player->password == Encrypter::password($password, $player->salt);
			 }
		}
	}
	
	/**
	 * Set the password for the given name
	 * 
	 * @param String $name
	 * @param String $password
	 */
	public static function setPassword($password, $session) {
	
		if (empty($password) || empty($session)) {
		
			return false;
		}
		
		$salt = substr(Encrypter::password(uniqid(microtime())), 0, 32);
		$stmt = self::$dbh->prepare("UPDATE `players` SET `password` = :password, `salt` = :salt WHERE `session` = :session LIMIT 1");
		$stmt->bindValue("salt", $salt);
		$stmt->bindValue("password", Encrypter::password($password, $salt));
		$stmt->bindValue("session", Encrypter::decryptSession($session));
		if (!$stmt->execute()) {
		
			throw new Exception("Could not insert/update player");
		}
		
		return $stmt->rowCount() == 1;
	}
	
	/**
	 * Authenticate with the giver name and password
	 * 
	 * @param String $name
	 * @param String $password
	 */
	public static function authenticate($name, $password) {
	
		$stmt = self::$dbh->prepare("SELECT `password`, `salt` FROM `players` WHERE `name` = :name LIMIT 1");
		$stmt->bindValue("name", $name);
		if (!$stmt->execute()) {
		
			throw new Exception("Could not select player");
		}
		
		$player = $stmt->fetchObject();
		if ($player === false) {
		
			return false;
			
		} else if ($player->password == Encrypter::password($password, $player->salt)) {
		
			return self::createSession($name, false);
			
		} else {
		
			return false;
		}
	}
	
	/**
	  * Register a name
	  *
	  * @param String $name
	  * @param String $password
	  * @param String $email
	  */
	public static function register($name, $password, $email) {
	
		if (!self::isNameAvailable($name)) {

			return false;
		}
		
		$salt = substr(Encrypter::password(uniqid(microtime())), 0, 32);
		$stmt = self::$dbh->prepare("INSERT INTO `players` (`name`, `password`, `salt`, `email`) VALUES(:name, :password, :salt, :email) ON DUPLICATE KEY UPDATE `password` = VALUES(`password`), `salt` = VALUES(`salt`), `email` = VALUES(`email`)");
		$stmt->bindValue("name", $name);
		$stmt->bindValue("email", $email);
		$stmt->bindValue("salt", $salt);
		$stmt->bindValue("password", Encrypter::password($password, $salt));
		if (!$stmt->execute()) {
		
			throw new Exception("Could not register player");
		}
		
		$result = $stmt->rowCount();
		return ($result == 1 || $result == 2);
	}
	
	/**
	 * Get a player by his session
	 *
	 * @param String $sessionID
	 * @return Object
	 */
	public static function getPlayerBySession($sessionID) {
	
		$sessionID = Encrypter::decryptSession($sessionID);
		$stmt = self::$dbh->prepare("SELECT * FROM `players` WHERE `session` = :session LIMIT 1");
		$stmt->bindValue("session", $sessionID);
		if (!$stmt->execute()) {
		
			throw new Exception("Could not load player by session");
		}
		
		return $stmt->fetchObject();
	}
	
	/**
	 * Create a new session for the given player
	 *
	 * @param String $name
	 * @return String
	 */
	public static function createSession($name, $check = true) {
	
		if ($check) {
		
			$stmt = self::$dbh->prepare("SELECT COUNT(`id`) FROM `players` WHERE `name` = :name LIMIT 1");
			$stmt->bindValue("name", $name);
			if (!$stmt->execute()) {
			
				throw new Exception("Could not select player");
			}
			
			if (!$stmt->fetchColum()) {
			
				return false;
			}
		}
		
		do {
		
			$sessionID = Encrypter::password(uniqid(microtime()));
			$stmt = self::$dbh->prepare("SELECT COUNT(`id`) FROM `players` WHERE `session` = :session LIMIT 1");
			$stmt->bindValue("session", $sessionID);
			if (!$stmt->execute()) {
	
				throw new Exception("Could not check session");
			}
			
		} while($stmt->fetchColumn());
		
		$stmt = self::$dbh->prepare("UPDATE `players` SET `session` = :session WHERE `name` = :name LIMIT 1");
		$stmt->bindValue("name", $name);
		$stmt->bindValue("session", $sessionID);
		if (!$stmt->execute()) {
	
			throw new Exception("Could not create session");
		}
		
		if ($stmt->rowCount() == 1) {
		
			return Encrypter::encryptSession($sessionID);
			
		} else {
		
			return false;
		}
	}
	/**
	 * Renew a new session
	 *
	 * @param String $name
	 * @return String
	 */
	public static function renewSession($oldSessionID) {
	
		do {
		
			$newSessionID = Encrypter::password(uniqid(microtime()));
			$stmt = self::$dbh->prepare("SELECT COUNT(`id`) FROM `players` WHERE `session` = :session LIMIT 1");
			$stmt->bindValue("session", $newSessionID);
			if (!$stmt->execute()) {
	
				throw new Exception("Could not check session");
			}
			
		} while($stmt->fetchColumn());
		
		$stmt = self::$dbh->prepare("UPDATE `players` SET `session` = :new_session WHERE `session` = :old_session LIMIT 1");
		$stmt->bindValue("new_session", $newSessionID);
		$stmt->bindValue("old_session", Encrypter::decryptSession($oldSessionID));
		if (!$stmt->execute()) {
	
			throw new Exception("Could not create session");
		}
		
		if ($stmt->rowCount() == 1) {
		
			return Encrypter::encryptSession($newSessionID);
			
		} else {
		
			return false;
		}
	}
	
	/**
	 * Check if the session exists
	 *
	 * @param String $sessionID
	 */
	public static function checkSession($sessionID) {
	
		$sessionID = Encrypter::decryptSession($sessionID);
		$stmt = self::$dbh->prepare("SELECT `name` FROM `players` WHERE `session` = :session LIMIT 1");
		$stmt->bindValue("session", $sessionID);
		if (!$stmt->execute()) {
		
			throw new Exception("Could not load session");
		}
		
		return $stmt->rowCount() == 1;
	}
}