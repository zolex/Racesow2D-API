<?php

if (!(int)$argc) exit; // only commandline
require(realpath(dirname(__FILE__) . '/..') . '/dbh.php');

// update overall points
$stmt = $dbh->prepare("UPDATE `players` `p` SET `points` = (SELECT SUM(`points`) FROM `highscores` `h` WHERE `h`.`player_id` = `p`.`id`)");
if (!$stmt->execute()) {

	throw new Exception("could not update players");
}