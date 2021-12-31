<?php

declare(strict_types=1);

namespace TelegramModule\Controller;

use App\Model\Table\CommandsTable;
use App\Model\Table\ContactsTable;
use App\Model\Table\SystemsettingsTable;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;
use TelegramModule\Lib\TelegramActions;
use TelegramModule\Model\Table\TelegramContactsAccessKeysTable;
use TelegramModule\Model\Table\TelegramSettingsTable;

/**
 * TelegramSettings Controller
 *
 * @method \TelegramModule\Model\Entity\TelegramSetting[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class TelegramSettingsController extends AppController {

    public function index() {

        if (!$this->isAngularJsRequest()) {
            //Only ship html template
            return;
        }

        /** @var TelegramSettingsTable $TelegramSettingsTable */
        $TelegramSettingsTable = TableRegistry::getTableLocator()->get('TelegramModule.TelegramSettings');
        $telegramSettings = $TelegramSettingsTable->getTelegramSettings();


        /** @var TelegramContactsAccessKeysTable $TelegramContactsAccessKeysTable */
        $TelegramContactsAccessKeysTable = TableRegistry::getTableLocator()->get('TelegramModule.TelegramContactsAccessKeys');
        $contactsAccessKeys = $TelegramContactsAccessKeysTable->getAllAsArray();

        /** @var $ContactsTable ContactsTable */
        $ContactsTable = TableRegistry::getTableLocator()->get('Contacts');
        $contacts = [];
        /*$contactsQuery = $ContactsTable->find()
            ->select([
                'Contacts.name',
                'Contacts.uuid'
            ])
            ->disableHydration()
            ->all();

        if ($contactsQuery !== null) {
            $contacts = $contactsQuery->toArray();
        }*/

        $allContacts = $ContactsTable->find()
            ->contain([
                'Containers',
                'HostCommands',
                'ServiceCommands',
                'Customvariables'
            ])
            ->disableHydration()
            ->all();

        /** @var $CommandsTable CommandsTable */
        $CommandsTable = TableRegistry::getTableLocator()->get('Commands');
        $telegramHostNotificationCommand = $CommandsTable->getCommandByName('host-notifiy-by-telegram');
        $telegramServiceNotificationCommand = $CommandsTable->getCommandByName('service-notifiy-by-telegram');

        foreach ($allContacts as $contact) {
            if (in_array($telegramHostNotificationCommand['Command'][0]['id'], Hash::extract($contact, 'host_commands.{n}.id')) ||
                in_array($telegramServiceNotificationCommand['Command'][0]['id'], Hash::extract($contact, 'service_commands.{n}.id'))) {
                $contacts[] = $contact;
            }
        }


        if ($telegramSettings->get('external_webhook_domain') == "") {
            /** @var SystemsettingsTable $SystemsettingsTable */
            $SystemsettingsTable = TableRegistry::getTableLocator()->get('Systemsettings');
            $result = $SystemsettingsTable->getSystemsettingByKey('SYSTEM.ADDRESS');
            $telegramSettings->set('external_webhook_domain', sprintf('https://%s', $result->get('value')));
        }

        if ($this->request->is('get')) {
            $this->set('telegramSettings', $telegramSettings);
            $this->set('contacts', $contacts);
            $this->set('contactsAccessKeys', $contactsAccessKeys);
            $this->viewBuilder()->setOption('serialize', [
                'telegramSettings', 'contacts', 'contactsAccessKeys'
            ]);
            return;
        }

        if ($this->request->is('post')) {
            $entity = $TelegramSettingsTable->getTelegramSettingsEntity();
            $originalTwoWaySetting = $entity->get('two_way');
            $entity = $TelegramSettingsTable->patchEntity($entity, $this->request->getData(null, []));

            if (($originalTwoWaySetting || $originalTwoWaySetting == 1) && (!$entity->get('two_way') || $entity->get('two_way') == 0) && $entity->get('token') != "") {
                //disable Telegram bot webhook
                $TelegramActions = new TelegramActions($entity->get('token'));
                $TelegramActions->disableWebhook();
            } else if (($entity->get('two_way') || $entity->get('two_way') == 1) && $entity->get('token') != "" && $entity->get('external_webhook_domain') != "" && $entity->get('webhook_api_key') != "") {
                //enable/update Telegram bot webhook
                $TelegramActions = new TelegramActions($entity->get('token'));
                $webhookUrl = sprintf('%s/telegram_module/telegram_webhook/notify.json?apikey=%s', $entity->get('external_webhook_domain'), $entity->get('webhook_api_key'));
                $result = $TelegramActions->enableWebhook($webhookUrl);

                if (!$result || $result === "") {
                    $entity->set('two_way', false);
                }
            }

            $TelegramSettingsTable->save($entity);
            if ($entity->hasErrors()) {
                $this->response = $this->response->withStatus(400);
                $this->set('error', $entity->getErrors());
                $this->viewBuilder()->setOption('serialize', ['error']);
                return;
            }

            $this->set('telegramSettings', $entity);
            $this->viewBuilder()->setOption('serialize', [
                'telegramSettings'
            ]);
        }
    }

    public function genKey() {
        if ($this->isAngularJsRequest()) {
            if ($this->request->is('post')) {
                $contact_uuid = $this->request->getData('contact_uuid');
                if ($contact_uuid !== null) {
                    /** @var $ContactsTable ContactsTable */
                    $ContactsTable = TableRegistry::getTableLocator()->get('Contacts');

                    $query = $ContactsTable->find()
                        ->where([
                            'Contacts.uuid' => $contact_uuid
                        ])
                        ->disableHydration()
                        ->first();

                    if ($query !== null) {
                        /** @var TelegramContactsAccessKeysTable $TelegramContactsAccessKeysTable */
                        $TelegramContactsAccessKeysTable = TableRegistry::getTableLocator()->get('TelegramModule.TelegramContactsAccessKeys');
                        $contactsAccessKey = $TelegramContactsAccessKeysTable->getNewOrExistingAccessKeyByContactUuid($contact_uuid);
                        $TelegramContactsAccessKeysTable->saveOrFail($contactsAccessKey);

                        $contactsAccessKeys = $TelegramContactsAccessKeysTable->getAllAsArray();
                        $this->set('contactsAccessKeys', $contactsAccessKeys);
                        $this->viewBuilder()->setOption('serialize', [
                            'contactsAccessKeys'
                        ]);
                    }
                }
            }
        }
    }

    public function rmKey() {
        if ($this->isAngularJsRequest()) {
            if ($this->request->is('post')) {
                $contact_uuid = $this->request->getData('contact_uuid');
                if ($contact_uuid !== null) {
                    /** @var $ContactsTable ContactsTable */
                    $ContactsTable = TableRegistry::getTableLocator()->get('Contacts');

                    $query = $ContactsTable->find()
                        ->where([
                            'Contacts.uuid' => $contact_uuid
                        ])
                        ->disableHydration()
                        ->first();

                    if ($query !== null) {
                        /** @var TelegramContactsAccessKeysTable $TelegramContactsAccessKeysTable */
                        $TelegramContactsAccessKeysTable = TableRegistry::getTableLocator()->get('TelegramModule.TelegramContactsAccessKeys');
                        $contactsAccessKey = $TelegramContactsAccessKeysTable->getNewOrExistingAccessKeyByContactUuid($contact_uuid);
                        $TelegramContactsAccessKeysTable->deleteOrFail($contactsAccessKey);

                        $contactsAccessKeys = $TelegramContactsAccessKeysTable->getAllAsArray();
                        $this->set('contactsAccessKeys', $contactsAccessKeys);
                        $this->viewBuilder()->setOption('serialize', [
                            'contactsAccessKeys'
                        ]);
                    }
                }
            }
        }
    }
}
