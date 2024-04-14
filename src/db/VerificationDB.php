<?php

namespace DerCooleVonDem\GigaLink\db;

use pocketmine\plugin\Plugin;
use SQLite3;

class VerificationDB {

    public SQLite3 $db;

    public function __construct(Plugin $plugin) {
        $dataFolder = $plugin->getDataFolder();

        // sqlite3 database
        $this->db = new SQLite3($dataFolder . "verifications.db");

        // create table if not exists
        $this->db->exec("CREATE TABLE IF NOT EXISTS verifications (discord_id TEXT, xuid TEXT)");

        // create index if not exists
        $this->db->exec("CREATE UNIQUE INDEX IF NOT EXISTS discord_id_index ON verifications (discord_id)");

        // create table link codes (code, xuid, expires)
        $this->db->exec("CREATE TABLE IF NOT EXISTS link_codes (code TEXT, xuid TEXT, expires INTEGER)");
    }

    public function addLinkEntry(string $discordId, string $xuid) {
        $stmt = $this->db->prepare("INSERT INTO verifications (discord_id, xuid) VALUES (:discord_id, :xuid)");
        $stmt->bindValue(":discord_id", $discordId);
        $stmt->bindValue(":xuid", $xuid);
        $stmt->execute();
    }

    public function getMinecraftXuid(string $discordId) {
        $stmt = $this->db->prepare("SELECT xuid FROM verifications WHERE discord_id = :discord_id");
        $stmt->bindValue(":discord_id", $discordId);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return $row["xuid"];
    }

    public function getDiscordId(string $xuid) {
        $stmt = $this->db->prepare("SELECT discord_id FROM verifications WHERE xuid = :xuid");
        $stmt->bindValue(":xuid", $xuid);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return $row["discord_id"];
    }

    public function hasLinkEntry(string $discordId) {
        $stmt = $this->db->prepare("SELECT * FROM verifications WHERE discord_id = :discord_id");
        $stmt->bindValue(":discord_id", $discordId);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return $row !== false;
    }

    public function hasLinkEntryXuid(string $xuid) {
        $stmt = $this->db->prepare("SELECT * FROM verifications WHERE xuid = :xuid");
        $stmt->bindValue(":xuid", $xuid);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return $row !== false;
    }

    public function removeLinkEntry(string $discordId) {
        $stmt = $this->db->prepare("DELETE FROM verifications WHERE discord_id = :discord_id");
        $stmt->bindValue(":discord_id", $discordId);
        $stmt->execute();
    }

    public function removeLinkEntryXuid(string $xuid) {
        $stmt = $this->db->prepare("DELETE FROM verifications WHERE xuid = :xuid");
        $stmt->bindValue(":xuid", $xuid);
        $stmt->execute();
    }

    public function getLinkCount() {
        $result = $this->db->query("SELECT COUNT(*) as count FROM verifications");
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return $row["count"];
    }

    public function generateLinkCode(string $xuid) {
        if($this->hasLinkCode($xuid)) {
            $linkCode = $this->getLinkCode($xuid);
            if(!$this->isLinkCodeExpired($linkCode)) {
                return $linkCode;
            }

            // remove expired link code
            $stmt = $this->db->prepare("DELETE FROM link_codes WHERE xuid = :xuid");
            $stmt->bindValue(":xuid", $xuid);
            $stmt->execute();
        }

        // the code expires after 5 minutes
        $expires = time() + 300;

        // generate 5 letter code
        $code = substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 5);

        // insert into database
        $stmt = $this->db->prepare("INSERT INTO link_codes (code, xuid, expires) VALUES (:code, :xuid, :expires)");
        $stmt->bindValue(":code", $code);
        $stmt->bindValue(":xuid", $xuid);
        $stmt->bindValue(":expires", $expires);
        $stmt->execute();

        return $code;
    }

    public function hasLinkCode(string $xuid) {
        $stmt = $this->db->prepare("SELECT * FROM link_codes WHERE xuid = :xuid");
        $stmt->bindValue(":xuid", $xuid);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return $row !== false;
    }

    public function getLinkCode(string $xuid) {
        $stmt = $this->db->prepare("SELECT code FROM link_codes WHERE xuid = :xuid");
        $stmt->bindValue(":xuid", $xuid);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return $row["code"];
    }

    public function doesLinkCodeExist(string $code) {
        $stmt = $this->db->prepare("SELECT * FROM link_codes WHERE code = :code");
        $stmt->bindValue(":code", $code);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return $row !== false;
    }

    public function getXuidFromLinkCode(string $message)
    {
        $stmt = $this->db->prepare("SELECT xuid FROM link_codes WHERE code = :code");
        $stmt->bindValue(":code", $message);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return $row["xuid"];
    }

    public function isLinkCodeExpired(string $code) {
        $stmt = $this->db->prepare("SELECT expires FROM link_codes WHERE code = :code");
        $stmt->bindValue(":code", $code);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        return $row["expires"] < time();
    }
}