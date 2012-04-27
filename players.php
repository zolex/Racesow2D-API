<?php

$writer = new XMLWriter('1.0', 'utf-8');
$writer->openURI('php://output');
$writer->setIndent(false); 
$writer->startDocument();

try {

	$offset = $_GET['offset'] ? $_GET['offset'] : 0;
	$limit = $_GET['limit'] ? $_GET['limit'] : 50;
	
	require('../dbh.php');	
	$stmt = $dbh->prepare("SELECT COUNT(`id`) FROM `players`;");
	if (!$stmt->execute()) {
	
		throw new Exception("Internal error. Please try again later.");
	}
	
	if (!$num = $stmt->fetchColumn()) {
	
		throw new Exception("No players found.");
	}
	
	$writer->startElement("ranking");
	$writer->startElement("count");
	$writer->text($num);
	$writer->endElement();
	
	$stmt = $dbh->prepare("SELECT `name`, `points` FROM `players` ORDER BY `points` DESC LIMIT " . $offset . "," . $limit . ";");
	if (!$stmt->execute()) {
	
		throw new Exception("Internal error. Please try again later.");
	}
	
	$writer->startElement('players');
	
	$no = $offset + 1;
	while ($player = $stmt->fetchObject()) {
		
		$writer->startElement('player');
		$writer->startElement('no');
		$writer->text($no++);
		$writer->endElement();
		$writer->startElement('name');
		$writer->text($player->name);
		$writer->endElement();
		$writer->startElement('points');
		$writer->text($player->points);
		$writer->endElement();
		$writer->endElement();
	}
	
	$writer->endElement();
	$writer->endElement();
		
} catch (Exception $e) {

	$writer->startElement('error');
	$writer->startCData();
	$writer->text($e->getMessage());
	$writer->endCData();
	$writer->endElement();
}

//header('Content-type: text/xml');
$writer->endDocument();
echo $writer->outputMemory();