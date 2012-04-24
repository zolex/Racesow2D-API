<?php

$source = $_GET;
require('../dbh.php'); // $dbh
require('../encrypter.php'); // Encrypter
require('accounts.class.php'); // Encrypter

Accounts::$dbh = $dbh;

try {
	switch ($source['action']) {

		case 'check':
			Accounts::isNameAvailable($source['name']);
			break;
			
		case 'auth':
			var_dump(Accounts::authenticate($source['name'], $source['pass']));
			break;
			
		case 'pass':
			var_dump(Accounts::setPassword($source['name'], $source['newpass'], $source['oldpass']));
			break;
			
		case 'session':
			var_dump(Accounts::checkSession($source['id']));
			break;
			
		default:
			throw new Exception("No such action");
	}
} catch (Exception $e) {

	echo "Error: ". $e->getMessage();
}