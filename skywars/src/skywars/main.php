<?php

namespace skywars;

use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\math\Vector3;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\level\Position;
use pocketmine\level\Level;
use pocketmine\item\Item;
use pocketmine\tile\Tile;
use pocketmine\tile\Chest;
use pocketmine\block\Chest as C;
use pocketmine\block\Block;
use pocketmine\utils\Config;
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\PluginTask;
use pocketmine\scheduler\CallbackTask;
use skywars\start;
use skywars\gtimer;
use pocketmine\Server;

class main extends PluginBase implements Listener
{

    public $task, $t;

	public function onEnable()
    {
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		if(!file_exists($this->getDataFolder())){
			mkdir($this->getDataFolder(), 0744, true);
		}
        $this->con = new Config($this->getDataFolder() ."config.yml", Config::YAML,
                         [
                         'タッチ'=>['x'=>'0', 'y'=>'4', 'z'=>'0'],
                         'wait'=>['x'=>'0', 'y'=>'4', 'z'=>'0', 'world'=>'world'],
                         'world'=>'world',
                         'chest'=>[],
                         'spawns'=>[],
                         'dispawn'=>['x'=>'0', 'y'=>'4', 'z'=>'0'],
                         'SaveConfigMessage'=>'false',
                         '最低人数'=>'3',
                         '最大人数'=>'8',
                         '待ち時間(s)'=>'60',
                         ]);
        $this->level = new Config($this->getDataFolder() ."level.yml", Config::YAML, []);
        $this->exp = new Config($this->getDataFolder() ."exp.yml", Config::YAML, []);
        $this->nextexp = new Config($this->getDataFolder() ."nextexp.yml", Config::YAML, []);
		$this->loadGame();
        $this->all = $this->con->getAll();
        $this->getServer()->loadLevel($this->con->get('world'));
                
	}


    public function loadGame()
    {
        $this->game['players'] = [];
        $this->game['live'] = [];
        $this->game['status'] = 'wait';
        $this->game['timer'] = 0;
    }

    public function onBreak(\pocketmine\event\block\BlockBreakEvent $ev)
    {
        $player = $ev->getPlayer();
        $level = $player->getLevel();
        if($level->getName() == $this->getServer()->getDefaultLevel()->getName()){
            if(!$player->isOp()){
                $ev->setCancelled();
            }
        }
    }


    public function onPlace(\pocketmine\event\block\BlockPlaceEvent $ev)
    {
        $player = $ev->getPlayer();
        $level = $player->getLevel();
        if($level->getName() == $this->getServer()->getDefaultLevel()->getName()){
            if(!$player->isOp()){
                $ev->setCancelled();
            }
        }
    }

    public function onJoin(\pocketmine\event\player\PlayerJoinEvent $ev)
    {
        $player = $ev->getPlayer();
        $name = $player->getName();
        if(!$this->level->exists($name)){
            $this->level->set($name, "1");
            $this->exp->set($name, "0");
            $this->nextexp->set($name, "40");
            $this->saveData();
        }
        $player->setNameTag("§b[Lv.".$this->getLevel($name)."] ".$name);
        $player->setDisplayName("§b[Lv.".$this->getLevel($name)."] ".$name." §f>");
        $player->getInventory()->clearAll();
        $player->setGamemode(0);
        $player->setHealth($player->getMaxHealth());
        $player->setFood(20);
    }

