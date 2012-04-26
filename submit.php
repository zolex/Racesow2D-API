<?php

if ($_GET['source'] == 'get') {

	$source = $_GET;
	
} else {

	$source = $_POST;
}

$writer = new XMLWriter('1.0', 'utf-8');
$writer->openURI('php://output');
$writer->setIndent(false); 
$writer->startDocument();

require('../api_key.php');
require('../dbh.php');	
require('accounts.class.php');
Accounts::$dbh = $dbh;

define("INTERNAL_ERROR", 1);
define("SESSION_INVALID", 2);
define("NO_PLAYER", 3);
define("NO_MAP", 3);

try {

	if (!is_array($source) || !count($source) ||
		!array_key_exists('map', $source) ||
		!array_key_exists('time', $source) ||
		!array_key_exists('key', $source) ||
		$source['key'] != $apiKey) {
	
		throw new Exception("Invalid request", INTERNAL_ERROR);
	}
	
	if (empty($source['player']) && empty($source['session'])) {
	
		throw new Exception("No name or session given", NO_PLAYER);
	}
	
	$writer->startElement("submission");

	$stmt = $dbh->prepare("SELECT `id` FROM `maps` WHERE `filename` = :filename LIMIT 1;");
	$stmt->bindValue('filename', $source['map']);

	if (!$stmt->execute()) {

		throw new Exception("could not select map", INTERNAL_ERROR);
	}
	
	if (!$map = $stmt->fetchObject()) {
	
		throw new Exception("Map not found", NO_MAP);
	}

	// get player by session
	if (array_key_exists('session', $source) && !empty($source['session'])) {
	
		if (!$player = Accounts::getPlayerBySession($source['session'])) {
		
			throw new Exception("Could not find session", SESSION_INVALID);
		}
		
		$writer->startElement("session");
		$writer->text(Accounts::renewSession($source['session']));
		$writer->endElement();
	
	// get player by name or insert
	} else {
	
		$stmt = $dbh->prepare("SELECT * FROM `players` WHERE `name` = :name LIMIT 1;");
		$stmt->bindValue('name', $source['player']);
		if (!$stmt->execute()) {

			throw new Exception("Could not select player", INTERNL_ERROR);
		}

		if (!$player = $stmt->fetchObject()) {

			$stmt = $dbh->prepare("INSERT INTO `players` (`name`, `created_at`) VALUES(:name, NOW());");
			$stmt->bindValue('name', $source['player']);
			if (!$stmt->execute()) {

				throw new Exception("Could not insert player", INTERNAL_ERROR);
			}

			$player = (object)array('id' => $dbh->lastInsertId());
		
		// if player is registered
		} else if ($player->password != NULL || $player->session != NULL) {
			
			throw new Exception("Player '". $source['player'] ."' must use his session", SESSION_INVALID);
		}
	}

	// get the old points on the map
	$stmt = $dbh->prepare("SELECT `points` FROM `highscores` WHERE `player_id` = :player_id AND `map_id` = :map_id LIMIT 1");
	$stmt->bindValue("map_id", $map->id);
	$stmt->bindValue("player_id", $player->id);
	if (!$stmt->execute()) {

		throw new Exception("Could not select old points", INTERNAL_ERROR);
	}
	
	$oldPoints = (int)$stmt->fetchColumn();
	
	// add the new race
	$stmt = $dbh->prepare("INSERT INTO `races` (`map_id`, `player_id`, `time`, `created_at`) VALUES(:map_id, :player_id, :time, NOW());");
	$stmt->bindValue("map_id", $map->id);
	$stmt->bindValue("player_id", $player->id);
	$stmt->bindValue("time", $source['time']);
	if (!$stmt->execute()) {

		throw new Exception("could not insert race", INTERNAL_ERROR);
	}

	// insert or update highscores
	$stmt = $dbh->prepare("INSERT INTO `highscores` (`map_id`, `player_id`, `time`, `races`, `created_at`) VALUES(:map_id, :player_id, :time, :races, NOW()) ON DUPLICATE KEY UPDATE `races` = `races` + 1, `created_at` = IF( VALUES(`time`) < `time` OR `time` = 0, VALUES(`created_at`), `created_at`), `time` = IF( VALUES(`time`) < `time` OR `time` = 0, VALUES(`time`), `time`);");
	$stmt->bindValue("map_id", $map->id);
	$stmt->bindValue("player_id", $player->id);
	$stmt->bindValue("time", $source['time']);
	$stmt->bindValue("races", 1);
	if (!$stmt->execute()) {

		throw new Exception("could not update highscores", INTERNAL_ERROR);
	}

	// reset points for map
	$stmt = $dbh->prepare("UPDATE `highscores` SET `points` = 0 WHERE `map_id` = :map_id");
	$stmt->bindValue("map_id", $map->id);
	if (!$stmt->execute()) {

		throw new Exception("could not update highscores", INTERNAL_ERROR);
	}

	// calculate new points on map
	$limit = 50;
	$stmt = $dbh->prepare("SELECT `player_id` FROM `highscores` WHERE `map_id` = :map_id ORDER BY `time` ASC LIMIT " . $limit);
	$stmt->bindValue("map_id", $map->id);
	if (!$stmt->execute()) {

		throw new Exception("could not select highscores", INTERNAL_ERROR);
	}

	$pos = 0;
	while ($position = $stmt->fetchObject()) {

		$pos++;

		$points = $limit + 1 - $pos;
		switch ($pos) {

			case 1:
				$points += 20;
				break;

			case 2:
				$points += 11;
				break;

			case 3:
				$points += 7;
				break;
		}

		$stmt2 = $dbh->prepare("UPDATE `highscores` SET `points` = :points WHERE `map_id` = :map_id AND `player_id` = :player_id LIMIT 1;");
		$stmt2->bindValue("points", $points);
		$stmt2->bindValue("map_id", $map->id);
		$stmt2->bindValue("player_id", $position->player_id);
		if (!$stmt2->execute()) {

			throw new Exception("could not update highscores", INTERNAL_ERROR);
		}
		
		if ($position->player_id == $player->id) {
		
			$newPoints = $points;
		}
	}
	
	$writer->startElement("points");	
	$writer->text($newPoints - $oldPoints);
	$writer->endElement();
	
	$writer->endElement();

} catch (Exception $e) {

	$writer->startElement('error');
	$writer->text($e->getMessage());
	$writer->endElement();
	$writer->startElement('code');
	$writer->text($e->getCode());
	$writer->endElement();
}

header('Content-type: text/xml');
$writer->endDocument();
echo $writer->outputMemory();
