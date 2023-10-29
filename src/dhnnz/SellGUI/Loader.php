<?php

namespace dhnnz\SellGUI;

use cooldogedev\BedrockEconomy\api\BedrockEconomyAPI;
use cooldogedev\BedrockEconomy\api\legacy\ClosureContext;

use muqsit\invmenu\InvMenu;
use muqsit\invmenu\InvMenuHandler;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;

use onebone\economyapi\EconomyAPI;

use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\Listener;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;

class Loader extends PluginBase implements Listener
{

    public function onEnable(): void
    {
        if (!class_exists(InvMenuHandler::class)) {
            $this->getLogger()->error("InvMenu virion not found.");
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }

        $this->saveResource("config.yml");

        if (!InvMenuHandler::isRegistered()) {
            InvMenuHandler::register($this);
        }
    }

    public function getEconomy()
    {
        return match (true) {
            (class_exists(EconomyAPI::class)) => new \dhnnz\SellGUI\economy\EconomyAPI(),
            (class_exists(BedrockEconomyAPI::class)) => new \dhnnz\SellGUI\economy\BedrockEconomyAPI(),
            default => null
        };
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        if (!($sender instanceof Player))
            return false;
        if ($command->getName() !== "sell")
            return false;
        $this->sellGui($sender);
        return true;
    }

    public function getMessage(string $message, array $args = []): string
    {
        $replace = $this->getConfig()->get($message, $message);

        for ($i = 0; $i < count($args); $i++) {
            $replace = str_replace("%$i%", $args[$i], $replace);
        }

        $replace = TextFormat::colorize($replace);
        return $replace;
    }

    public function sellGui(Player $player)
    {
        $menu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST);
        $inventory = $menu->getInventory();

        $self = $this;

        $menu->setInventoryCloseListener(function (Player $player, Inventory $inventory) use ($self) {
            $config = $self->getConfig();
            $listSell = [];
            foreach ($inventory->getContents() as $item) {
                if (!in_array(str_replace(" ", "_", $item->getName()), $config->get("blacklist"))) {
                    $price = (int) ((isset($config->get("items")[str_replace(" ", "_", $item->getName())]["price"])) ? $config->get("items")[str_replace(" ", "_", $item->getName())]["price"] : $config->get("items")["default"]["price"]) * $item->getCount();
                    $listSell[str_replace(" ", "_", $item->getName())] = [
                        "count" => ($listSell[str_replace(" ", "_", $item->getName())]["count"] ?? 0) + $item->getCount(),
                        "price" => ($listSell[str_replace(" ", "_", $item->getName())]["price"] ?? 0) + $price
                    ];
                } else {
                    $player->sendMessage($self->getMessage("message.cannot.sell", [str_replace(" ", "_", $item->getName())]));
                    $player->getInventory()->addItem($item);
                }
            }

            $prices = array_column($listSell, 'price');
            array_map(function ($price) use ($player, $self) {
                $economy = $self->getEconomy();
                if ($economy !== null) {
                    $economy->addMoney($player, $price, function (bool $updated) {
                        return $updated;
                    });
                }
            }, $prices);

            $player->sendMessage(TextFormat::colorize($self->getMessage("message.list.sell.top") . "\n" . implode("\n", array_map(function ($item, $details) {
                $count = $details['count'];
                $price = number_format($details['price']);
                return $self->getMessage("message.list.sell", [$count, $item, $price]);
            }, array_keys($listSell), $listSell))));
        });

        $menu->send($player);
    }
}
