<?php

$source = $_POST;

$now = date("Y-m-d H:i:s");

$writer = new XMLWriter('1.0', 'utf-8');
$writer->openURI('php://output');
$writer->setIndent(false); 
$writer->startDocument();

try {

	if (empty($source['name']) && empty($source['session'])) {
	
		throw new Exception("No player or session given");
	}
	
	require('../dbh.php');
	require('accounts.class.php');
	Accounts::$dbh = $dbh;
	
	$writer->startElement('update');
	
	if (array_key_exists('session', $source) && !empty($source['session'])) {
	
		// get the  player by session
		if (!$player = Accounts::getPlayerBySession($source['session'])) {
		
			throw new Exception("Could not find player by session");
		}
		
		$writer->startElement('session');
		$writer->text(Accounts::renewSession($source['session']));
		$writer->endElement();
		
	} else {
	
		// get the  player by name
		$stmt = $dbh->prepare("SELECT * FROM `players` WHERE `name` = :name LIMIT 1");
		$stmt->bindValue("name", $source['name']);
		if (!$stmt->execute()) {
		
			throw new Exception("Error loading player '". $source['name'] . "'");
		}
		
		if (!$player = $stmt->fetchObject()) {
		
			throw new Exception("Could not find player '". $source['name'] . "'");
		}
		
		if ($player->password != NULL || $player->session != NULL) {
		
			throw new Exception("Player '". $source['name'] . "' must use his session");
		}
	}
	
	$writer->startElement('name');
	$writer->text($player->name);
	$writer->endElement();
	
	$writer->startElement('points');
	$writer->text($player->points);
	$writer->endElement();
	
	$writer->startElement('updated');
	$writer->text($now);
	$writer->endElement();
	
	// get the player's overall poition
	$stmt = $dbh->prepare("SELECT id FROM `players` ORDER BY `points` DESC");
	if (!$stmt->execute()) {
	
		throw new Exception("Error loading players");
	}
	
	$position = 0;
	do { $position ++; } while($stmt->fetchColumn() != $player->id);
	
	$writer->startElement('position');
	$writer->text($position);
	$writer->endElement();
	
	// get position on maps the player has played
	$writer->startElement('maps');
	
	$stmt = $dbh->prepare("SELECT `maps`.`id`, `maps`.`filename` FROM `maps` INNER JOIN `highscores` ON `maps`.`id` = `highscores`.`map_id` WHERE `highscores`.`player_id` = :player_id GROUP BY `maps`.`id`");
	$stmt->bindValue("player_id", $player->id);
	if (!$stmt->execute()) {
	
		throw new Exception("Error loading map IDs");
	}
	
	while ($map = $stmt->fetchObject()) {
		
		$stmt3 = $dbh->prepare("SELECT `time` FROM `highscores` WHERE `player_id` = :player_id AND `map_id` = :map_id LIMIT 1");
		$stmt3->bindValue("player_id", $player->id);
		$stmt3->bindValue("map_id", $map->id);
		if (!$stmt3->execute()) {

			throw new Exception("Error loading own highscore");
		}

		$ownRecord = $stmt3->fetchColumn();

		$stmt2 = $dbh->prepare("SELECT `player_id`, `time`, `created_at` FROM `highscores` WHERE `map_id` = :map_id AND `created_at` > :from AND `created_at` < :to AND `time` < :time ORDER BY `time` ASC");
		$stmt2->bindValue("map_id", $map->id);
		$stmt2->bindValue("from", isset($source['updated']) && !empty($source['updated']) ? $source['updated'] : 0);
		$stmt2->bindValue("to", $now);
		$stmt2->bindValue("time", $ownRecord);
		if (!$stmt2->execute()) {
		
			throw new Exception("Error loading races");
		}
		
		$beatenBy = array();
		while ($highscore = $stmt2->fetchObject()) { 
			
			if ($highscore->player_id == $player->id) {
				break;
			}
			
			$stmt3 = $dbh->prepare("SELECT `name` FROM `players` WHERE `id` = :id LIMIT 1;");
			$stmt3->bindValue("id", $highscore->player_id);
			if (!$stmt3->execute()) {
			
				throw new Exception("Error loading player '". $highscore->player_id ."'");
			}
			
			$beatenBy[] = array(
				'player' => $stmt3->fetchColumn(),
				'time' => $highscore->time
			);
		}
		
		if (count($beatenBy)) {
		
			$writer->startElement("map");
			$writer->startElement("name");
			$writer->text($map->filename);
			$writer->endElement();
			$writer->startElement("beaten_by");
			foreach ($beatenBy as $info) {
				
				$writer->startElement("player");
				$writer->startElement("name");
				$writer->text($info['player']);
				$writer->endElement();
				$writer->startElement("time");
				$writer->text($info['time']);
				$writer->endElement();
				$writer->endElement();
			}
			$writer->endElement();
			$writer->endElement();
		}
	}
	
	$writer->endElement();
		
	$writer->endElement();

} catch (Exception $e) {

	$writer->startElement('error');
	$writer->text($e->getMessage());
	$writer->endElement();
}
	
header('Content-type: text/xml');
$writer->endDocument();
echo $writer->outputMemory();