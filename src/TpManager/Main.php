<?php

namespace TpManager;

use pocketmine\plugin\PluginBase;
use pocketmine\player\Player;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\scheduler\ClosureTask;

class Main extends PluginBase{

    private array $tpaRequests = [];
    private array $tpaHereRequests = [];

    public function onEnable() : void{
        $this->saveDefaultConfig();
    }

    private function msg(string $key, array $replace = []) : string{
        $msg = $this->getConfig()->get("messages")[$key] ?? $key;

        foreach($replace as $k => $v){
            $msg = str_replace("{".$k."}", $v, $msg);
        }

        return $msg;
    }

    private function findPlayer(string $name) : ?Player{
        $name = strtolower($name);

        foreach($this->getServer()->getOnlinePlayers() as $player){
            if(str_starts_with(strtolower($player->getName()), $name)){
                return $player;
            }
        }

        return null;
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{

        if(!$sender instanceof Player){
            return true;
        }

        switch($command->getName()){

            case "tpa":

                if(!isset($args[0])){
                    $sender->sendMessage("/tpa <player>");
                    return true;
                }

                $target = $this->findPlayer($args[0]);

                if(!$target){
                    $sender->sendMessage($this->msg("player-not-found"));
                    return true;
                }

                $this->tpaRequests[$target->getName()] = $sender->getName();

                $sender->sendMessage($this->msg("tpa-sent", ["player"=>$target->getName()]));
                $target->sendMessage($this->msg("tpa-received", ["player"=>$sender->getName()]));

                $this->expireRequest($target->getName(), false);

            return true;


            case "tpahere":

                if(!isset($args[0])){
                    $sender->sendMessage("/tpahere <player>");
                    return true;
                }

                $target = $this->findPlayer($args[0]);

                if(!$target){
                    $sender->sendMessage($this->msg("player-not-found"));
                    return true;
                }

                $this->tpaHereRequests[$target->getName()] = $sender->getName();

                $sender->sendMessage($this->msg("tpahere-sent", ["player"=>$target->getName()]));
                $target->sendMessage($this->msg("tpahere-received", ["player"=>$sender->getName()]));

                $this->expireRequest($target->getName(), true);

            return true;


            case "tpaccept":

                if(!isset($args[0])){
                    $sender->sendMessage("/tpaccept <player>");
                    return true;
                }

                $playerName = $args[0];

                if(isset($this->tpaRequests[$sender->getName()]) &&
                    strtolower($this->tpaRequests[$sender->getName()]) === strtolower($playerName)){

                    $player = $this->findPlayer($playerName);

                    if($player){
                        $player->teleport($sender->getPosition());
                    }

                    unset($this->tpaRequests[$sender->getName()]);
                    $sender->sendMessage($this->msg("tpaccepted"));

                    return true;
                }

                if(isset($this->tpaHereRequests[$sender->getName()]) &&
                    strtolower($this->tpaHereRequests[$sender->getName()]) === strtolower($playerName)){

                    $player = $this->findPlayer($playerName);

                    if($player){
                        $sender->teleport($player->getPosition());
                    }

                    unset($this->tpaHereRequests[$sender->getName()]);
                    $sender->sendMessage($this->msg("tpaccepted"));

                    return true;
                }

                $sender->sendMessage($this->msg("no-request"));
            return true;


            case "tpdeny":

                if(!isset($args[0])){
                    $sender->sendMessage("/tpdeny <player>");
                    return true;
                }

                if(isset($this->tpaRequests[$sender->getName()])){
                    unset($this->tpaRequests[$sender->getName()]);
                    $sender->sendMessage($this->msg("tpdenied"));
                    return true;
                }

                if(isset($this->tpaHereRequests[$sender->getName()])){
                    unset($this->tpaHereRequests[$sender->getName()]);
                    $sender->sendMessage($this->msg("tpdenied"));
                    return true;
                }

                $sender->sendMessage($this->msg("no-request"));

            return true;


            case "tpall":

                if(!$sender->hasPermission("tpmanager.tpall")){
                    return true;
                }

                foreach($this->getServer()->getOnlinePlayers() as $player){

                    if($player !== $sender){
                        $player->teleport($sender->getPosition());
                    }

                }

                $sender->sendMessage($this->msg("tpall"));

            return true;
        }

        return false;
    }

    private function expireRequest(string $player, bool $here) : void{

        $time = $this->getConfig()->get("expire-time");

        $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($player, $here){

            if($here){

                if(isset($this->tpaHereRequests[$player])){
                    unset($this->tpaHereRequests[$player]);
                }

            }else{

                if(isset($this->tpaRequests[$player])){
                    unset($this->tpaRequests[$player]);
                }

            }

        }), 20 * $time);
    }
}
