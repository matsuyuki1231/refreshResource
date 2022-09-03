<?php

namespace matsuyuki\refreshResource;

use pocketmine\block\VanillaBlocks;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\item\ItemBlock;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\event\player\PlayerInteractEvent;

class refreshResource extends PluginBase implements Listener {

    private Config $config;
    private array $status;
    private array $signs;
    public const mustBreak = 50; /*必ず破壊しなければならないブロック数*/

    public function onEnable():void {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->config = new Config($this->getDataFolder() ."config.json", Config::JSON, []);
        /*
        * x:y:z:w
        *   start: [x, y, z]
        *   end: [x, y, z]
        *   block:
        *     id: int
        *     meta: int
        *   id: int/str
        */
        $this->signs = array_keys($this->config->getAll());
        $this->status = ["confirm" => [], "complete" => [], "add" => [], "temp" => []];
   }

    public function onTap(PlayerInteractEvent $event):void {
        $block = $event->getBlock();
        $pos = $block->getPosition();
        $strPos = $pos->x. ":". $pos->y. ":". $pos->z. ":". $pos->getWorld()->getFolderName();
        if (in_array($strPos, $this->signs)) {
            if ($block->getId() === 63 || $block->getId() === 68) {
                $handlerName = "看板";
            } else {
                $handlerName = "ブロック";
            }
            if (array_key_exists($event->getPlayer()->getName(), $this->status["complete"]) and
                time() - $this->status["complete"][$event->getPlayer()->getName()] < 6) {
                $event->getPlayer()->sendMessage("§l§c[refreshResource]§r §7少し待ってから実行してください");
                return;
            }
            if (!array_key_exists($event->getPlayer()->getName(), $this->status["confirm"]) or
                time() - $this->status["confirm"][$event->getPlayer()->getName()] > 3) {
                $event->getPlayer()->sendMessage("§l§b[refreshResource]§r §7もう一度". $handlerName. "をタップして実行します");
                $this->status["confirm"][$event->getPlayer()->getName()] = time();
                return;
            }
            $info = $this->config->get($strPos);
            $smaller = [
                "x" => min([$info["start"][0], $info["end"][0]]),
                "y" => min([$info["start"][1], $info["end"][1]]),
                "z" => min([$info["start"][2], $info["end"][2]])
            ];
            $bigger = [
                "x" => max([$info["start"][0], $info["end"][0]]),
                "y" => max([$info["start"][1], $info["end"][1]]),
                "z" => max([$info["start"][2], $info["end"][2]])
            ];
            $block = ItemBlock::jsonDeserialize($info["block"])->getBlock();
            $canRefresh = true;
            $countAir = 0;
            $countBlock = 0;
            foreach (Server::getInstance()->getOnlinePlayers() as $player) {
                $pPos = $player->getPosition();
                if ($pPos->getWorld()->getFolderName() !== $pos->getWorld()->getFolderName()) {
                    continue;
                }
                if ($smaller["x"] < $pPos->x and $smaller["y"] - 1 < $pPos->y and $smaller["z"] < $pPos->z and
                    $bigger["x"] > $pPos->x and $bigger["y"] > $pPos->y and $bigger["z"] > $pPos->z) {
                    $event->getPlayer()->sendMessage("§l§c[refreshResource]§r §7範囲内に人がいます");
                    return;
                }
            }
            for ($x=$smaller["x"]; $x<=$bigger["x"]; $x++) {
                for ($y=$smaller["y"]; $y<=$bigger["y"]; $y++) {
                    for ($z=$smaller["z"]; $z<=$bigger["z"]; $z++) {
                        $countBlock++;
                        $blockAt = $pos->getWorld()->getBlockAt($x, $y, $z, false, false);
                        if (($block->getId() !== $blockAt->getId() or $block->getMeta() !== $blockAt->getMeta()) and
                            $blockAt->getId() !== VanillaBlocks::AIR()->getId()) {
                            $event->getPlayer()->sendMessage("§l§c[refreshResource]§r §7x". $x. " y". $y. " z". $z. " に後から設置されたブロック(".
                                $blockAt->getName(). "-". $blockAt->getId(). ":". $blockAt->getMeta().
                                ")があります。\nこれを撤去してから再実行してください");
                            $canRefresh = false;
                        }
                        if ($blockAt->getId() === VanillaBlocks::AIR()->getId()) {
                            $countAir++;
                        }
                    }
                }
            }
            if ($countBlock > self::mustBreak) {
                $mustBreak = self::mustBreak;
            } else {
                $mustBreak = floor($countBlock / 3 * 2);
            }
            if ($countAir < $mustBreak) {
                $event->getPlayer()->sendMessage("§l§c[refreshResource]§r §7まだブロックは多くあります。もう少し掘ってから再実行してください。");
                return;
            }
            if ($canRefresh) {
                for ($x=$smaller["x"]; $x<=$bigger["x"]; $x++) {
                    for ($y=$smaller["y"]; $y<=$bigger["y"]; $y++) {
                        for ($z=$smaller["z"]; $z<=$bigger["z"]; $z++) {
                            $pos->getWorld()->setBlockAt($x, $y, $z, $block, false);
                        }
                    }
                }
            } else {
                return;
            }
            $event->getPlayer()->sendMessage("§l§a[refreshResource]§r §7ブロックを補充しました！");
            $this->status["complete"][$event->getPlayer()->getName()] = time();
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args):bool {
        if ($command->getName() === "ref") {
            if (!$sender->hasPermission("forceRefresh.refreshResource")) {
                return true;
            }
            if (count($args) !== 1) {
                $str = "§l§c[refreshResource]§r §7指定可能なID";
                foreach ($this->config->getAll() as $eachData) {
                    ItemBlock::jsonDeserialize($eachData["block"]);
                    $str .= "\n[§f". $eachData["id"]. "§7] §f". ItemBlock::jsonDeserialize($eachData["block"])->getName().
                        " §7(". $eachData["start"][0]. ", ". $eachData["start"][1]. ", ". $eachData["start"][2]. ")";
                }
                $sender->sendMessage($str);
                return false;
            }
            if ($args[0] === "reload") {
                $this->config->reload();
                $this->signs = array_keys($this->config->getAll());
                $sender->sendMessage("§l§a[refreshResource]§r §7設定ファイルを再読み込みしました");
                return true;
            }
            $data = null;
            foreach ($this->config->getAll() as $key => $eachData) {
                if ((string) $eachData["id"] === $args[0]) {
                    $data = $eachData;
                    $world = Server::getInstance()->getWorldManager()->getWorldByName(explode(":", $key)[3]);
                    if ($world === null) {
                        $sender->sendMessage("§l§c[refreshResource:Critical]§r §c指定したIDから得られるデータのワールド名にあたるワールドがありません");
                    }
                }
            }
            if ($data === null) {
                $sender->sendMessage("§l§c[refreshResource]§r §7指定したIDはありません");
                $str = "§l§a[refreshResource]§r §7指定可能なID";
                foreach ($this->config->getAll() as $eachData) {
                    ItemBlock::jsonDeserialize($eachData["block"]);
                    $str .= "\n[§f". $eachData["id"]. "§7] §f". ItemBlock::jsonDeserialize($eachData["block"])->getName().
                        " §7(". $eachData["start"][0]. ", ". $eachData["start"][1]. ", ". $eachData["start"][2]. ")";
                }
                $sender->sendMessage($str);
                return true;
            }
            $smaller = [
                "x" => min([$data["start"][0], $data["end"][0]]),
                "y" => min([$data["start"][1], $data["end"][1]]),
                "z" => min([$data["start"][2], $data["end"][2]])
            ];
            $bigger = [
                "x" => max([$data["start"][0], $data["end"][0]]),
                "y" => max([$data["start"][1], $data["end"][1]]),
                "z" => max([$data["start"][2], $data["end"][2]])
            ];
            $block = ItemBlock::jsonDeserialize($data["block"])->getBlock();
            $canRefresh = true;
            foreach (Server::getInstance()->getOnlinePlayers() as $player) {
                $pPos = $player->getPosition();
                if ($pPos->getWorld()->getFolderName() !== $world->getFolderName()) {
                    continue;
                }
                if ($smaller["x"] < $pPos->x and $smaller["y"] - 1 < $pPos->y and $smaller["z"] < $pPos->z and
                    $bigger["x"] > $pPos->x and $bigger["y"] > $pPos->y and $bigger["z"] > $pPos->z) {
                    $sender->sendMessage("§l§c[refreshResource]§r §7範囲内に人がいます");
                    return true;
                }
            }
            for ($x=$smaller["x"]; $x<=$bigger["x"]; $x++) {
                for ($y=$smaller["y"]; $y<=$bigger["y"]; $y++) {
                    for ($z=$smaller["z"]; $z<=$bigger["z"]; $z++) {
                        $blockAt = $world->getBlockAt($x, $y, $z, false, false);
                        if (($block->getId() !== $blockAt->getId() or $block->getMeta() !== $blockAt->getMeta()) and
                            $blockAt->getId() !== VanillaBlocks::AIR()->getId()) {
                            $sender->sendMessage("§l§c[refreshResource]§r §7x". $x. " y". $y. " z". $z. " に後から設置されたブロック(".
                                $blockAt->getName(). "-". $blockAt->getId(). ":". $blockAt->getMeta().
                                ")があります。\nこれを撤去してから再実行してください");
                            $canRefresh = false;
                        }
                    }
                }
            }
            if ($canRefresh) {
                for ($x=$smaller["x"]; $x<=$bigger["x"]; $x++) {
                    for ($y=$smaller["y"]; $y<=$bigger["y"]; $y++) {
                        for ($z=$smaller["z"]; $z<=$bigger["z"]; $z++) {
                            $world->setBlockAt($x, $y, $z, $block, false);
                        }
                    }
                }
            } else {
                return true;
            }
            $sender->sendMessage("§l§a[refreshResource]§r §7ブロックを補充しました！");
            return true;
        } elseif ($command->getName() === "addref") {
            if (!$sender instanceof Player) {
                $sender->sendMessage("§l§c[refreshResource]§r §7コンソールからは実行できません");
            }
            if (!$sender->hasPermission("forceRefresh.refreshResource")) {
                return true;
            }
            $sender->sendMessage("§l§a[refreshResource]§r §7補充する起点の位置にあるブロックを壊してください");
            $this->status["add"][$sender->getName()] = 1;
            return true;
        } elseif ($command->getName() === "delref") {
            if (!$sender->hasPermission("forceRefresh.refreshResource")) {
                return true;
            }
            if (count($args) !== 1) {
                $str = "§l§c[refreshResource]§r §7指定可能なID";
                foreach ($this->config->getAll() as $eachData) {
                    ItemBlock::jsonDeserialize($eachData["block"]);
                    $str .= "\n[§f". $eachData["id"]. "§7] §f". ItemBlock::jsonDeserialize($eachData["block"])->getName().
                        " §7(". $eachData["start"][0]. ", ". $eachData["start"][1]. ", ". $eachData["start"][2]. ")";
                }
                $sender->sendMessage($str);
                return false;
            }
            $this->config->reload();
            $sign = null;
            foreach ($this->config->getAll() as $key => $eachData) {
                if ((string) $eachData["id"] === $args[0]) {
                    $sign = $key;
                    $id = $eachData["id"];
                }
            }
            if ($sign === null) {
                $sender->sendMessage("§l§c[refreshResource]§r §7指定したIDはありません");
                $str = "§l§a[refreshResource]§r §7指定可能なID";
                foreach ($this->config->getAll() as $eachData) {
                    ItemBlock::jsonDeserialize($eachData["block"]);
                    $str .= "\n[§f". $eachData["id"]. "§7] §f". ItemBlock::jsonDeserialize($eachData["block"])->getName().
                        " §7(". $eachData["start"][0]. ", ". $eachData["start"][1]. ", ". $eachData["start"][2]. ")";
                }
                $sender->sendMessage($str);
                return true;
            }
            $this->config->remove($sign);
            $this->config->save();
            $this->config->reload();
            $this->signs = array_keys($this->config->getAll());
            $sender->sendMessage("§l§a[refreshResource]§r §7ID". $id. " 削除しました！");
            return true;
        }
        return false;
    }

    public function onBreak(BlockBreakEvent $event):void {
        $block = $event->getBlock();
        $pos = $block->getPosition();
        $strPos = $pos->x. ":". $pos->y. ":". $pos->z. ":". $pos->getWorld()->getFolderName();
        if (in_array($strPos, $this->signs)) {
            if ($event->getPlayer()->hasPermission("forceRefresh.refreshResource")) {
                $id = $this->config->get($strPos)["id"];
                $this->config->remove($strPos);
                $this->config->save();
                $this->config->reload();
                $this->signs = array_keys($this->config->getAll());
                $event->getPlayer()->sendMessage("§l§a[refreshResource]§r §7ID". $id. " の補充設定を削除しました！");
                return;
            } else {
                $event->getPlayer()->sendMessage("§l§c[refreshResource]§r §7この看板は壊せません");
                $event->cancel();
                return;
            }
        }
        if (array_key_exists($event->getPlayer()->getName(), $this->status["add"]) and
            ($this->status["add"][$event->getPlayer()->getName()] === 1 or $this->status["add"][$event->getPlayer()->getName()] === 2
            or $this->status["add"][$event->getPlayer()->getName()] === 3)) {
            $event->cancel();
            $pos = $event->getBlock()->getPosition();
            switch ($this->status["add"][$event->getPlayer()->getName()]) {
                case 1:
                    $this->status["temp"][$event->getPlayer()->getName()]["start"] = [$pos->x, $pos->y, $pos->z, $pos->getWorld()->getFolderName()];
                    $this->status["add"][$event->getPlayer()->getName()] = 2;
                    $event->getPlayer()->sendMessage("§l§a[refreshResource]§r §7補充する終点の位置にあるブロックを壊してください\nただし、壊すブロックは、補充するブロックにしてください\n/addrefで、最初からやり直すこともできます");
                    break;
                case 2:
                    if ($pos->getWorld()->getFolderName() !== $this->status["temp"][$event->getPlayer()->getName()]["start"][3]) {
                        $event->getPlayer()->sendMessage("§l§c[refreshResource]§r §7始点と終点は同じワールド「". $this->status["temp"][$event->getPlayer()->getName()]["start"][3].
                            "」にしてください\n始点を間違えた場合は、/addrefで最初からやり直してください");
                    }
                    $this->status["temp"][$event->getPlayer()->getName()]["end"] = [$pos->x, $pos->y, $pos->z];
                    $event->getPlayer()->sendMessage("§l§a[refreshResource]§r §7補充看板もしくは補充ブロックを壊してください");
                    $this->status["add"][$event->getPlayer()->getName()] = 3;
                    $this->status["temp"][$event->getPlayer()->getName()]["block"] = $event->getBlock()->asItem()->jsonSerialize();
                    break;
                case 3:
                    if ($block->getId() === 63 || $block->getId() === 68) {
                        $handlerName = "看板";
                    } else {
                        $handlerName = "ブロック";
                    }
                    if ($pos->getWorld()->getFolderName() !== $this->status["temp"][$event->getPlayer()->getName()]["start"][3]) {
                        $event->getPlayer()->sendMessage("§l§c[refreshResource]§r §7補充". $handlerName. "は始点・終点と同じワールド「".
                            $this->status["temp"][$event->getPlayer()->getName()]["start"][3]. "」にしてください\n始点を間違えた場合は、/addrefで最初からやり直してください");
                    }
                    $this->config->reload();
                    $ids = [];
                    foreach ($this->config->getAll() as $eachData) {
                        $ids[] = $eachData["id"];
                    }
                    $index = 1;
                    while (true) {
                        if (!in_array($index, $ids)) {
                            $id = $index;
                            break;
                        }
                        $index++;
                    }
                    $this->config->set($pos->x. ":". $pos->y. ":". $pos->z. ":". $pos->getWorld()->getFolderName(), [
                        "start" => [$this->status["temp"][$event->getPlayer()->getName()]["start"][0],
                            $this->status["temp"][$event->getPlayer()->getName()]["start"][1],
                            $this->status["temp"][$event->getPlayer()->getName()]["start"][2]],
                        "end" => $this->status["temp"][$event->getPlayer()->getName()]["end"],
                        "block" => $this->status["temp"][$event->getPlayer()->getName()]["block"],
                        "id" => $id,
                        "author" => $event->getPlayer()->getName()
                    ]);
                    $this->config->save();
                    $this->config->reload();
                    $this->signs = array_keys($this->config->getAll());
                    $this->status["add"][$event->getPlayer()->getName()] = 0;
                    $event->getPlayer()->sendMessage("§l§a[refreshResource]§r §7セットしました！(補充ID: ". $id.
                        ")\n§7補充". $handlerName. "を2回タップすると資源が補充されます");
                    break;
            }
        }

    }

}
