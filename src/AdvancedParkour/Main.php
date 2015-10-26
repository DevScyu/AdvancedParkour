<?php

namespace AdvancedParkour;

use AdvancedParkour\Course;

use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\math\Vector3;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\command\CommandExecutor;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\Event;
use pocketmine\event\EventPriority;
use pocketmine\event\Listener;
use pocketmine\plugin\PluginManager;
use pocketmine\plugin\PluginLogger;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerInteractEvent;

class Main extends PluginBase implements Listener,CommandExecutor{

    private $course=array();
    private $courseconf;
    private $activeplayers=array();
    private $race=array();

    public function onEnable(){
        $this->getServer()->getLogger()->info("AdvancedParkour enabled");
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
		if(!is_dir($this->getDataFolder())){
			@mkdir($this->getDataFolder());
		}
		$this->courseconf = new Config($this->getDataFolder() . "course.yml", CONFIG::YAML, array(
			"Example" => [
				"date-of-creation" => "24 August 2000",
				"Course-maker" => "DRedDog",
				"level" => "world",
				"floor-y" => 46,
				"start-position" => [
					"yaw" => 360,
					"x" => 20,
					"y" => 50,
					"z" => 20,
					],
				"timer-block" => [
					"x" => 23,
					"y" => 50,
					"z" => 20,
					],
				"end-block" => [
					"x" => 40,
					"y" => 50,
					"z" => 40,
					],
				"checkpoints" => [
					"1" => [
						"yaw" => 360,
						"x" => 50,
						"y" => 20,
						"z" => 10,
					],
				],
			],
		));
        $course = (new Config($this->getDataFolder() . "course.yml"))->getAll();
		$this->db = new \SQLite3($this->getDataFolder() . "scoreboard.db");
		$this->db->exec("CREATE TABLE IF NOT EXISTS score (username VARCHAR(32), level VARCHAR(64), highscore FLOAT);");
        
		foreach($course as $name=> $info){
            $level = $this->getServer()->getLevelByName($info['level']);
            $checkpoints = array();
            if(isset($info['checkpoints'])){
                foreach($info['checkpoints'] as $checkpoint=> $cinfo){
                    $checkpoints[(int)$checkpoint] = array("yaw" => $cinfo['yaw'], "position" =>new Position($cinfo['x'],$cinfo['y'],$cinfo['z'],$level));
                }
            }
            $this->course[$name] = new Course($name, $info["Course-maker"], $info["date-of-creation"], $level, $info['floor-y'], new Position($info['timer-block']['x'],$info['timer-block']['y'],$info['timer-block']['z'],$level),$info['start-position']['yaw'],new Position($info['start-position']['x'],$info['start-position']['y'],$info['start-position']['z'],$level),new Position($info['end-block']['x'],$info['end-block']['y'],$info['end-block']['z'],$level),$checkpoints);
            $this->getLogger()->info("§cCourse §b'" . $name. "'§c has loaded. ");
        }
    }

