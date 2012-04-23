<?php

$writer = new XMLWriter('1.0', 'utf-8');
$writer->openURI('php://output');
$writer->setIndent(false); 
$writer->startDocument();

try {

	require('../dbh.php');	
	$stmt = $dbh->prepare("SELECT `name` FROM `maps` ORDER BY `name` ASC");
	
	if (!$stmt->execute()) {
	
		throw new Exception("Internal error. Please try again later.");
	}
	
	$writer->startElement('maps');
	while ($map = $stmt->fetchObject()) {
		
		$writer->startElement('map');
		$writer->text($map->name);
		$writer->endElement();
	}
	
	$writer->endElement();
		
} catch (Exception $e) {

	$writer->startElement('error');
	$writer->startCData();
	$writer->text($e->getMessage());
	$writer->endCData();
	$writer->endElement();
}

header('Content-type: text/xml');
$writer->endDocument();
echo $writer->outputMemory();