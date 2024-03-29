<?php

$writer = new XMLWriter('1.0', 'utf-8');
$writer->openURI('php://output');
$writer->setIndent(false); 
$writer->startDocument();

try {

	if (empty($_GET['name'])) {
	
		throw new Exception("no mapname given");
	}

	$offset = $_GET['offset'] ? $_GET['offset'] : 0;
	$limit = $_GET['limit'] ? $_GET['limit'] : 50;
	
	require('../dbh.php');	
	
	$stmt = $dbh->prepare("SELECT `map_id` AS `id`, COUNT(`player_id`) AS `num` FROM `highscores` `h` INNER JOIN `maps` `m` ON `m`.`id` = `h`.`map_id` WHERE `m`.`name` = :name GROUP BY `map_id`;");
	$stmt->bindValue("name", $_GET['name']);
	if (!$stmt->execute()) {
	
		throw new Exception("Internal error. Please try again later.");
	}
	
	if (!$map = $stmt->fetchObject()) {
	
		throw new Exception("map not found.");
	}
	
	$writer->startElement("map");
	$writer->startElement("count");
	$writer->text($map->num);
	$writer->endElement();
	
	$stmt = $dbh->prepare("SELECT `p`.`name` AS `player`, `h`.`time`, `h`.`created_at`, `h`.`races` FROM `highscores` `h` INNER JOIN `players` `p` ON `p`.`id` = `h`.`player_id` WHERE `h`.`map_id` = :map_id ORDER BY `h`.`time` ASC LIMIT " . $offset . "," . $limit . ";");
	$stmt->bindValue("map_id", $map->id);
	
	if (!$stmt->execute()) {
	
		throw new Exception("Internal error. Please try again later.");
	}
	
	$writer->startElement('positions');
	
	$no = $offset + 1;
	while ($position = $stmt->fetchObject()) {
		
		$writer->startElement('position');
		$writer->startElement('no');
		$writer->text($no++);
		$writer->endElement();
		$writer->startElement('player');
		$writer->text(substr($position->player, 0, 18));
		$writer->endElement();
		$writer->startElement('races');
		$writer->text($position->races);
		$writer->endElement();
		$writer->startElement('time');
		$writer->text($position->time);
		$writer->endElement();
		$writer->startElement('created_at');
		$writer->text(preg_replace("/:\d\d$/", "", $position->created_at));
		$writer->endElement();
		$writer->endElement();

		$lastMap = $position->map;
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