<?php
namespace MineBlock\RespawnTime;

use pocketmine\Player;
use pocketmine\Server;
use pocketmine\entity\Entity;
use pocketmine\network\protocol\AddPlayerPacket;
use pocketmine\network\protocol\MovePlayerPacket;
use pocketmine\network\protocol\RemovePlayerPacket;

class FroatingText{
	public $closed = $f = false;
	private $server;
	private $server, $target, $time, $name, $id, $sche;
	private $show = false;
	private $x = 0, $y = 0, $z = 0, $healTick = 0;

	public function __construct($plugin, Player $target, $time = 0){
		$this->server = Server::getInstance();
		$this->target = $target;
		$this->time = $time;
		$this->name = "§c§oRespawn time : §r§4§l" . ($this->time - time(true));
		$this->AddPlayerPacket = new AddPlayerPacket();
		$this->AddPlayerPacket->eid = $this->AddplayerPacket->clientID = $this->id = bcadd("1095216660480", mt_rand(0, 0x7fffffff));
		$this->AddPlayerPacket->yaw = $this->AddPlayerPacket->pitch = $this->AddPlayerPacket->item = $this->AddPlayerPacket->meta = $this->AddPlayerPacket->slim = false;
		$this->AddPlayerPacket->skin = str_repeat("\x00", 64 * 32 * 4);
		$this->AddPlayerPacket->metadata = [
			Entity::DATA_FLAGS => [Entity::DATA_TYPE_BYTE, 1 << Entity::DATA_FLAG_INVISIBLE],
			Entity::DATA_SHOW_NAMETAG => [Entity::DATA_TYPE_BYTE, 1],
			Entity::DATA_NO_AI => [Entity::DATA_TYPE_BYTE, 1]
		];
		$this->RemovePlayerPacket = new RemovePlayerPacket();
		$this->RemovePlayerPacket->eid = $this->id;
		$this->RemovePlayerPacket->clientID = $this->id;
		$this->sche = $this->server->getScheduler()->scheduleRepeatingTask(new Task($plugin, [$this,"onTick"]), 2);
	}

	public function onTick(){
		$t = $this->target;
		if($this->closed || $this->time - time(true) <= 0){
			$this->despawn();
			if($this->sche !== null){
				$this->server->getScheduler()->cancelTask($this->sche->getTaskId());
				$this->sche = null;
			}
 		}elseif(!$t->loggedIn){
 			$this->closed = true;
		}else{
			$this->x = $t->x - (sin($this->f ? 0 : $t->getyaw() / 180 * M_PI) * cos($this->f ? 0 : $t->getPitch() / 180 * M_PI) * 5);
			$this->y = $t->y + 3.12 - (sin($this->f ? 0 : $t->getPitch() / 180 * M_PI) * 5);
			$this->z = $t->z + (cos($this->f ? 0 : $t->getyaw() / 180 * M_PI) * cos($this->f ? 0 : $t->getPitch() / 180 * M_PI) * 5);
			if($this->name !== "§c§oRespawn time : §r§4§l" . ($this->time - time(true))){
				$this->name = "§c§oRespawn time : §r§4§l" . ($this->time - time(true));
				$this->despawn();
			}
			$this->spawn();
			$pk = new MovePlayerPacket();
			$pk->eid = $this->id;
			$pk->yaw = 0;
			$pk->bodyYaw = 0;
			$pk->pitch = 0;
			$pk->x = $this->x;
			$pk->y = $this->y - 1.62;
			$pk->z = $this->z;
 			$t->dataPacket($pk);
		}
	}

	public function setName($name = ""){
		if($this->name != $name){
			$this->name = $name;
			$this->despawn();
			$this->spawn();
		}
	}

	public function getName(){
		return $this->name;
	}

	public function spawn(){
		if(!$this->show && $this->target->spawned){
			$pk = clone $this->AddPlayerPacket;
			$pk->username = $this->name;
			$pk->x = $this->x;
			$pk->y = $this->y;
			$pk->z = $this->z;
			$this->target->dataPacket($pk);
			$this->show = true;
		}
	}

	public function despawn(){
		if($this->show){
			$this->target->dataPacket($this->RemovePlayerPacket);
			$this->show = false;
		}
	}

	public function kill(){
		if(!$this->closed){
			if($this->schedule !== null){
				$this->server->getScheduler()->cancelTesk($this->schedule->getTaskId());
				$this->schedule = null;
			}
			$this->closed = true;
			return;
		}
	}
}