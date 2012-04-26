<?php

if ($_GET['source'] == 'get') {

	$source = $_GET;
	
} else {

	$source = $_POST;
}

require('../dbh.php'); // $dbh
require('accounts.class.php'); // Accounts

Accounts::$dbh = $dbh;

$writer = new XMLWriter('1.0', 'utf-8');
$writer->openURI('php://output');
$writer->setIndent(false);
$writer->startDocument();

$writer->startElement('account');

try {
	switch ($source['action']) {

		case 'check':
			$writer->startElement('checkname');
			$writer->startElement('available');
			$writer->text((int)Accounts::isNameAvailable($source['name']));
			$writer->endELement();
			$writer->endELement();
			break;
			
		case 'auth':
			$writer->startElement('auth');
			$session = Accounts::authenticate($source['name'], $source['pass']);
			$writer->startElement('result');
			$writer->text((int)($session !== false));
			$writer->endElement();
			if ($session !== false) {
			
				$writer->startElement('session');
				$writer->text($session);
				$writer->endElement();
			}
			$writer->endElement();
			break;
			
		case 'pass':
			$writer->startElement('pass');
			$success = Accounts::setPassword($source['pass'], $source['session']);
			$writer->startElement('result');
			$writer->text((int)$success);
			$writer->endElement();
			if ($success) {
			
				$writer->startElement('session');
				$writer->text(Accounts::renewSession($source['session']));
				$writer->endElement();
			}
			$writer->endElement();
			break;
			
		case 'session':
			$writer->startElement('checksession');
			$success = Accounts::checkSession($source['id']);
			$writer->startElement('result');
			$writer->text((int)$success);
			$writer->endElement();
			if ($success) {
			
				$writer->startElement('session');
				$writer->text(Accounts::renewSession($source['id']));
				$writer->endElement();
			}
			$writer->endElement();
			break;
			
		case 'register':
			$writer->startElement('registration');
			$success = Accounts::register($source['name'], $source['pass'], $source['email']);
			$writer->startElement('result');
			$writer->text((int)$success);
			$writer->endElement();
			if ($success) {
			
				$writer->startElement('session');
				$writer->text(Accounts::createSession($source['name'], false));
				$writer->endElement();
			}
			$writer->endElement();
			break;
			
		case 'recover':
			$writer->startElement('recovery');
			$writer->startElement('result');
			$writer->text((int)Accounts::sendRecoveryCode($source['email']));
			$writer->endElement();
			$writer->endElement();
			break;
			
		default:
			throw new Exception("No such action");
	}

} catch (Exception $e) {

	$writer->startElement('error');
	$writer->text($e->getMessage());
	$writer->endElement();
}

$writer->endElement();

header('Content-type: text/xml');
$writer->endDocument();
echo $writer->outputMemory();