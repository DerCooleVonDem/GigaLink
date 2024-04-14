<?php

declare(strict_types=1);

namespace DerCooleVonDem\GigaLink;

use DerCooleVonDem\GigaLink\commands\DiscordLinkCommand;
use DerCooleVonDem\GigaLink\config\SettingsConfig;
use DerCooleVonDem\GigaLink\db\VerificationDB;
use DerCooleVonDem\GigaLink\event\ChatListener;
use JaxkDev\DiscordBot\Models\Presence\Activity\Activity;
use JaxkDev\DiscordBot\Models\Presence\Activity\ActivityType;
use JaxkDev\DiscordBot\Plugin\Api;
use JaxkDev\DiscordBot\Plugin\Events\DiscordReady;
use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;

class Main extends PluginBase implements Listener
{
    use SingletonTrait;

    public SettingsConfig $settingsConfig;
    public VerificationDB $verificationDB;
    public Api $api;
    public ChatListener $chatListener;

    protected function onEnable(): void
    {
        $discordBotPlugin = $this->getServer()->getPluginManager()->getPlugin("DiscordBot");
        if($discordBotPlugin === null) {
            $this->getLogger()->error("DiscordBot plugin not found! Please install it from: https://poggit.pmmp.io/p/DiscordBot");
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }

        if($discordBotPlugin instanceof \JaxkDev\DiscordBot\Plugin\Main) {
            $this->api = $discordBotPlugin->getApi();
        } else {
            $this->getLogger()->error("DiscordBot plugin not found! Please install it from: https://poggit.pmmp.io/p/DiscordBot");
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }

        self::setInstance($this);

        $this->settingsConfig = new SettingsConfig($this);
        $this->verificationDB = new VerificationDB($this);
        $this->chatListener = new ChatListener();

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getServer()->getPluginManager()->registerEvents($this->chatListener, $this);

        $commandMap = $this->getServer()->getCommandMap();
        $commandMap->register("link", new DiscordLinkCommand());
    }

    public function onReady(DiscordReady $event)
    {
        if(!$this->settingsConfig->config->get("discord-activity")) {
            $event->setActivity(new Activity("", ActivityType::GAME));
            return;
        }

        $type = $this->settingsConfig->config->get("discord-activity-type");
        $text = $this->settingsConfig->config->get("discord-activity-text");

        switch ($type) {
            case "streaming":
                $event->setActivity(new Activity($text, ActivityType::STREAMING));
                break;
            case "listening":
                $event->setActivity(new Activity($text, ActivityType::LISTENING));
                break;
            case "watching":
                $event->setActivity(new Activity($text, ActivityType::WATCHING));
                break;
            default:
                $event->setActivity(new Activity($text, ActivityType::GAME));
                break;
        }
    }
}
