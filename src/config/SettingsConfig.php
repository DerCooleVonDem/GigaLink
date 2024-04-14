<?php

namespace DerCooleVonDem\GigaLink\config;

use pocketmine\plugin\Plugin;
use pocketmine\utils\Config;

class SettingsConfig {

    public Config $config;

    public function __construct(Plugin $plugin) {
        $config = $plugin->getDataFolder() . "settings.yml";
        $this->config = new Config($config, Config::YAML, [
            "discord-server-id" => "please put your discord servers id here",
            "discord-activity" => true,
            "discord-activity-type" => "watching",
            "discord-activity-text" => "a Minecraft Server",
            "discord-minecraft-chat" => false,
            "discord-minecraft-chat-channel" => "please put your channel id here",
            "discord-minecraft-chat-send-server-status" => false,
            "discord-minecraft-chat-discord-message" => "**&username&**: &message&",
            "discord-minecraft-chat-minecraft-message" => "[Discord] <&username&> &message&"
        ]);
    }

}