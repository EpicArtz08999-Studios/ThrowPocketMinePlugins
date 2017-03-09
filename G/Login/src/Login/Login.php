<?php

namespace Login;

use pocketmine\Player;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as Color;

class Login extends \pocketmine\plugin\PluginBase implements \pocketmine\event\Listener{
	const PASSWORD = 0;
	const CLIENTID = 1;
	
	public function onLoad(){
		$this->sendLogin = str_repeat(Color::DARK_RED . str_repeat("∅", 20) . "\n", 4);
		$this->player = [];
	}

	public function onEnable(){
		$this->loadYml();
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new Task($this, [$this, "onTick"]), 5);
	}

	public function onCommand(\pocketmine\command\CommandSender $sender, \pocketmine\command\Command $cmd, $label, array $sub){
		$ik = $this->isKorean();
		if(!$sender instanceof Player && ($cmd = strtolower($cmd->getName())) !== "loginop"){
			$sender->sendMessage(Color::YELLOW . "[Login] " . ($ik ? "게임내에서만 사용가능합니다. " : "Please run this command in-game"));
		}elseif(isset($sub[0]) && $sub[0] !== ""){
			switch($cmd){
				case "login":
					if($this->isLogin($sender)){
						$sender->sendMessage(Color::YELLOW . "[Login] " . ($ik ? "이미 로그인되었습니다. " : "Already logined"));
					}else{
						$this->login($sender, $sub[0], false, isset($sub[1]) ? $sub[1] : "");
					}
					return true;
				break;
				case "register":
					if($this->isRegister($sender)){
						$sender->sendMessage(Color::YELLOW . "[Login] " . ($ik ? "이미 가입되었습니다. " : "Already registered"));
					}elseif(!isset($sub[1]) || $sub[1] == "" || $sub[0] !== $sub[1]){
						return false;
					}elseif(strlen($sub[0]) < 5){
						$sender->sendMessage(Color::YELLOW . "[Login] " . ($ik ? "비밀번호가 너무 짧습니다. " : "Password is too short"));
						return false;
					}else{
						$this->register($sender, $sub[0]);
						if(!$sender->isOp()) $this->login($sender, $sub[0]);
					}
				break;
				case "change":
					if(!isset($sub[1]) || $sub[1] == "" || $sub[0] !== $sub[1]){
						return false;
					}elseif(strlen($sub[0]) < 5){
						$sender->sendMessage(Color::YELLOW . "[Login] " . ($ik ? "비밀번호가 너무 짧습니다. " : "Password is too short"));
						return false;
					}else{
						$this->lg["Players"][strtolower($sender->getName())][self::PASSWORD] = hash("sha256", $sub[1]);
						$sender->sendMessage(Color::YELLOW . "[Login] " . $sub[1] . ($ik ? $sub[2] . "로 비밀번호를 변경하셨습니다. " : "Change your password to " . $sub[1]));
						$this->saveYml();
						return true;
					}
				break;
				case "loginop":
					switch(strtolower($sub[0])){
						case "unregister":
						case "ur":
						case "u":
						case "탈퇴":
							if(!isset($sub[1]) || $sub[1] == "" || !isset($this->lg["Players"][$sub[1] = strtolower($sub[1])])){
								$sender->sendMessage(Color::YELLOW . "[Login] $sub[1]" . ($ik ? "는 잘못된 플레이어명입니다." : " is invalid player"));
							}else{
								unset($this->lg["Players"][$sub[1]]);
								$sender->sendMessage(Color::YELLOW . "[Login] " . ($ik ? "$sub[1] 님의 비밀번호을 제거합니다. " : "Delete $sub[1] 's password"));
								$this->saveYml();
								return true;
							}
						break;
						case "change":
						case "c":
							if(!isset($sub[1]) || $sub[1] == "" || !isset($this->lg["Players"][strtolower($sub[1])])){
								$sender->sendMessage(Color::YELLOW . "[Login] $sub[1]" . ($ik ? "는 잘못된 플레이어명입니다." : " is invalid player"));
							}elseif(!isset($sub[2]) || $sub[2] == ""){
								$sender->sendMessage(Color::YELLOW . "[Login] " . ($ik ? "<플레이어명>을 확인해주세요. " : "Please check <PlayerName>"));
							}else{
								$this->lg["Players"][$sub[1]][self::PASSWORD] = hash("sha256", $sub[2]);
								$sender->sendMessage(Color::YELLOW . "[Login] " . $sub[1] . ($ik ? "님의 비밀번호를 바꿨습니다. : " : "'s Password is changed : ") . "$sub[2] ");
								$this->saveYml();
								return true;
							$this->saveYml();
							}
						break;
						case "protect":
							$this->lg["Protect"] = !$this->lg["Protect"];
							$sender->sendMessage(Color::YELLOW . "[Login] " . ($ik ? "부계정을 " . ($this->lg["Protect"] ? "방지" : "허용") . "합니다." : ($this->lg["Protect"] ? "" : "Not ") . "Protect Duplicate register"));
							$this->saveYml();
							return true;
						break;
						case "auto":
							$this->lg["Auto"] = !$this->lg["Auto"];
							$sender->sendMessage(Color::YELLOW . "[Login] " . ($ik ? "자동 로그인을 " . ($this->lg["Auto"] ? "설정" : "해제") . "합니다." : "Auto Login is " . ($this->lg["Auto"] ? "On" : "Off")));
							$this->saveYml();
							return true;
						break;
					}
				break;
			}
		}else return false;
		return false;
	}

	public function onPlayerPreLogin(\pocketmine\event\player\PlayerPreLoginEvent $event){
		$ik = $this->isKorean();
		if($this->isLogin($player = $event->getPlayer(), true)){
			$address = $player->getAddress();
			foreach($this->getServer()->getOnlinePlayers() as $onlinePlayer){
				if($onlinePlayer !== $player && strtolower($onlinePlayer->getName()) === strtolower($player->getName()) && $onlinePlayer->getAddress() === $address){
					$onlinePlayer->close($reason = Color::RED . ($ik ? "새로운 접속" : "New connections"), $reason);
					$address = false;
					break;
				}
			}
			if($address){
				$event->setKickMessage(Color::RED . "Already Login this Id");
				$event->setCancelled();
			}
		}elseif($this->lg["Protect"]){
			$cid = $player->getClientId();
			$name = strtolower($player->getName());
			foreach($this->lg["Players"] as $key => $lg){
				if($lg[self::CLIENTID] == $cid && $key != $name){
					$event->setKickMessage(Color::RED . ($ik ? "당신은 이미 가입하셨습니다. " : "Your Already Register. ") . "\n" . Color::GOLD . "     On ". Color::YELLOW . $key);
					$event->setCancelled();
					break;
				}
			}
		}
	}

	public function onPlayerQuit(\pocketmine\event\player\PlayerQuitEvent $event){
		$this->unLogin($event->getPlayer());
	}

	public function onPlayerCommandPreprocess(\pocketmine\event\player\PlayerCommandPreprocessEvent $event){
		if(!$this->isLogin($player = $event->getPlayer()) && !in_array(strtolower(explode(" ", substr($event->getMessage(), 1))[0]), ["register", "login"])) $event->setCancelled();
	}

	public function onPlayerInteract(\pocketmine\event\player\PlayerInteractEvent $event){
		if(!$this->isLogin($event->getPlayer())) $event->setCancelled();
	}

	public function onPlayerDropItem(\pocketmine\event\player\PlayerDropItemEvent $event){
		if(!$this->isLogin($event->getPlayer())) $event->setCancelled();
	}

	public function onPlayerItemConsume(\pocketmine\event\player\PlayerItemConsumeEvent $event){
		if(!$this->isLogin($event->getPlayer())) $event->setCancelled();
	}

	public function onPlayerMove(\pocketmine\event\player\PlayerMoveEvent $event){
		if(!$this->isLogin($event->getPlayer())) $event->setCancelled();
	}		

	public function onBlockBreak(\pocketmine\event\block\BlockBreakEvent $event){
		if(!$this->isLogin($event->getPlayer())) $event->setCancelled();
	}

	public function onBlockPlace(\pocketmine\event\block\BlockPlaceEvent $event){
		if(!$this->isLogin($event->getPlayer())) $event->setCancelled();
	}

	public function onEntityDamage(\pocketmine\event\entity\EntityDamageEvent $event){
		if(($player = $event->getEntity()) instanceof Player && !$this->isLogin($player)) $event->setCancelled();
	}

	public function onTick(){
		$ik = $this->isKorean();
		foreach($this->getServer()->getOnlinePlayers() as $player){
			if(!$this->isLogin($player)){
				$player->sendMessage($this->sendLogin);
				if(!isset($this->lg["Players"][strtolower($player->getName())])) $player->sendMessage(Color::RED . "[Login] " . ($ik ? "당신은 가입되지 않았습니다.\n" . Color::RED . "      /Register <비밀번호> <비밀번호>" : "You are not registered.\n" . Color::RED . "     /Register <Password> <Password>"));
				else $player->sendMessage(Color::RED . "[Login] " . ($ik ? "당신은 로그인하지 않았습니다.\n" . Color::RED .  "     /Login <비밀번호>" : "You are not logined.\n" . Color::RED . "     /Login <Password>"));
				$player->sendMessage($this->sendLogin);
			}
		}
	}

	public function register($player, $pw){
		$player->sendMessage(Color::YELLOW . "[Login] " . ($this->isKorean() ? "가입 완료" : "Register to complete"));
		$this->lg["Players"][strtolower($player->getName())] = [self::PASSWORD => hash("sha256", $pw), self::CLIENTID => $player->getClientId()];
		$this->saveYml();
	}

	public function isRegister(Player $player){
		return isset($this->lg["Players"][strtolower($player->getName())]);
	}

	public function login(Player $player, $pw = "", $auto = false){ //, $opw = ""){
		if($this->isLogin($player, "", true)) return;
		$ik = $this->isKorean();
		if(!isset($this->lg["Players"][$name = strtolower($player->getName())])){
			$player->sendMessage(Color::GOLD . "[Login] " . ($ik ? "당신은 가입되지 않았습니다.\n/Register <비밀번호> <비밀번호>" : "You are not registered.\n/Register <Password> <Password>"));
			return false;
		}
		if($pw) $pw = hash("sha256", $pw);
		if(!($auto = $auto ? $this->lg["Auto"] : false)){
			if($pw !== $this->lg["Players"][$name][self::PASSWORD]){
				$player->sendMessage(Color::RED . "[Login] " . ($ik ? "로그인 실패" : "Login to failed"));
				return false;
			}
/*
			if($player->isOp()){
				$op = (new Config($this->getDataFolder() . "! Login-OP.yml", Config::YAML, ["Op" => false, self::PASSWORD => "op"]))->getAll();
				if($op["Op"] && $op[self::PASSWORD] !== $opw){
					$player->sendMessage(Color::RED . "[Login] " . ($ik ? "로그인 실패" : "Login to failed" . Color::RED . "/Login " . ($ik ? "<비밀번호> <오피비밀번호>" : "<Password> <OP PassWord>")));
					return true;
				}
			}
*/
		}elseif(!$this->lg["Auto"]){
			$player->sendMessage($this->sendLogin . Color::RED . "[Login] " . ($ik ? "당신은 로그인하지 않았습니다.\n" . Color::RED .  "     /Login <비밀번호>" : "You are not logined.\n" . Color::RED . "     /Login <Password>") . $this->sendLogin);
			return true;
		}
		$this->player[$name] = true;
		$player->sendMessage(Color::GREEN . "[Login] " . ($auto ? ($ik ? "자동" : "Auto") : "") . ($ik ? "로그인 완료" : "Login to complete"));
		$this->saveYml();
		return true;
	}

	public function isLogin($player, $isLogin = false){
		if($player instanceof Player && isset($this->lg["Players"][$name = strtolower($player->getName())])){
			if(isset($this->player[$name])){
				return true;
			}elseif(!$isLogin){
 				if(!isset($this->lg["Players"][$name][self::CLIENTID])) $this->lg["Players"][$name][self::CLIENTID] = $player->getClientId();
				if($this->lg["Players"][$name][self::CLIENTID] == $player->getClientId()){
					$this->login($player, "", true);
					return true;
				}
			}
		}
		return false;
	}

	public function unLogin($player){
		unset($this->player[strtolower($player->getName())]);
	}

	public function loadYml(){
		@mkdir($this->getDataFolder());
		$this->lg = (new Config($this->getDataFolder() . "Login.yml", Config::YAML, ["Players" => [], "Protect" => true, "Auto" => true]))->getAll();
		if(count($this->lg["Players"]) == 0 && count($this->lg) != 3){
			$newConfig = ["Players" => [], "Protect" => true, "Auto" => true];
			foreach($this->lg as $key => $value){
				if($key == strtolower($key)) $newConfig["Players"][$key] = [self::PASSWORD => $value["PW"], self::CLIENTID => $value["CID"]];
			}
			$this->lg = $newConfig;
			$this->saveYml();
		}
	}

	public function saveYml(){
		ksort($this->lg);
		$lg = new Config($this->getDataFolder() . "Login.yml", Config::YAML);
		$lg->setAll($this->lg);
		$lg->save();
	}

	public function isKorean(){
		return $this->getServer()->getLanguage()->getName() == "\"한국어\"";
	}
}