    public function onTouch(PlayerInteractEvent $ev)
    {
        $player = $ev->getPlayer();
        $block = $ev->getBlock();
        $name = $player->getName();
        $x = $block->x;
        $y = $block->y;
        $z = $block->z;
        if($this->all['タッチ']['x'].",".$this->all['タッチ']['y'].",".$this->all['タッチ']['z'] == $x.",".$y.",".$z){
            if($player->getLevel()->getName() == $this->getServer()->getDefaultLevel()->getName()){
                if($this->game['status'] == 'wait'){
                    if(!$this->isPlayer($name)){
                        if($this->getPlayerCount() <= $this->con->get("最大人数")){
                            array_push($this->game['players'], $name);
                            array_push($this->game['live'], $name);
                            if($this->getPlayerCount() == 1){
                                $this->task = $task = new start($this, $this->con->get("待ち時間(s)"));
                                $this->getServer()->getScheduler()->scheduleRepeatingTask($task, 20); //ゲーム開始までのカウントダウン
                            }
                            $player->teleport(new Position($this->all['wait']['x'], $this->all['wait']['y'], $this->all['wait']['z']), $this->getServer()->getLevelByName($this->all['wait']['world']));
                            $player->sendMessage("§l§a>§b参加しました。");
                        }else{
                            $player->sendMessage("§l§e> §c満員です。");
                        }
                     }else{ 
                        $player->sendMessage("§l§e>§bすでに参加しています。");
                     }  
                 }else{
                     $player->sendMessage("§l§e> §b現在ゲーム中です。");
                 }
             }
         }else if($block instanceof C){
             if(isset($this->opt[$name])){
                 if(!array_key_exists($this->opt[$name], $this->all['chest'])){
                     $add = [$this->opt[$name]=>['x'=>$x, 'y'=>$y, 'z'=>$z]];
                     $add = array_merge($this->all['chest'], $add);
                     $this->con->set('chest', $add);
                     $this->saveData();
                     $player->sendMessage("§bInfo§f > §aチェスト §f#[".$this->opt[$name]."] §aを §6 x: [".$x."] y: [".$y."] z: [".$z."] §aに設定しました。");
                     $ev->setCancelled();
                     unset($this->opt[$name]);
                 }else{
                     $player->sendMessage("§eInfo§f > §aすでにその名前は登録されています。");
                 }
              }
          }else if($player->getInventory()->getItemInHand()->getId() == 352){
              if($player->isOp()){
                  $data = ['x'=>$x, 'y'=>$y, 'z'=>$z];
                  $this->con->set('タッチ', $data);
                  $this->saveData();
                  $player->sendMessage("受付を設定しました");
              }
         }
    }


