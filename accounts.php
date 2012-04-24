<?php

$source = $_GET;
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
			$writer->startElement('available');
			$writer->text((int)Accounts::isNameAvailable($source['name']));
			$writer->endELement();
			break;
			
		case 'auth':
			$session = Accounts::authenticate($source['name'], $source['pass']);
			$writer->startElement('result');
			$writer->text((int)($session !== false));
			$writer->endElement();
			if ($session !== false) {
			
				$writer->startElement('session');
				$writer->text($session);
				$writer->endElement();
			}
			break;
			
		case 'pass':
			$success = Accounts::setPassword($source['pass'], $source['session']);
			$writer->startElement('result');
			$writer->text((int)$success);
			$writer->endElement();
			if ($success) {
			
				$writer->startElement('session');
				$writer->text(Accounts::renewSession($source['session']));
				$writer->endElement();
			}
			break;
			
		case 'session':
			Accounts::checkSession($source['id']);
			break;
			
		default:
			throw new Exception("No such action");
	}

} catch (Exception $e) {

	$writer->startElement('error');
	$writer->startCData();
	$writer->text($e->getMessage());
	$writer->endCData();
	$writer->endElement();
}

$writer->endElement();

header('Content-type: text/xml');
$writer->endDocument();
echo $writer->outputMemory();