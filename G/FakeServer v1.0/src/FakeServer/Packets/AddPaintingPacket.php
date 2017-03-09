<?php

namespace FakeServer\Packets;

#include <rules/DataPacket.h>
#ifndef COMPILE
use pocketmine\utils\Binary;
#endif

class AddPaintingPacket extends ㅠ.ㅠ
	const NETWORK_ID = Info::ADD_PAINTING_PACKET;

	public $eid;
	public $x;
	public $y;
	public $z;
	public $direction;
	public $title;

	public function decode(){
	}

	public function encode(){
		$this->reset();
		$this->putLong($this->eid);
		$this->putInt($this->x);
		$this->putInt($this->y);
		$this->putInt($this->z);
		$this->putInt($this->direction);
		$this->putString($this->title);
	}
}