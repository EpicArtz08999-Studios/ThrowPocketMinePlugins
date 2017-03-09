<?php

namespace FakeServer\Packets;

#include <rules/DataPacket.h>

class LoginPacket extends \pocketmine\network\protocol{
	const NETWORK_ID = Info::LOGIN_PACKET;

	public $username;
	public $protocol1;
	public $protocol2;
	public $clientId;
	public $slim = false;
	public $skin = null;

	public function decode(){
		$this->username = $this->getString();
		$this->protocol1 = $this->getInt();
		$this->protocol2 = $this->getInt();
		if($this->protocol1 < Info::CURRENT_PROTOCOL){ //New fields!
			$this->setBuffer(null, 0); //Skip batch packet handling
			return;
		}
		$this->clientId = $this->getLong();
		$this->clientUUID = $this->getUUID();
		$this->serverAddress = $this->getString();
		$this->clientSecret = $this->getString();

		$this->slim = $this->getByte() > 0;
		$this->skin = $this->getString();
	}

	public function encode(){
	}
}