    public function onDisable(){
        $this->getServer()->getLogger()->info("AdvancedParkour disabled");
    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args){
            if($command->getName() == "ap"){
                if($args[0]=="start"){ #/ap start <course name>
                    if(isset($args[1])){
                        if(isset($this->course[$args[1]])){
                            if(!isset($this->activeplayers[$sender->getName()])){
                                $this->activeplayers[$sender->getName()] = array("current-course" =>strtolower($args[1]), "last-set" =>0, "micro-seconds" =>0, "active" =>false, "current-checkpoint" =>0);
                                $sender->teleport($this->course[$args[1]]->getStartPosition());
                                $sender->setRotation($this->course[$args[1]]->getStartYaw(), $sender->getPitch());
                                $sender->sendMessage("§cYou have started the Course '§b" . $this->course[$args[1]]->getCourseName() . "§c'. ");
							return true;
                            }else{
                                $sender->sendMessage("§cYou are currently still playing a different course \n §cUse §b/ap leave§c to leave this c. ");
							return true;
                            }
                        }else{
                            $sender->sendMessage("§cThe Course '§b" . strtolower($args[1]) . "§c' doesn't exist. ");
							return true;
                        }
                    }else{
                        $sender->sendMessage("§cUsage: §b/ap join [Course name]");
							return true;
                    }
                }else
                if($args[0]=="leave"){
                    if(isset($this->activeplayers[$sender->getName()])){
                        unset($this->activeplayers[$sender->getName()]);
                        $sender->sendMessage("§cYou have left your current Course. ");
							return true;
                    }else{
                        $sender->sendMessage("§cYou aren't currently playing any Courses. ");
							return true;
                    }
                }else
                if($args[0]=="info"){
                    if(isset($args[1])){
                        if(isset($this->course[$args[1]])){
                            $Course = $this->course[$args[1]];
                            $currentlyplaying = array();
                            foreach($this->activeplayers as $player=> $name){
                                if($name['current-course'] == strtolower($args[1])){
                                    array_push($currentlyplaying, $player);
                                }
                            }
                            $sender->sendMessage("§cCourse info: \n §cCourse name: §b" . $Course->getCourseName() . " \n §cCourse maker: §b" . $Course->getCourseMaker() . " \n §cDate of creation: §b" . $Course->getDateOfCreation() . " \n §cCourse world name: §b" . $Course->getCourseLevel()->getName() . " \n §cYour current highscore: §b" .($Course->getTime($sender) === null ? "none" : $Course->getTime($sender)) . " \n §cCurrently playing: §b" .implode(", ",$currentlyplaying) . " \n §cFor leaderboards, please type §b/ap topten " . strtolower($args[1]));
							return true;
                        }else{
                            $sender->sendMessage("§cThis Course doesn't exist. ");
							return true;
                        }
                    }else{
                        $sender->sendMessage("§cUsage: §b/ap info [Course name]");
							return true;
                    }
                }else
                if($args[0]=="topten"){
                    if(isset($args[1])){
                        if(isset($this->course[$args[1]])){
                            $sender->sendMessage("§cTop ten laps of the Course §b" . $this->course[$args[1]]->getCourseName() . "§c:");
                            $count = 0;
                            foreach($this->course[$args[1]]->getTopTen() as $player){
                                $count++;
                                $sender->sendMessage("§c" . $count. ") §b" . $player['username']. "§c with §b" . $player['highscore']);
                            }
                        }else{
                            $sender->sendMessage("§cThis Course doesn't exist. ");
							return true;
                        }
                    }else{
                        $sender->sendMessage("§cUsage: §b/ap topten [Course name]");
							return true;
                    }
                }
                if($args[0]=="create"){
                    if(isset($args[1])){
                        $this->courseconf->set($args[1], array("date-of-creation" =>date("d/m/Y"), "Course-maker" => $sender->getName(), "level" => $sender->getLevel()->getName(), "floor-y" =>0, "start-position" =>array("x" =>0, "y" =>0, "z" =>0, "yaw" =>360), "timer-block" =>array("x" =>0, "y" =>0, "z" =>0), "end-block" =>array("x" =>0, "y" =>0, "z" =>0)));
                        $sender->sendMessage("§cNew Course '§b" . $args[1]. "§c' has been created. \n §cPlease use the follwing command to set the start position: \n §b/ap setstart " . strtolower($args[1]));
							return true;
                    }else{
                        $sender->sendMessage("§cUsage: §b/ap create [Course name]");
							return true;
                    }
                }else
                if($args[0]=="setstart"){
                    if(isset($args[1])){
                        if($this->courseconf->exists($args[1])){
                            $newCourse = $this->courseconf->get($args[1]);
                            $newCourse['start-position'] = array("x" => $sender->getFloorX(), "y" => $sender->getFloorY(), "z" => $sender->getFloorZ(),$sender->getYaw());
                            $this->courseconf->set($args[1],$newCourse);
                            $sender->sendMessage("§cAwesome! Now use the follwing command to set the floor that will reset you once you fall below/on it. \n §b/ap setfloor " . strtolower($args[1]));
							return true;
                        }else{
                            $sender->sendMessage("§cThis Course doesn't exist. ");
							return true;
                        }
                    }else{
                        $sender->sendMessage("§cUsage: §b/ap setfloor [Course name]");
							return true;
                    }
                }else
                if($args[0]=="setfloor"){
                    if(isset($args[1])){
                        if($this->courseconf->exists($args[1])){
                            $newCourse = $this->courseconf->get($args[1]);
                            $newCourse['floor-y'] = $sender->getFloorY();
                            $this->courseconf->set($args[1],$newCourse);
                            $sender->sendMessage("§cGreat! Now use the follwing command to set the timer start position. \n §b/ap settimer " . strtolower($args[1]));
							return true;
                        }else{
                            $sender->sendMessage("§cThis Course doesn't exist. ");
							return true;
                        }
                    }else{
                        $sender->sendMessage("§cUsage: §b/ap setfloor [Course name]");
							return true;
                    }
                }else
                if($args[0]=="settimer"){
                    if(isset($args[1])){
                        if($this->courseconf->exists($args[1])){
                            $newCourse = $this->courseconf->get($args[1]);
                            $newCourse['timer-block'] = array("x" => $sender->getFloorX(), "y" => $sender->getFloorY(), "z" => $sender->getFloorZ());
                            $this->courseconf->set($args[1],$newCourse);
                            $sender->sendMessage("§cAlmost there! Use the following command to set the end of the Course. \n §b/ap setend " . strtolower($args[1]) . " \n §cAfter that use §b/ap setcheckpoint " . strtolower($args[1]) . " §cfor every checkpoint you would like to set. ");
							return true;
                        }else{
                            $sender->sendMessage("§cThis Course doesn't exist. ");
							return true;
                        }
                    }else{
                        $sender->sendMessage("§cUsage: §b/ap settimer [Course name]");
							return true;
                    }
                }else
                if($args[0]=="setend"){
                    if(isset($args[1])){
                        if($this->courseconf->exists($args[1])){
                            $newCourse = $this->courseconf->get($args[1]);
                            $newCourse['end-block'] = array("x" => $sender->getFloorX(), "y" => $sender->getFloorY(), "z" => $sender->getFloorZ());
                            $this->courseconf->set($args[1],$newCourse);
                            $this->courseconf->save();
                            $sender->sendMessage("§cAwesome! Your new Course is now ready to use. \n §cRestart the server to use the new Course. \n §c(Refreshing Courses would cause too much confusion)");
							return true;
                        }else{
                            $sender->sendMessage("§cThis Course doesn't exist. ");
							return true;
                        }
                    }else{
                        $sender->sendMessage("§cUsage: §b/ap setend [Course name]");
							return true;
                    }
                }else
                if($args[0]=="setcheckpoint"){
                    if(isset($args[1])){
                        if($this->courseconf->exists($args[1])){
                            $checkpoints = $this->courseconf->get($args[1]);
                            $checkpoints['checkpoints'][(count($checkpoints['checkpoints']) + 1)] = array("yaw" => $sender->getYaw(), "x" => $sender->getFloorX(), "y" => $sender->getFloorY(), "z" => $sender->getFloorZ());
                            $this->courseconf->set($args[1],$checkpoints);
                            $this->courseconf->save();
                            $sender->sendMessage("§cNew checkpoint set. Restart the server to apply the new changes. ");
							return true;
                        }else{
                            $sender->sendMessage("§cThis Course doesn't exist. ");
							return true;
                        }
                    }else{
                        $sender->sendMessage("§cUsage: §b/ap setcheckpoint [Course name]");
							return true;
                    }
                }
            }
        return false;
    }