    public function start()
    {
        if($this->getPlayerCount() < $this->con->get("最低人数")){
            $level = $this->getServer()->getDefaultLevel();
            foreach($this->game['players'] as $name){
                $player = $this->getServer()->getPlayer($name);
                if($player instanceof Player){
                    $player->sendMessage("§l§a> §b人数が少ないため、戻ります。");
                    $player->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn());
                }
            }
            $this->loadGame();
        }else{
            $this->t = $task = new gtimer($this);
            $this->getServer()->getScheduler()->scheduleRepeatingTask($task, 20);
            $level = $this->getServer()->getLevelByName($this->con->get('world'));
            $c = 0;
            foreach($this->all['spawns'] as $spawn){
                if(isset($this->game['players'][$c])){
                    $name = $this->game['players'][$c];
                    $player = $this->getServer()->getPlayer($name);
                    if($player instanceof Player){
                        $player->sendMessage("§l§a> §bゲームが開始されました。");
                        $pos = new Position($spawn['x'], $spawn['y'], $spawn['z'], $level);
                        $player->teleport($pos);
                        $player->setSpawn($pos);
                        $this->game[$name]['life'] = 1;
                        $this->game[$name]['result'] = [
                                                        'kill'=>0,
                                                        '雪玉あて'=>0,
                                                        '生き残り'=>0
                                                       ]; //ゲーム終了後出すやつ
                     }
                 }
                 $c++;
             }
             $this->game['status'] = 'now'; 
             $this->setItems();
        }
    }


    public function getPlayerCount()
    {
        return count($this->game['players']);
    }
        

    public function getLives()
    {
        return count($this->game['live']);
    }


    public function getMaxPlayer()
    {
        return $this->con->get("最大人数");
    }


    public function isPlayer($n)
    {
        if(in_array($n, $this->game['players'])){
            return true;
        }else{
            return false;
        }
    }


    public function setItems()
    {
        $level = $this->getServer()->getLevelByName($this->con->get('world'));
        foreach($this->all['chest'] as $c){
            $pos = new Vector3($c['x'], $c['y'], $c['z']);
            $chest = $level->getTile($pos);
            if($chest instanceof Chest){
                $ran = rand(4, 6);
                $items = $this->getRandomItem($ran);
                $chest->getInventory()->setContents($items);
                $chest->saveNBT();
            }
        }
    }

    public function getRandomItem($count = 5)
    {
        $items = [
                  Item::get(268, 0, 1),
                  Item::get(261, 0, 1),
                  Item::get(262, 0, 32),
                  Item::get(332, 0, 5),
                  Item::get(364, 0, 1),
                  Item::get(298, 0, 1),
                  Item::get(299, 0, 1),
                  Item::get(300, 0, 1),
                  Item::get(301, 0, 1),
                  Item::get(302, 0, 1),
                  Item::get(303, 0, 1),
                  Item::get(304, 0, 1),
                  Item::get(306, 0, 1),
                  Item::get(17, 0, rand(4, 32)),
                  Item::get(5, 0, 32),
                  Item::get(1, 0, 5),
                  Item::get(274, 0, 1),
                  Item::get(267, 0, 1)
                 ];
        $result = [];

        for($i=1;$i<=$count;$i++){
            $rn = rand(0, count($items)-1);
            array_push($result, $items[$rn]);
        }

        return $result;
    }


    public function onDamage(\pocketmine\event\entity\EntityDamageEvent $ev)
    {
        $en = $ev->getEntity();
        if($ev instanceof \pocketmine\event\entity\EntityDamageByEntityEvent){
            $damager = $ev->getDamager();
            if($en instanceof Player && $damager instanceof Player){
                $dname = $damager->getName();
                $ename = $en->getName();
                if($this->con->get("world") == $damager->getLevel()->getName()){
                    if($dname !== $ename){
                        if(1 > $en->getHealth() - $ev->getFinalDamage()){
                            $en->sendMessage("§b§l>§6".$dname."§a に殺されました。");
                            $this->game[$dname]['result']['kill']++;
                            $en->setHealth($en->getMaxHealth());
                            $en->setFood(20);
                            if(0 < $this->game[$ename]['life']){
                                $en->teleport($en->getSpawn());
                                $en->sendTip("§a§l>§b リスポーン");
                                $this->game[$ename]['life']--;
                            }else{
                                $en->setGamemode(3);
                                $en->sendMessage("§b§l> §cゲームオーバー");
                                $en->teleport(new Vector3($this->all['dispawn']['x'], $this->all['dispawn']['y'], $this->all['dispawn']['z']));
                                $key = array_search($ename, $this->game['live']);
                                unset($this->game['live'][$key]);
                                if($this->getLives() == 1){
                                    $this->game[$dname]['result']['生き残り'] = 1;
                                    $this->end();
                                }             
                            }
                        }
                        if($ev instanceof \pocketmine\event\entity\EntityDamageByChildEntityEvent){
                            $chi = $ev->getChild();
                            if($chi instanceof \pocketmine\entity\Snowball){
                                $this->game[$dname]['result']['雪玉あて']++;
                            }
                        }
                    }else{
                        $ev->setCancelled();
                    }
                }else{
                    $ev->setCancelled();
                }
            }else{
                $ev->setCancelled();
            }
        }
    }

    public function onRe(\pocketmine\event\server\DataPacketReceiveEvent $ev)
    {
        $pk = $ev->getPacket();
        $player = $ev->getPlayer();
        $name = $player->getName();
        if($pk instanceof \pocketmine\network\protocol\MovePlayerPacket){
            if($player->y < -1){
                if($this->game['status'] == 'now'){
                    if($this->isPlayer($name)){
                        if($this->isLiver($name)){
                            $player->setHealth($player->getMaxHealth());
                            $player->setFood(20);
                            if(0 < $this->game[$name]['life']){
                                $player->sendTip("§a§l>§b リスポーン");
                                $player->teleport($player->getSpawn());
                                $this->game[$name]['life']--;
                            }else{
                                $player->setGamemode(3);
                                $player->sendMessage("§b§l> §cゲームオーバー");
                                $player->teleport(new Vector3($this->all['dispawn']['x'], $this->all['dispawn']['y'], $this->all['dispawn']['z']));
                                $key = array_search($name, $this->game['live']);
                                unset($this->game['live'][$key]);
                                if($this->getLives() == 1){
                                    $arg = array_merge($this->game['live']);
                                    $this->game[$arg[0]]['result']['生き残り'] = 1;
                                    $this->end();
                                }             
                            }
                        }else{
                            $player->teleport(new Vector3($this->all['dispawn']['x'], $this->all['dispawn']['y'], $this->all['dispawn']['z']));
                        }
                    }else{
                        $player->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn());
                    } 
                }else{
                    if($this->isPlayer($name)){
                        $player->teleport(new Position($this->all['wait']['x'], $this->all['wait']['y'], $this->all['wait']['z']), $this->getServer()->getLevelByName($this->all['wait']['world']));
                    }else{
                        $player->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn());
                    }
                }
            }
        }
    }

     
    public function timer()
    {
        $this->game['timer']++;
    }

    public function getTime()
    {
        return $this->game['timer'];
    }

    public function isLiver($name){
        if(in_array($name, $this->game['live'])){
            return true;
        }else{
            return false;
        }
    }
               

    public function end()
    {
        $pos = $this->getServer()->getDefaultLevel()->getSafeSpawn();
               
        foreach($this->game['players'] as $name){
            $player = $this->getServer()->getPlayer($name);
            if($player instanceof Player){
                $player->setSpawn($pos);
                $player->teleport($pos);
                $player->setGamemode(0);
                $player->getInventory()->clearAll();
            }
        }
        $this->t->stop();
        @$this->getServer()->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "result"]), 20*4);
    }


    public function result()
    {
        foreach($this->game['players'] as $name){
            $player = $this->getServer()->getPlayer($name);
            if($player instanceof Player){
                $kill = $this->game[$name]['result']['kill'];
                $kexp = $kill * 2;
                $snow = $this->game[$name]['result']['雪玉あて'];
                $snowexp = $snow;
                $re = $snowexp + $kexp;
                $player->sendMessage("§6 >------  RESULT  -------<");
                $player->sendMessage("§2 Kill : ".$kill."回  ".$kexp."exp");
                $player->sendMessage("§2 雪玉 : ".$snow."回  ".$snowexp."exp");
                if($this->game[$name]['result']['生き残り'] == 1){
                    $player->sendMessage("§2 生き残り :      5exp");
                    $re = $re + 5;
                }
                $player->sendMessage("§2----------------------");
                $player->sendMessage("§a合計          §2".$re."exp");
                $time = $this->game['timer'];
                $h = floor($time / 3600);
                $m = floor(($time / 60) % 60);
                $s = $time % 60;
                $player->sendMessage("§b> §6今回の試合時間は §a".$h."時間".$m."分".$s."秒　§6でした。");
                $this->addExp($name, $re);
            }
             
        }
        $this->loadGame();
        $this->getServer()->unloadLevel($this->getServer()->getLevelByName($this->con->get('world')));
        $this->getServer()->loadLevel($this->con->get('world'));

    }

    public function sendTips($message)
    {
        foreach($this->game['players'] as $name){
            $player = $this->getServer()->getPlayer($name);
            if($player instanceof Player){
                $player->sendTip($message);
            }
        }
    }

    public function sendPopups($message)
    {
        foreach($this->game['players'] as $name){
           $player = $this->getServer()->getPlayer($name);
           if($player instanceof Player){
                $player->sendPopup($message);
           }
        }
    }


    public function addExp($name, $value)
    {
        if($this->exp->exists($name)){
            $now = $this->exp->get($name);
            $after = $now + $value;
            $apexp = $this->nextexp->get($name);
            $level = $this->level->get($name);
            $player = $this->getServer()->getPlayer($name);
            if($after >= $apexp){
                $this->nextexp->set($name, $apexp + 10);
                $l = $level + 1;
                $this->level->set($name, $l);
                $this->exp->set($name, $after - $apexp);
                $player->sendMessage("§b§l> §aレベルが上がりました。§6".$level." §e-> §6".$l);
                $player->setNameTag("§b[Lv.".$this->getLevel($name)."] ".$name);
                $player->setDisplayName("§b[Lv.".$this->getLevel($name)."] ".$name." §f>");
            }else{
                $this->exp->set($name, $after);
            }
            $this->saveData();
        }else{
            $this->getServer()->getLogger()->info("§bそのようなPlayerは登録されていません");
        }
    }

    public function getLevel($name)
    {
        if($this->level->exists($name)){
            return $this->level->get($name);
        }else{
            $this->getServer()->getLogger()->info("§ePlayerがみつかりません");
        }
    }


    public function saveData()
    {
        $this->con->save();
        $this->exp->save();
        $this->level->save();
        $this->nextexp->save();
        if($this->con->get("SaveConfigMessage") == "true"){
            $this->getServer()->getLogger()->info("§bCONFIGをセーブしました");
        }
        $this->all = $this->con->getAll();
    }
    

    public function onQuit(\pocketmine\event\player\PlayerQuitEvent $ev)
    {
        $player = $ev->getPlayer();
        $name = $player->getName();
        if($this->isPlayer($name)){
            unset($this->game[$name]);
            $key = array_search($name, $this->game['players']);
            unset($this->game['players'][$key]);
            $key = array_search($name, $this->game['live']);
            unset($this->game['live'][$key]);
            if($this->getPlayerCount() < 1){
                $this->getServer()->broadcastMessage("§b>プレイヤーがいなくなったのでゲームを終了します");
                $this->loadGame();
                $this->task->count = -1;
            }else if($this->getLives() == 1){
                $this->getServer()->broadcastMessage("§e>§c プレイヤーがいなくなったため終了します。");
                $this->end();
            }       
         }
         $player->setSpawn($this->getServer()->getDefaultLevel()->getSafeSpawn());
    }
                     

    public function onCommand(CommandSender $sender, Command $cmd, $label,array $args)
    {
        $cmdname = $cmd->getName();
        $name = $sender->getName();
        switch($cmdname){
             
            case 'opt':
                if($sender->isOp()){
                    if(isset($args[0])){
                        $this->opt[$name] = $args[0];
                        $sender->sendMessage("§bチェストにタッチしてください");
                    }else{
                        $sender->sendMessage("§b使い方 : /opt [\"名前\"]");
                    }
                }
            break;

            case 'set':
                if($sender->isOp()){
                    if(isset($args[0])){
                        if($args[0] == 'spawns'){
                            if(isset($args[1])){
                                if(!array_key_exists($args[1], $this->all['spawns'])){
                                    $x = floor($sender->x);
                                    $y = floor($sender->y);
                                    $z = floor($sender->z);
                                    $add = [$args[1]=>['x'=>$x, 'y'=>$y, 'z'=>$z]];
                                    $add = array_merge($this->all['spawns'], $add);
                                    $this->con->set("spawns", $add);
                                    $this->saveData();
                                    $sender->sendMessage("§b x : ".$x." y : ".$y." z : ".$z." に ".$args[1]."を設定しました");
                                }else{
                                    $sender->sendMessage("§eその名前は既に登録されています");
                                }
                            }else{
                                 $sender->sendMessage("§e使い方 : /set spawns [\"名前\"]    : 島のスポーン地点を設定します。");
                            }
                        }else if($args[0] == 'wait'){
                            $x = floor($sender->x);
                            $y = floor($sender->y);
                            $z = floor($sender->z);
                            $levelname = $sender->getLevel()->getName();
                            $data = ['x'=>$x, 'y'=>$y, 'z'=>$z, 'world'=>$levelname];
                            $this->con->set('wait', $data);
                            $sender->sendMessage("§a§l> §bx : ".$x." y: ".$y." z : ".$z." に待機場所を設定しました");
                            $this->saveData();     
                        }else{
                            $sender->sendMessage("§6----CommandList----");
                            $sender->sendMessage("§a/set spawns [\"名前\"]  : 島のスポーン地点を設定します。");
                            $sender->sendMessage("§a/set wait               : ゲームが始まるまでの待機場所の設定をします。");
                        }  
                    }else{
                        $sender->sendMessage("§6----CommandList----");
                        $sender->sendMessage("§a/set spawns [\"名前\"]  : 島のスポーン地点を設定します。");
                        $sender->sendMessage("§a/set wait               : ゲームが始まるまでの待機場所の設定をします。");
                    }        
                }
            break;

            case 'dispawn':
                if($sender->isOp()){
                    $x = $sender->x;
                    $y = $sender->y;
                    $z = $sender->z;
                    $this->con->set('dispawn', ['x'=>$x, 'y'=>$y, 'z'=>$z]);
                    $sender->sendMessage("§aDispawnを設定しました。");
                    $this->saveData();
                }
            break;
        }
    }
                                          
}
