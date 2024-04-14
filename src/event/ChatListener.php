<?php

namespace DerCooleVonDem\GigaLink\event;

use Carbon\Factory;
use DerCooleVonDem\GigaLink\config\SettingsConfig;
use DerCooleVonDem\GigaLink\Main;
use Discord\Discord;
use Discord\Parts\Channel\Forum\Tag;
use JaxkDev\DiscordBot\Libs\React\Promise\PromiseInterface;
use JaxkDev\DiscordBot\Models\Channels\Channel;
use JaxkDev\DiscordBot\Models\Channels\ChannelType;
use JaxkDev\DiscordBot\Models\User;
use JaxkDev\DiscordBot\Plugin\Api;
use JaxkDev\DiscordBot\Plugin\ApiResolution;
use JaxkDev\DiscordBot\Plugin\Events\DiscordClosed;
use JaxkDev\DiscordBot\Plugin\Events\DiscordReady;
use JaxkDev\DiscordBot\Plugin\Events\MessageSent;
use LogLevel;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\plugin\PluginDisableEvent;
use pocketmine\Server;

class ChatListener implements Listener
{
    public Api $api;
    public SettingsConfig $settingsConfig;

    public bool $minecraftDiscordChatBridgeEnabled = false;
    public string $minecraftDiscordChatChannel = "";
    public bool $minecraftDiscordChatSendServerStatus = false;

    public function __construct() {
        $this->api = Main::getInstance()->api;
        $this->settingsConfig = Main::getInstance()->settingsConfig;
        $this->minecraftDiscordChatBridgeEnabled = $this->settingsConfig->config->get("discord-minecraft-chat");
        $this->minecraftDiscordChatChannel = $this->settingsConfig->config->get("discord-minecraft-chat-channel");
        $this->minecraftDiscordChatSendServerStatus = $this->settingsConfig->config->get("discord-minecraft-chat-send-server-status");
    }

    public function onChat(PlayerChatEvent $event): void
    {
        if($this->minecraftDiscordChatBridgeEnabled) {
            $guildId = $this->settingsConfig->config->get("discord-server-id");
            $channel = $this->settingsConfig->config->get("discord-minecraft-chat-channel");

            $playerMessage = $event->getMessage();
            $player = $event->getPlayer();

            $messageTemplate = $this->settingsConfig->config->get("discord-minecraft-chat-discord-message");
            $message = str_replace("&username&", $player->getName(), $messageTemplate);
            $message = str_replace("&message&", $playerMessage, $message);

            $this->api->sendMessage($guildId, $channel, $message);
        }
    }

    public function onDiscordChat(MessageSent $event) {
        $messageChannelId = $event->getMessage()->getChannelId();
        $channelId = $this->settingsConfig->config->get("discord-minecraft-chat-channel");

        if($this->minecraftDiscordChatBridgeEnabled && $messageChannelId == $channelId){
            $authorId = $event->getMessage()->getAuthorId();
            $this->api->fetchUser($authorId)->then(function (ApiResolution $result) use ($event) {
                $data = $result->getData()[0];

                if($data instanceof User){
                    $username = $data->getGlobalName();
                    $userMessage = $event->getMessage()->getContent();

                    $messageTemplate = $this->settingsConfig->config->get("discord-minecraft-chat-minecraft-message");
                    $message = str_replace("&username&", $username, $messageTemplate);
                    $message = str_replace("&message&", $userMessage, $message);

                    $onlinePlayers = Server::getInstance()->getOnlinePlayers();
                    foreach ($onlinePlayers as $player) {
                        $player->sendMessage($message);
                    }

                    Main::getInstance()->getLogger()->log(LogLevel::INFO, $message);
                }
            });
        }

        $this->api->fetchChannel(null, $event->getMessage()->getAuthorId())->then(function (ApiResolution $result) use ($event, $messageChannelId) {

            $data = $result->getData()[0];
            if($data instanceof Channel) {
                if($messageChannelId !== $data->getId()) return;

                $message = $event->getMessage()->getContent();
                $authorId = $event->getMessage()->getAuthorId();

                // check if its an all caps 5 letter code
                if(preg_match("/^[A-Z]{5}$/", $message)) {
                    if(Main::getInstance()->verificationDB->hasLinkEntry($authorId)) {
                        $this->api->sendMessage(null, $messageChannelId, "You are already linked with a Minecraft account!");
                        return;
                    }

                    if(!Main::getInstance()->verificationDB->doesLinkCodeExist($message)) {
                        $this->api->sendMessage(null, $messageChannelId, "Invalid code! Please make sure you send the correct code.");
                        return;
                    }

                    if(Main::getInstance()->verificationDB->isLinkCodeExpired($message)) {
                        $this->api->sendMessage(null, $messageChannelId, "The code has expired! Please generate a new one.");
                        return;
                    }

                    $xuid = Main::getInstance()->verificationDB->getXuidFromLinkCode($message);
                    Main::getInstance()->verificationDB->addLinkEntry($authorId, $xuid);
                    $this->api->sendMessage(null, $messageChannelId, "You have successfully linked your Minecraft account! Thank you!");
                } else {
                    $this->api->sendMessage(null, $messageChannelId, "Invalid code! Please make sure you send the correct code.");
                }
            }
        });

    }

    public function onReady(DiscordReady $event) {
        if($this->minecraftDiscordChatSendServerStatus){
            $guildId = $this->settingsConfig->config->get("discord-server-id");
            $channel = $this->settingsConfig->config->get("discord-minecraft-chat-channel");

            $this->api->sendMessage($guildId, $channel, ":white_check_mark: **The server started**");
        }
    }

    public function onDisable(PluginDisableEvent $event) {
        $plugin = $event->getPlugin()->getName();
        if($plugin !== "DiscordBot") return;

        if($this->minecraftDiscordChatSendServerStatus){
            $guildId = $this->settingsConfig->config->get("discord-server-id");
            $channel = $this->settingsConfig->config->get("discord-minecraft-chat-channel");

            $this->api->sendMessage($guildId, $channel, ":x: **The server stopped**");
        }
    }
}