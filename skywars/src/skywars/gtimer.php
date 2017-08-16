<?php

namespace skywars;

use pocketmine\scheduler\PluginTask;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use skywars\main;

class gtimer extends PluginTask
{
    public function __construct(PluginBase $owner)
    {
        parent::__construct($owner);
    }

    public function onRun($currentTick)
    {
        $this->getOwner()->timer();
    }

    public function stop()
    {
        $this->getOwner()->getServer()->getScheduler()->cancelTask($this->getTaskId());
    }
}   