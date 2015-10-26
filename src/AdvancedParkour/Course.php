<?php

namespace AdvancedParkour;

use AdvancedParkour\Main;

use pocketmine\Player;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\block\Block;

class Course extends Main{
	
	private $name;
	private $coursemaker;
	private $date;
	private $level;
	private $floory;
	private $startblock;
	private $startpos;
	private $startyaw;
	private $endpos;
	private $checkpoints;

	public function __construct($name, $coursemaker, $date, Level $level, $floory, Position $startblock, $startyaw, Position $startpos, Position $endpos, array $checkpoints){
		$this->name = $name;
		$this->coursemaker = $coursemaker;
		$this->date = $date;
		$this->startyaw = $startyaw;
		$this->level = $level;
		$this->floory = $floory;
		$this->startblock = $startblock;
		$this->startpos = $startpos;
		$this->endpos = $endpos;
		$this->checkpoints = $checkpoints;
	}

	public function getCourseName(){
		return $this->name;
	}

	public function getStartYaw(){
		return $this->startyaw;
	}

	public function getCourseLevel(){
		return $this->level;
	}

	public function getCourseMaker(){
		return $this->coursemaker;
	}

	public function getFloorY(){
		return $this->floory;
	}

	public function getDateOfCreation(){
		return $this->date;
	}

	public function getStartPosition(){
		return $this->startpos;
	}

	public function getEndPosition(){
		return $this->endpos;
	}

	public function getStartBlock(){
		return $this->startblock;
	}

	public function getCheckpoints(){
		return $this->checkpoints;
	}

	public function setTime(Player $player, $time){
		if(Runner::$mysql->query("SELECT * FROM AdvancedParkour WHERE username = '".$player->getName()."';")->num_rows == 0){
			Runner::$mysql->query("INSERT INTO AdvancedParkour
				VALUES('".$player->getName()."', '".$this->getCourseName()."', ".$time.");");
		}else $query = Runner::$mysql->query("UPDATE AdvancedParkour SET highscore = ".$time." WHERE username = '".$player->getName()."' AND course = '".$this->getCourseName()."';");
	}

	public function getTime(Player $player){
		if(Runner::$mysql->query("SELECT * FROM AdvancedParkour WHERE username = '".$player->getName()."';")->num_rows == 0){
			return null;
		}else{
			$query = Runner::$mysql->query("SELECT * FROM AdvancedParkour WHERE username = '".$player->getName()."' AND course = '".$this->getCourseName()."';");
			return (float)$query->fetch_assoc()['highscore'];
		}
	}

	public function getTopTen(){
		$query = Runner::$mysql->query("SELECT * FROM AdvancedParkour WHERE course = '".$this->getCourseName()."' ORDER BY highscore ASC LIMIT 10;");
		$topten = array();
		while($row = $query->fetch_assoc()){
			$topten[++$row] = array("username"=>$row['username'],"highscore"=>$row['highscore']);
		}
		return $topten;
	}

	public function getBestPlayer(){
		return $this->getTopTen()[1];
	}
}
