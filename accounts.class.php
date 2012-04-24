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
	public static function setPassword($name, $newPassword, $oldPassword = NULL) {
	
		if (empty($newPassword)) {
		
			return false;
		}
	
		if (!self::isNameAvailable($name, $oldPassword)) {
		
			return false;
		}
		
		$salt = substr(Encrypter::password(uniqid(microtime())), 0, 32);
		$stmt = self::$dbh->prepare("INSERT INTO `players` (`name`, `password`, `salt`, `points`, `created_at`) VALUES(:name, :password, :salt, 0, NOW()) ON DUPLICATE KEY UPDATE `password` = VALUES(`password`), `salt` = VALUES(`salt`);");
		$stmt->bindValue("name", $name);
		$stmt->bindValue("salt", $salt);
		$stmt->bindValue("password", Encrypter::password($newPassword, $salt));
		if (!$stmt->execute()) {
		
			throw new Exception("Could not insert/update player");
		}
		
		return true;
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
		
		return Encrypter::encryptSession($sessionID);
	}
	
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