<?php

namespace CropPlus\block;

use pocketmine\item\Item;

class Potato extends Crops{
	protected $id = self::POTATO_BLOCK;

	public function getName(){
		return "Potato Block";
	}

	public function getDrops(Item $item){
		$drops = [];
		if($this->meta >= 0x07){
			$drops[] = [Item::POTATO, 0, mt_rand(1, 4)];
		}else{
			$drops[] = [Item::POTATO, 0, 1];
		}
		return $drops;
	}
}