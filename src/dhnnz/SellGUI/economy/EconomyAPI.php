<?php

namespace dhnnz\SellGUI\economy;

use onebone\economyapi\EconomyAPI as EconomyapiEconomyAPI;
use pocketmine\player\Player;

class EconomyAPI
{

    public function addMoney(Player $player, $amount, callable $callable)
    {
        $result = EconomyapiEconomyAPI::getInstance()->addMoney($player, $amount);
        $callable(($result > 0) ? true : false);
    }

    public function reduceMoney(Player $player, $amount, callable $callable)
    {
        $result = EconomyapiEconomyAPI::getInstance()->reduceMoney($player, $amount);
        $callable(($result > 0) ? true : false);
    }
}