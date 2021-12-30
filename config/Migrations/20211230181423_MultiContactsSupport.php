<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Class MultiContactsSupport
 */
class MultiContactsSupport extends AbstractMigration {

    public $autoId = false;

    public function up() {
        if (!$this->hasTable('telegram_contacts_access_keys')) {
            $this->table('telegram_contacts_access_keys')
                ->addColumn('contact_uuid', 'string', [
                    'default' => null,
                    'limit' => 37,
                    'null' => false,
                ])
                ->addColumn('access_key', 'string', [
                    'default' => '',
                    'limit' => 255,
                    'null' => false,
                ])
                ->addColumn('created', 'datetime', [
                    'default' => null,
                    'null' => false,
                ])->addColumn('modified', 'datetime', [
                    'default' => null,
                    'null' => false,
                ])
                ->addPrimaryKey('contact_uuid')
                ->addForeignKey('contact_uuid', 'contacts', 'uuid', [
                    'delete' => 'cascade'
                ])
                ->create();
        }
        if ($this->hasTable('telegram_settings')) {
            $this->table('telegram_settings')
                ->removeColumn('access_key')
                ->save();
        }
        if ($this->hasTable('telegram_chats')) {
            $this->table('telegram_chats')
                ->addColumn('contact_uuid', 'string', [
                    'default' => null,
                    'limit' => 37,
                    'null' => false,
                ])
                ->save();
        }
    }

    public function down() {
        if ($this->hasTable('telegram_contacts_access_keys')) {
            $this->table('telegram_contacts_access_keys')->drop()->save();
        }
        if ($this->hasTable('telegram_settings')) {
            $this->table('telegram_settings')
                ->addColumn('access_key', 'string', [
                    'default' => '',
                    'limit' => 255,
                    'null' => false,
                ])
                ->save();
        }
        if ($this->hasTable('telegram_chats')) {
            $this->table('telegram_chats')
                ->removeColumn('contact_uuid')
                ->save();
        }
    }
}
