<?php

namespace MineBlock\OpLogin;

use pocketmine\Player;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\inventory\InventoryOpenEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class OpLogin extends PluginBase implements Listener{
	public function onEnable(){
		$this->player = [];
		$this->loadYml();
		foreach($this->getServer()->getOnlinePlayers() as $p) $this->sendLogin($p, true);
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new Task($this, [$this, "onTick"]), 100);
	}

	public function onDisable(){
		$this->getServer()->getScheduler()->cancelTasks($this);
	}

	public function onCommand(CommandSender $sender, Command $cmd, $label, array $sub){
		$rm = TextFormat::RED . "Usage: /" . $cmd->getName();
		$mm = "[OpLogin] ";
		$ik = $this->isKorean();
		$cmd = strtolower($cmd->getName());
		if($sender->getName() == "CONSOLE" && $cmd !== "loginop"){
			$sender->sendMessage($mm . ($ik ? "게임내에서만 사용가능합니다." : "Please run this command in-game"));
		}elseif(isset($sub[0]) && $sub[0] !== ""){
			switch(strtolower($cmd)){
				case "oplogin":
					if($this->isLogin($sender)){
						$sender->sendMessage($mm . ($ik ? "이미 로그인되었습니다." : "Already logined"));
					}else{
						$this->login($sender, $sub[0], false, isset($sub[1]) ? $sub[1] : "");
					}
				break;
				case "opregister":
					if($this->isRegister($sender)){
						$sender->sendMessage($mm . ($ik ? "이미 가입되었습니다." : "Already registered"));
					}elseif(!isset($sub[1]) || $sub[1] == "" || $sub[0] !== $sub[1]){
						return false;
					}elseif(strlen($sub[0]) < 5){
						$sender->sendMessage($mm . ($ik ? "비밀번호가 너무 짧습니다." : "Password is too short"));
						return false;
					}else{
						$this->register($sender, $sub[0]);
						if(!$sender->isOp()) $this->login($sender, $sub[0]);
					}
				break;
				case "oploginop":
					if(!isset($sub[1]) || $sub[1] == "" || !isset($this->lg[strtolower($sub[1])])){
						$sender->sendMessage($mm . ($ik ? "<플레이어명>을 확인해주세요." : "Please check <PlayerName>"));
						return false;
					}else{
						$sub[1] = strtolower($sub[1]);
						$pass = $this->lg[strtolower($sub[1])]["PW"];
						switch(strtolower($sub[0])){
							case "unregister":
							case "ur":
							case "u":
							case "탈퇴":
								unset($this->lg[$sub[1]]);
								$sender->sendMessage($mm . ($ik ? "$sub[1] 님의 비밀번호을 제거합니다." : "Delete $sub[1] 's password"));
							break;
							case "change":
							case "c":
								if(!isset($sub[2]) || $sub[2] == ""){
									$sender->sendMessage($mm . ($ik ? "<플레이어명>을 확인해주세요." : "Please check <PlayerName>"));
									return false;
								}else{
									$this->lg[$sub[1]]["PW"] = hash("sha256", $sub[2]);
									$sender->sendMessage($mm . $sub[1] . ($ik ? "님의 비밀번호를 바꿨습니다. : " : "'s Password is changed : ") . "$sub[2]");
								}
							break;
						}
					}
					$this->saveYml();
				break;
				default:
					return false;
				break;
			}
		}else return false;
		return true;
	}

	public function onPlayerJoin(PlayerJoinEvent $event){
		if($event->getPlayer()->isOp()) $this->sendLogin($event->getPlayer(), true);
	}

	public function onPlayerRespawn(PlayerRespawnEvent $event){
		if($event->getPlayer()->isOp()) $this->spawn[strtolower($event->getPlayer()->getName())] = $event->getRespawnPosition();
	}

	public function onPlayerQuit(PlayerQuitEvent $event){
		if($event->getPlayer()->isOp()) $this->unLogin($event->getPlayer());
	}

	public function onPlayerCommandPreprocess(PlayerCommandPreprocessEvent $event){
		if(!$this->isLogin($p = $event->getPlayer()) && !in_array(strtolower(explode(" ", substr($event->getMessage(), 1))[0]), ["opregister", "oplogin"])) $event->setCancelled($this->sendLogin($p));
	}

	public function onPlayerInteract(PlayerInteractEvent $event){
		if(!$this->isLogin($event->getPlayer())) $event->setCancelled();
	}

	public function onBlockBreak(BlockBreakEvent $event){
		if(!$this->isLogin($event->getPlayer())) $event->setCancelled();
	}

	public function onBlockPlace(BlockPlaceEvent $event){
		if(!$this->isLogin($event->getPlayer())) $event->setCancelled();
	}

	public function onPlayerDropItem(PlayerDropItemEvent $event){
		if(!$this->isLogin($event->getPlayer())) $event->setCancelled();
	}

	public function onPlayerItemConsume(PlayerItemConsumeEvent $event){
		if(!$this->isLogin($event->getPlayer())) $event->setCancelled();
	}

	public function onInventoryOpen(InventoryOpenEvent $event){
		if(!$this->isLogin($event->getPlayer())) $event->setCancelled();
	}

	public function onEntityDamage(EntityDamageEvent $event){
		if(!$this->isLogin($event->getEntity())) $event->setCancelled();
	}

	public function onPlayerMove(){
		if(!$this->isLogin($event->getPlayer())){
			$event->setTo($event->getFrom());
			$event->setCancelled();
		}
	}		

	public function onTick(){
		foreach($this->getServer()->getOnlinePlayers() as $p){
			if(!$this->isLogin($p)) $this->sendLogin($p);
		}
	}

	public function register($p, $pw){
		$p->sendMessage("[Login] " . ($this->isKorean() ? "가입 완료" : "Register to complete"));
		$this->lg[strtolower($p->getName())] = ["PW" => hash("sha256", $pw), "IP" => $p->getAddress()];
		$this->saveYml();
	}

	public function isRegister($p){
		return $p instanceof Player && isset($this->lg[strtolower($p->getName())]) ? true : false;
	}

	public function login($p, $pw = "", $auto = false, $opw = ""){
		if($this->isLogin($p)) return;
		$n = strtolower($p->getName());
		$ik = $this->isKorean();
		if(!isset($this->lg[$n])){
			$p->sendMessage("[Login]" . ($ik ? "당신은 가입되지 않았습니다.\n/Register <비밀번호> <비밀번호>" : "You are not registered.\n/Register <Password> <Password>"));
			return false;
		}
		if($pw) $pw = hash("sha256", $pw);
		if(!$auto){
			if($pw !== $this->lg[$n]["PW"]){
				$p->sendMessage("[Login] " . ($ik ? "로그인 실패" : "Login to failed"));
				return false;
			}
			if($p->isOp()){
				$op = (new Config($this->getDataFolder() . "! Login-OP.yml", Config::YAML, ["Op" => false, "PW" => "op"]))->getAll();
				if($op["Op"] && $op["PW"] !== $opw){
					$p->sendMessage("[Login] " . ($ik ? "로그인 실패" : "Login to failed"));
					$p->sendMessage("/Login " . ($ik ? "<비밀번호> <오피비밀번호>" : "<Password> <OP PassWord>"));
					return true;
				}
			}
		}
		$this->player[$n] = true;
		$this->lg[$n]["IP"] = $p->getAddress();
		$p->getInventory()->sendContents($p);
		$p->getInventory()->sendArmorContents($p);
		$p->sendMessage("[Login] " . ($auto ? ($ik ? "자동" : "Auto") : "") . ($ik ? "로그인 완료" : "Login to complete"));
		$this->saveYml();
		return true;
	}

	public function isLogin($p){
 		return !$p->isOp() || $p instanceof Player && isset($this->player[strtolower($p->getName())]) ? true : false;
	}

	public function unLogin($p){
		unset($this->player[strtolower($p->getName())]);
	}

	public function sendLogin($p, $l = false){
		if($p instanceof Player){
			$mm = "[OpLogin] ";
			$ik = $this->isKorean();
			$n = strtolower($p->getName());
			if($this->isLogin($p)){
			}elseif(!isset($this->lg[$n])){
				$p->sendMessage($mm . ($ik ? "당신은 가입되지 않았습니다.\n/OpRegister <비밀번호> <비밀번호>" : "You are not registered.\n/OpRegister <Password> <Password>"));
			}elseif($l && $this->lg[$n]["IP"] == $p->getAddress()){
				$this->login($p, "", true);
			}else{
				$p->sendMessage($mm . ($ik ? "당신은 로그인하지 않았습니다.\n/OpLogin <비밀번호>" : "You are not logined.\n/OpLogin <Password>"));
			}
		}
		return true;
	}

	public function loadYml(){
		@mkdir($this->getDataFolder());
		$this->lg = (new Config($this->getDataFolder() . "Login.yml", Config::YAML))->getAll();
	}

	public function saveYml(){
		ksort($this->lg);
		$lg = new Config($this->getDataFolder() . "Login.yml", Config::YAML);
		$lg->setAll($this->lg);
		$lg->save();
	}

	public function isKorean(){
		return strtolower($this->getServer()->getLanguage()->getName()) == "korean";
	}
}