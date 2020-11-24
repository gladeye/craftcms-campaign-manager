<?php

namespace Gladeye\CampaignManager\migrations;

use Craft;
use craft\db\Migration;

/**
 * Install migration.
 */
class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Place installation code here...
        if(!$this->db->tableExists('{{%campaigns}}')) {
            $this->createTables();
            $this->createIndexes();
            $this->createForeignKeys();
        }

    }


    public function createTables() {
        $this->createTable('{{%campaigns}}', [
            'id' => $this->integer()->notNull(),
            'name' => $this->string(),
            'description' => $this->text(),
            'publishedAt'=> $this->dateTime(),
            'state' => $this->string(16),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY(id)'
        ]);
        $this->createTable('{{%campaigns_entries}}', [
            'id' => $this->primaryKey(),
            'campaignId' => $this->integer()->notNull(),
            'entryId' => $this->integer()->notNull(),
            'type' => $this->string(16)->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
        $this->createTable('{{%campaign_tokens}}', [
            'id' => $this->primaryKey(),
            'token' => $this->string(32)->notNull(),
            'campaignId' => $this->integer()->notNull(),
            'expiryDate' => $this->dateTime(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
    }
    public function createIndexes() {
        $this->createIndex(null, '{{%campaigns_entries}}', ['campaignId'], false);
        $this->createIndex(null, '{{%campaigns_entries}}', ['entryId'], false);
        $this->createIndex(null, '{{%campaign_tokens}}', ['campaignId'], false);
    }

    public function createForeignKeys() {
        $this->addForeignKey(null, '{{%campaigns_entries}}', ['campaignId'], '{{%campaigns}}', ['id'], 'CASCADE', null);
        $this->addForeignKey(null, '{{%campaigns_entries}}', ['entryId'], '{{%elements}}', ['id'], 'CASCADE', null);
        $this->addForeignKey(null, '{{%campaign_tokens}}', ['campaignId'], '{{%campaigns}}', ['id'], 'CASCADE', null);
        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%campaigns}}', 'id'),
            '{{%campaigns}}', 'id', '{{%elements}}', 'id', 'CASCADE', null);

    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        // Place uninstallation code here...
        $this->dropTable('{{%campaign_tokens}}');
        $this->dropTable('{{%campaigns_entries}}');
        $this->dropTable('{{%campaigns}}');
    }
}
