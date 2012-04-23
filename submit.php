<?php

set_time_limit(0);

$source = $_POST;

require('../api_key.php');
if (is_array($source) && count($source) &&
	array_key_exists('map', $source) &&
	array_key_exists('player', $source) &&
	array_key_exists('time', $source) &&
	array_key_exists('key', $source) &&
	$source['key'] == $apiKey) {
	
	require('../dbh.php');	
	
	try {
		$stmt = $dbh->prepare("SELECT id FROM `maps` WHERE `filename` = :filename LIMIT 1;");
		$stmt->bindValue('filename', $source['map']);

		if (!$stmt->execute() || !$map = $stmt->fetchObject()) {

			throw new Exception("could not select maps");
		}

		$stmt = $dbh->prepare("SELECT id FROM `players` WHERE `name` = :name LIMIT 1;");
		$stmt->bindValue('name', $source['player']);
		if (!$stmt->execute()) {

			throw new Exception("could not select player");
		}

		if (!$player = $stmt->fetchObject()) {

			$stmt = $dbh->prepare("INSERT INTO `players` (`name`, `created_at`) VALUES(:name, NOW());");
			$stmt->bindValue('name', $source['player']);
			if (!$stmt->execute()) {

				throw new Exception("could not insert player");
			}

			$player = (object)array('id' => $dbh->lastInsertId());
		}

		$stmt = $dbh->prepare("INSERT INTO `races` (`map_id`, `player_id`, `time`, `created_at`) VALUES(:map_id, :player_id, :time, NOW());");
		$stmt->bindValue("map_id", $map->id);
		$stmt->bindValue("player_id", $player->id);
		$stmt->bindValue("time", $source['time']);
		if (!$stmt->execute()) {

			throw new Exception("could not insert race");
		}

		$stmt = $dbh->prepare("INSERT INTO `highscores` (`map_id`, `player_id`, `time`, `races`, `created_at`) VALUES(:map_id, :player_id, :time, :races, NOW()) ON DUPLICATE KEY UPDATE `races` = `races` + 1, `created_at` = IF( VALUES(`time`) < `time` OR `time` = 0, VALUES(`created_at`), `created_at`), `time` = IF( VALUES(`time`) < `time` OR `time` = 0, VALUES(`time`), `time`);");
		$stmt->bindValue("map_id", $map->id);
		$stmt->bindValue("player_id", $player->id);
		$stmt->bindValue("time", $source['time']);
		$stmt->bindValue("races", 1);
		if (!$stmt->execute()) {

			throw new Exception("could not update highscores");
		}

		// recalculate points
		$stmt = $dbh->prepare("UPDATE `highscores` SET `points` = 0 WHERE `map_id` = :map_id");
		$stmt->bindValue("map_id", $map->id);
		if (!$stmt->execute()) {

			throw new Exception("could not update highscores");
		}

		$limit = 50;
		$stmt = $dbh->prepare("SELECT `player_id` FROM `highscores` WHERE `map_id` = :map_id ORDER BY `time` ASC LIMIT " . $limit);
		$stmt->bindValue("map_id", $map->id);
		if (!$stmt->execute()) {

			throw new Exception("could not select highscores");
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

				throw new Exception("could not update highscores");
			}
		}

		$stmt = $dbh->prepare("UPDATE `players` `p` SET `points` = (SELECT SUM(`points`) FROM `highscores` `h` WHERE `h`.`player_id` = `p`.`id`)");
		if (!$stmt->execute()) {

			throw new Exception("could not update players");
		}

	} catch (Exception $e) {

		echo $e->getMessage();
	}

} else {

	echo 'invalid request';
}
