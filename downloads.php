<?php

require('../dbh.php');
if (isset($_GET['map'])) {

	$stmt = $dbh->prepare("SELECT `name`, `download` FROM `maps` WHERE `filename` = :filename;");
	$stmt->bindValue("filename", $_GET['map']);
	if (!$stmt->execute()) {
	
		die();
	}
	
	if ($map = $stmt->fetchObject()) {
	
		echo $map->name . ',' . $map->download;
		exit;
	}

} else {

	$writer = new XMLWriter('1.0', 'utf-8');
	$writer->openURI('php://output');
	$writer->setIndent(false); 
	$writer->startDocument();

	try {	
		
		$stmt = $dbh->prepare("SELECT * FROM `maps` WHERE `download` IS NOT NULL ORDER BY name ASC;");
		
		if (!$stmt->execute()) {
		
			throw new Exception("Internal error. Please try again later.");
		}
		
		$writer->startElement('maps');
		
		while ($map = $stmt->fetchObject()) {
		
			$writer->startElement('map');
			
			$writer->startElement('id');
			$writer->text($map->id);
			$writer->endElement();	
			
			$writer->startElement('name');
			$writer->text($map->name);
			$writer->endElement();		
			
			$writer->startElement('author');
			$writer->text($map->author);
			$writer->endElement();			
			
			$writer->startElement('skill');
			$writer->text($map->skill);
			$writer->endElement();		

			$writer->startElement('download');
			$writer->text($map->download);
			$writer->endElement();

			$writer->startElement('filename');
			$writer->text($map->filename);
			$writer->endElement();	
			
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
}