    public function PlayerMoveEvent(PlayerMoveEvent $event){
        $player = $event->getPlayer();
        if(isset($this->activeplayers[$player->getName()])){
            if($player->getLevel() === $this->course[$this->activeplayers[$player->getName()]['current-course']]->getCourseLevel()){
                $Course = $this->course[$this->activeplayers[$player->getName()]['current-course']];
                $pos = $this->getPlayerPos($player);
                $checkpoints = $Course->getCheckpoints();
                if($pos->equals($Course->getStartBlock(),true)){
                    if((time() - $this->activeplayers[$player->getName()]['last-set']) > 2){
                        $this->activeplayers[$player->getName()]['current-checkpoint'] = 0;
                        $this->activeplayers[$player->getName()]['last-set'] = time();
                        $this->activeplayers[$player->getName()]['micro-seconds'] = round(microtime(true) * 1000);
                        $this->activeplayers[$player->getName()]['active'] = true;
                        $player->sendMessage("§cTimer reset. ");
                    }
                }
                if($player->getFloorY() <= $Course->getFloorY()){
                    if($this->activeplayers[$player->getName()]['current-checkpoint'] == 0){
                        $player->teleport($Course->getStartPosition());
                        $player->setRotation($Course->getStartYaw(), $player->getPitch());
                        $this->activeplayers[$player->getName()]['active'] = true;
                    }else{
                        $player->teleport($checkpoints[$this->activeplayers[$player->getName()]['current-checkpoint']]['position']);
                        $player->setRotation($checkpoints[$this->activeplayers[$player->getName()]['current-checkpoint']]['yaw'], $player->getPitch());
                    }
                }
                if($this->activeplayers[$player->getName()]['active']){
                    foreach($checkpoints as $checkpoint=> $info){
                        if($pos->equals($info['position'])){
                            if($checkpoint > $this->activeplayers[$player->getName()]['current-checkpoint']){
                                $this->activeplayers[$player->getName()]['current-checkpoint'] = $checkpoint;
                                $player->sendMessage("§cCheckpoint §b" . $checkpoint. "#");
                            }
                        }
                    }
                    if($this->activeplayers[$player->getName()]['current-checkpoint'] === count($checkpoints)){
                        if($pos->equals($Course->getEndPosition(),true)){
                            $time = (round(microtime(true) * 1000) - $this->activeplayers[$player->getName()]['micro-seconds']) / 1000;
                            $player->sendMessage("§cWell done! You have completed the Course in §b" . $time. "§c seconds!");
                            if($Course->getTime($player) == null){
                                $Course->setTime($player, $time);
                            }else
                            if($Course->getTime($player) > $time){
                                $player->sendMessage("§cYou have beaten your old record of §b" . $Course->getTime($player) . "§c!");
                                $Course->setTime($player, $time);
                            }
                            $player->teleport($Course->getStartPosition());
                            $this->activeplayers[$player->getName()]['current-checkpoint'] = 0;    
                            $this->activeplayers[$player->getName()]['last-set'] = time();
                            $this->activeplayers[$player->getName()]['micro-seconds'] = round(microtime(true) * 1000);
                            $this->activeplayers[$player->getName()]['active'] = false;
                        }
                    }
                }
            }
        }
    }
	public function Update(SignChangeEvent $event){ //Thanks TahaTheHacker!
	  $line1 = $event->getLine(0);
	  $line2 = $event->getLine(1);
	  $line3 = $event->getLine(2);
	  $line4 = $event->getLine(3);
	  if($line1 == strtolower("[parkour]")){
		if(isset($this->course[$line2])){		  
			  $event->setLine(0, "§l§a[§bParkour§a]");
			  $event->setLine(1, "§l§a" . $line2);
			  $event->setLine(2, "§l§a" . $line3);
			  $event->setLine(3, "§l§a" . $line4);	  
		} else {
			  $event->getPlayer()->sendMessage("[error] The Course doesn't exist. ");
			  $event->setLine(0, "§l§c[§bError§c]");
			  $event->setLine(1, "§l§c[§bError§c]");
			  $event->setLine(2, "§l§c[§bError§c]");
			  $event->setLine(3, "§l§c[§bError§c]");
		}
	  }
	}
	public function onInteract(PlayerInteractEvent $event) {
		if($event->getBlock()->getId() == 63 || $event->getBlock()->getId() == 68) {
			$sender = $event->getPlayer();
			$sign = $event->getPlayer()->getLevel()->getTile($event->getBlock());
			$signtext = $sign->getText();
			if($signtext[0] === "§l§a[§bParkour§a]") {

			if(isset($this->course[$signtext[1]])){
				if(strtolower($signtext[3]) === "join") {
						if(!isset($this->activeplayers[$sender->getName()])){
							$this->activeplayers[$sender->getName()] = array("current-course" =>strtolower($signtext[1]), "last-set" =>0, "micro-seconds" =>0, "active" =>false, "current-checkpoint" =>0);
							$sender->teleport($this->course[$signtext[1]]->getStartPosition());
							$sender->setRotation($this->course[$signtext[1]]->getStartYaw(), $sender->getPitch());
							$sender->sendMessage("§cYou have started the Course '§b" . $this->course[$signtext[1]]->getCourseName() . "§c'. ");
						}else{
							$sender->sendMessage("§cYou are currently still playing a different course \n §cUse §b/ap leave§c to leave this course. ");
						}
				}
			}else{
						$sender->sendMessage("§cThe Course '§b" . strtolower($signtext[1]) . "§c' doesn't exist. ");
					}
			
				if(strtolower($signtext[3]) === "leave") {
					if(isset($this->course[$signtext[1]])){
						if(isset($this->activeplayers[$sender->getName()])) {
							unset($this->activeplayers[$sender->getName()]);
							$sender->sendMessage("§cYou have left your current Course. ");
						}else{
							$sender->sendMessage("§cYou aren't currently playing any Courses. ");
						}
					}else{
						$sender->sendMessage("§cThe Course '§b" . strtolower($signtext[1]) . "§c' doesn't exist. ");
					}
				}
				if(strtolower($signtext[3] === "topten")) {
					if(isset($this->course[$signtext[1]])){
						$sender->sendMessage("§cTop ten laps of the Course §b" . $this->course[$args[1]]->getCourseName() . "§c:");
						$count = 0;
						foreach($this->course[$signtext[1]]->getTopTen() as $player){
							$count++;
							$sender->sendMessage("§c" . $count. ") §b" . $player['username']. "§c with §b" . $player['highscore']);
						}
					}else{
						$sender->sendMessage("§cThis Course doesn't exist. ");
					}
				}
			} elseif($signtext[0] === "§l§c[§bError§c]") {
				$sender->sendMessage("§cThis sign does not work. ");
			}
		}
	}

    public function comparePositions($pos1,$pos2){
        if($pos1->getFloorX() == $pos2->getFloorX() and $pos1->getFloorY() == $pos2->getFloorY() and $pos1->getFloorZ() == $pos2->getFloorZ()){
            return true;
        }else{
            return false;
        }
    }

    public function getPlayerPos($player){
        return (new Position($player->getFloorX(),$player->getFloorY(),$player->getFloorZ(),$player->getLevel()));
    }
}
