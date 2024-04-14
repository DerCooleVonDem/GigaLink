<?php

namespace DerCooleVonDem\GigaLink\commands;

use DerCooleVonDem\GigaLink\Main;
use JaxkDev\DiscordBot\Models\Guild\Guild;
use JaxkDev\DiscordBot\Plugin\ApiResolution;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Server;

class DiscordLinkCommand extends Command {

    public function __construct()
    {
        parent::__construct("link", "Link your Discord account with your Minecraft account", "/link", ["discord"]);
        $this->setPermission("gigalink.link");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {
        $main = Main::getInstance();
        $player = Server::getInstance()->getPlayerExact($sender->getName());

        if($main->verificationDB->hasLinkEntryXuid($player->getXuid())) {
            $sender->sendMessage("§cYou have already linked your account.");
            return;
        }

        $discordBotName = $main->api->getBotUser()->getUsername();
        $guild = $main->api->fetchGuild($main->settingsConfig->config->get("discord-server-id"));
        $guild->then(function (ApiResolution $result) use ($sender, $main, $player, $discordBotName) {
            $data = $result->getData()[0];
            if($data instanceof Guild) {
                $guildName = $data->getName();

                $linkCode = $main->verificationDB->generateLinkCode($player->getXuid());
                $sender->sendMessage("§aPlease now proceed to your Discord account and dm the bot §e{$discordBotName}§a from the discord server §e{$guildName}§a with the following code");
                $sender->sendMessage("§e--------------------");
                $sender->sendMessage("§aLink Code: §l§b$linkCode");
                $sender->sendMessage("§e--------------------");
            } else {
                $sender->sendMessage("§cAn error occurred while fetching the guild. Please try again later.");
            }
        });
    }
}