<?php

namespace skywars;

use pocketmine\scheduler\PluginTask;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use skywars\main;

class start extends PluginTask
{
    public function __construct(PluginBase $owner, $count)
    {
        parent::__construct($owner);
        $this->count = $count;
    }

    public function onRun($currentTick)
    {
        $count = $this->count--;
        $pcount = $this->getOwner()->getPlayerCount();
        $max = $this->getOwner()->getMaxPlayer();
        if($count >= 0){
            $this->getOwner()->sendPopups("§b>> §6".$count."§a    ".$pcount." / ".$max."  §b<<");
            if($count == 0){
                $this->getOwner()->start(); 
            }
        }
    }
}   