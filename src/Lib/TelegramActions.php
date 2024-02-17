<?php

namespace TelegramModule\Lib;

// require manual autoload for core-called files, since openitcockpit core does not autoload plugin dependencies
require_once dirname(__FILE__) . '/../../vendor/autoload.php';

use App\Model\Table\HostsTable;
use App\Model\Table\ProxiesTable;
use App\Model\Table\ServicesTable;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\ORM\TableRegistry;
use itnovum\openITCOCKPIT\Core\System\Gearman;
use itnovum\openITCOCKPIT\Exceptions\HostNotFoundException;
use itnovum\openITCOCKPIT\Exceptions\ServiceNotFoundException;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Exception;
use TelegramBot\Api\InvalidArgumentException;
use TelegramBot\Api\Types\CallbackQuery;
use TelegramBot\Api\Types\Update;
use TelegramModule\Model\Table\TelegramChatsTable;
use TelegramModule\Model\Table\TelegramContactsAccessKeysTable;
use TelegramModule\Model\Table\TelegramSettingsTable;

class TelegramActions
{

    /** @var TelegramChatsTable */
    private $telegramChatsTable;

    /** @var TelegramSettingsTable */
    private $telegramSettingsTable;

    /** @var TelegramContactsAccessKeysTable */
    private $telegramContactsAccessKeysTable;

    private $telegramSettings = [];
    private $proxySettings = [];
    private $updateOffset = 0;
    private $token = '';

    /** @var BotApi $bot */
    private $bot;

    /**
     * @throws \Exception
     */
    public function __construct(string $tokenOverwrite = null)
    {
        $this->telegramChatsTable = TableRegistry::getTableLocator()->get('TelegramModule.TelegramChats');
        $this->telegramSettingsTable = TableRegistry::getTableLocator()->get('TelegramModule.TelegramSettings');
        $this->telegramContactsAccessKeysTable = TableRegistry::getTableLocator()->get(
            'TelegramModule.TelegramContactsAccessKeys'
        );

        $this->telegramSettings = $this->telegramSettingsTable->getTelegramSettings();

        /** @var ProxiesTable $proxiesTable */
        $proxiesTable = TableRegistry::getTableLocator()->get('Proxies');
        $this->proxySettings = $proxiesTable->getSettings();

        $this->token = $tokenOverwrite != null ? $tokenOverwrite : $this->telegramSettings->get('token');
        if ($this->token && trim($this->token) !== '') {
            $this->bot = new BotApi($this->token);

            if ($this->telegramSettings->get('use_proxy') && $this->proxySettings['enabled'] == 1) {
                $this->bot->setProxy(sprintf('%s:%s', $this->proxySettings['ipaddress'], $this->proxySettings['port']));
            }

            if ($this->telegramSettings->get('last_update_id') > 0) {
                $this->updateOffset = $this->telegramSettings->get('last_update_id');
            }
        } else {
            echo __d('oitc_console', 'No telegram bot token configured!') . PHP_EOL;
            return null;
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function isTwoWayWebhookEnabled(): bool
    {
        return $this->telegramSettings->get('two_way') == 1;
    }

    /**
     * @param string $completeWebhookUrl
     * @return string
     * @throws Exception
     */
    public function enableWebhook(string $completeWebhookUrl): string
    {
        return $this->bot->setWebhook($completeWebhookUrl);
    }

    /**
     * @throws Exception
     */
    public function disableWebhook()
    {
        $this->bot->deleteWebhook();
    }

    /**
     * @return \Exception|Exception|InvalidArgumentException|Update[]
     */
    public function getUpdates()
    {
        try {
            return $this->bot->getUpdates($this->getUpdateOffset() + 1);
        } catch (Exception|InvalidArgumentException $exception) {
            return $exception;
        }
    }

    /**
     * @param array $updateArray
     * @return Update
     * @throws InvalidArgumentException
     */
    public function parseUpdate(array $updateArray): Update
    {
        return Update::fromResponse($updateArray);
    }

    /**
     * @param $updates
     * @throws HostNotFoundException
     * @throws ServiceNotFoundException
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function processUpdates($updates)
    {
        foreach ($updates as $update) {
            $this->processUpdate($update);
        }

        if (sizeof($updates) > 0) {
            if ($this->telegramSettings->get('token') == '') {
                $this->telegramSettings->set('token', $this->token);
            }
            $this->telegramSettingsTable->patchEntity(
                $this->telegramSettings,
                ['last_update_id' => $this->getUpdateOffset()]
            );
            $this->telegramSettingsTable->save($this->telegramSettings);
            if ($this->telegramSettings->hasErrors()) {
                print_r($this->telegramSettings->getErrors());
            }
        }
    }

    /**
     * Check if chat authorization of the given update is already present in database.
     * If not, this will return false and sends out the welcome and auth info message.
     * @param Update $update
     * @return bool
     * @throws Exception
     * @throws InvalidArgumentException
     */
    private function isChatAuthorized(Update $update): bool
    {
        if ($this->telegramChatsTable->existsByChatId($update->getMessage()->getChat()->getId())) {
            return true;
        }

        $this->bot->sendMessage(
            $update->getMessage()->getChat()->getId(),
            sprintf(
                $this->getText('welcome'),
                $update->getMessage()->getFrom()->getFirstName(),
                $update->getMessage()->getFrom()->getLastName()
            ),
            "Markdown"
        );
        $this->bot->sendMessage(
            $update->getMessage()->getChat()->getId(),
            sprintf(
                $this->getText('auth'),
                $update->getMessage()->getFrom()->getFirstName(),
                $update->getMessage()->getFrom()->getLastName()
            ),
            "Markdown"
        );
        return false;
    }

    /**
     * @param Update $update
     * @throws HostNotFoundException
     * @throws ServiceNotFoundException
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function processUpdate(Update $update)
    {
        if ($this->updateOffset < $update->getUpdateId()) {
            $this->updateOffset = $update->getUpdateId();
        }

        if ($update->getMessage()) {
            switch (trim($update->getMessage()->getText())) {
                case '/auth':
                    // this is not the correct authentication command
                    // send message to inform the user about the correct command
                    $this->bot->sendMessage(
                        $update->getMessage()->getChat()->getId(),
                        sprintf(
                            $this->getText('auth'),
                            $update->getMessage()->getFrom()->getFirstName(),
                            $update->getMessage()->getFrom()->getLastName()
                        ),
                        "Markdown"
                    );
                    break;

                case '/start':
                    if ($this->isChatAuthorized($update)) {
                        $telegramChat = $this->telegramChatsTable->getByChatId(
                            $update->getMessage()->getChat()->getId()
                        );
                        $this->telegramChatsTable->patchEntity($telegramChat, ['enabled' => true]);
                        $this->telegramChatsTable->save($telegramChat);

                        $this->bot->sendMessage(
                            $update->getMessage()->getChat()->getId(),
                            sprintf(
                                $this->getText('successfully_enabled'),
                                $update->getMessage()->getFrom()->getFirstName(),
                                $update->getMessage()->getFrom()->getLastName()
                            ),
                            "Markdown"
                        );
                    }
                    break;

                case '/stop':
                    if ($this->isChatAuthorized($update)) {
                        $telegramChat = $this->telegramChatsTable->getByChatId(
                            $update->getMessage()->getChat()->getId()
                        );
                        $this->telegramChatsTable->patchEntity($telegramChat, ['enabled' => false]);
                        $this->telegramChatsTable->save($telegramChat);

                        $this->bot->sendMessage(
                            $update->getMessage()->getChat()->getId(),
                            sprintf(
                                $this->getText('successfully_disabled'),
                                $update->getMessage()->getFrom()->getFirstName(),
                                $update->getMessage()->getFrom()->getLastName()
                            ),
                            "Markdown"
                        );
                    }
                    break;

                case '/help':
                    $this->bot->sendMessage(
                        $update->getMessage()->getChat()->getId(),
                        sprintf(
                            $this->getText('help'),
                            $update->getMessage()->getFrom()->getFirstName(),
                            $update->getMessage()->getFrom()->getLastName()
                        ),
                        "Markdown"
                    );
                    break;

                case '/delete':
                    if ($this->isChatAuthorized($update)) {
                        $telegramChat = $this->telegramChatsTable->getByChatId(
                            $update->getMessage()->getChat()->getId()
                        );
                        $this->telegramChatsTable->delete($telegramChat);

                        $this->bot->sendMessage(
                            $update->getMessage()->getChat()->getId(),
                            sprintf(
                                $this->getText('deleted_successfully'),
                                $update->getMessage()->getFrom()->getFirstName(),
                                $update->getMessage()->getFrom()->getLastName()
                            ),
                            "Markdown"
                        );
                    }
                    break;

                default:
                    if (str_starts_with(trim($update->getMessage()->getText()), '/auth ')) {
                        $this->processAuthenticationMessage($update);
                    }
            }
        }

        $callbackQuery = $update->getCallbackQuery();
        if ($callbackQuery) {
            $this->processCallbackQueryData($callbackQuery);
        }
    }

    /**
     * @param Update $update
     * @return void
     * @throws Exception
     * @throws InvalidArgumentException
     */
    private function processAuthenticationMessage(Update $update): void
    {
        $providedAuthKey = str_replace('/auth ', '', trim($update->getMessage()->getText()));
        $contactAccessKey = $this->telegramContactsAccessKeysTable->getContactByAccessKey($providedAuthKey);
        if ($contactAccessKey !== null) {
            if (!$this->telegramChatsTable->existsByChatId($update->getMessage()->getChat()->getId())) {
                $startedFromUsername = $update->getMessage()->getFrom()->getUsername();
                if ($startedFromUsername == null) {
                    // use definite existing first name as fallback if the telegram user has no username
                    $startedFromUsername = $update->getMessage()->getFrom()->getFirstName();
                }
                $telegramChat = $this->telegramChatsTable->newEntity([
                    'chat_id'               => $update->getMessage()->getChat()->getId(),
                    'enabled'               => false,
                    'started_from_username' => $startedFromUsername,
                    'contact_uuid'          => $contactAccessKey->get('contact_uuid')
                ]);
                $this->telegramChatsTable->save($telegramChat);

                $this->bot->sendMessage(
                    $update->getMessage()->getChat()->getId(),
                    sprintf(
                        $this->getText('auth_successful'),
                        $update->getMessage()->getFrom()->getFirstName(),
                        $update->getMessage()->getFrom()->getLastName()
                    ),
                    "Markdown"
                );
                $this->bot->sendMessage(
                    $update->getMessage()->getChat()->getId(),
                    sprintf(
                        $this->getText('help'),
                        $update->getMessage()->getFrom()->getFirstName(),
                        $update->getMessage()->getFrom()->getLastName()
                    ),
                    "Markdown"
                );
            }
        } else {
            $this->bot->sendMessage(
                $update->getMessage()->getChat()->getId(),
                sprintf(
                    $this->getText('auth_unsuccessful'),
                    $update->getMessage()->getFrom()->getFirstName(),
                    $update->getMessage()->getFrom()->getLastName()
                ),
                "Markdown"
            );
        }
    }

    /**
     * @param CallbackQuery $callbackQuery
     * @return void
     * @throws Exception
     * @throws HostNotFoundException
     * @throws InvalidArgumentException
     * @throws ServiceNotFoundException
     */
    public function processCallbackQueryData(CallbackQuery $callbackQuery): void
    {
        $callbackData = $callbackQuery->getData();

        if (str_starts_with($callbackData, 'ack_host_')) {
            $full_ack_user_name = sprintf(
                "%s %s",
                $callbackQuery->getFrom()->getFirstName(),
                $callbackQuery->getFrom()->getLastName()
            );
            if ($this->acknowledgeHost(str_replace('ack_host_', '', $callbackData), $full_ack_user_name)) {
                $this->bot->sendMessage(
                    $callbackQuery->getMessage()->getChat()->getId(),
                    sprintf(
                        __d('oitc_console', 'Successfully acknowledged by %s %s.'),
                        $callbackQuery->getMessage()->getChat()->getFirstName(),
                        $callbackQuery->getMessage()->getChat()->getLastName()
                    ),
                    "Markdown",
                    false,
                    $callbackQuery->getMessage()->getMessageId()
                );
            }
        } else {
            if (str_starts_with($callbackData, 'ack_service_')) {
                $full_ack_user_name = sprintf(
                    "%s %s",
                    $callbackQuery->getFrom()->getFirstName(),
                    $callbackQuery->getFrom()->getLastName()
                );
                if ($this->acknowledgeService(
                    str_replace('ack_service_', '', $callbackData),
                    $full_ack_user_name
                )) {
                    $this->bot->sendMessage(
                        $callbackQuery->getMessage()->getChat()->getId(),
                        sprintf(
                            __d('oitc_console', 'Successfully acknowledged by %s %s.'),
                            $callbackQuery->getMessage()->getChat()->getFirstName(),
                            $callbackQuery->getMessage()->getChat()->getLastName()
                        ),
                        "Markdown",
                        false,
                        $callbackQuery->getMessage()->getMessageId()
                    );
                }
            }
        }
    }

    /**
     * @return int|mixed|null
     */
    public function getUpdateOffset()
    {
        return $this->updateOffset;
    }

    /**
     * @param $telegram_chat_id
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function notifyChatAboutDeauthorization($telegram_chat_id)
    {
        $this->bot->sendMessage(
            $telegram_chat_id,
            $this->getText('deleted_successfully'),
            "Markdown",
            false,
            null,
            null
        );
    }

    /**
     * @param string $hostUuid
     * @param string $author
     * @return bool whether host exists and ack was sent, or not
     */
    private function acknowledgeHost(string $hostUuid, string $author): bool
    {
        /** @var HostsTable $hostsTable */
        $hostsTable = TableRegistry::getTableLocator()->get('Hosts');

        // we don't need the host, just check if it exists in a simple way
        try {
            $hostsTable->getHostByUuid($hostUuid);

            (new Gearman())->sendBackground('cmd_external_command', [
                'command'     => 'ACKNOWLEDGE_HOST_PROBLEM',
                'parameters'  => [
                    'hostUuid'   => $hostUuid,
                    'sticky'     => 1,
                    'notify'     => 0, // do not enable
                    'persistent' => 1,
                    'author'     => $author,
                    'comment'    => __('Issue got acknowledged by {0} via Telegram.', $author),
                ],
                'satelliteId' => null
            ]);
        } catch (RecordNotFoundException $e) {
            return false;
        }

        return true;
    }

    /**
     * @param string $serviceUuid
     * @param string $author
     * @return bool whether service exists and ack was sent, or not
     */
    private function acknowledgeService(string $serviceUuid, string $author): bool
    {
        /** @var HostsTable $hostsTable */
        $hostsTable = TableRegistry::getTableLocator()->get('Hosts');
        /** @var ServicesTable $servicesTable */
        $servicesTable = TableRegistry::getTableLocator()->get('Services');

        // we don't need the service, just check if it exists in a simple way
        try {
            $service = $servicesTable->getServiceByUuid($serviceUuid);
            $hostUuid = $hostsTable->getHostUuidById($service->get('host_id'));

            (new Gearman())->sendBackground('cmd_external_command', [
                'command'     => 'ACKNOWLEDGE_SVC_PROBLEM',
                'parameters'  => [
                    'hostUuid'    => $hostUuid,
                    'serviceUuid' => $serviceUuid,
                    'sticky'      => 1,
                    'notify'      => 0, // do not enable
                    'persistent'  => 1,
                    'author'      => $author,
                    'comment'     => __('Issue got acknowledged by {0} via Telegram.', $author),
                ],
                'satelliteId' => null
            ]);
        } catch (RecordNotFoundException $e) {
            return false;
        }

        return true;
    }

    /**
     * @param string $key
     * @return string
     */
    public function getText(string $key)
    {
        switch ($key) {
            case 'welcome':
                return __d('oitc_console', "Nice to see you %s %s");
            case 'successfully_enabled':
                return __d('oitc_console', "You have successfully enabled openITCOCKPIT notifications in this chat.");
            case 'successfully_disabled':
                return __d('oitc_console', "You have successfully disabled openITCOCKPIT notifications in this chat.");
            case 'auth':
                return __d(
                    'oitc_console',
                    "If you want to enable openITCOCKPIT notifications in this chat, you have to authorize yourself with the (in openITCOCKPIT) configured API access key.
Use `/auth xxx` to authorize yourself. Replace xxx with the right API access key."
                );
            case 'auth_successful':
                return __d('oitc_console', 'The authorization was successful. You are now able to use this bot :)');
            case 'auth_unsuccessful':
                return __d('oitc_console', 'Unfortunately the authorization was unsuccessful.');
            case 'deleted_successfully':
                return __d(
                    'oitc_console',
                    'Connection successfully deleted. To use this bot again, you will need to re-authorize it.'
                );
            case 'delay':
                return __d(
                    'oitc_console',
                    '_Note: Interactions with this bot are only processed every minute due to the missing webhook configuration. As a result, there may be slight delays in executing commands._'
                );
            case 'help':
                return __d(
                        'oitc_console',
                        "Here are some instructions and commands for using this bot.

*Bot control commands*:

`/auth xxx` authorizes yourself to activate the bot usage
`/start` enables openITCOCKPIT notifications
`/stop` disables openITCOCKPIT notifications
`/help` shows this help text again
`/delete` deletes this bot connection in openITCOCKPIT"
                    ) .
                    ($this->isTwoWayWebhookEnabled() ? '' : PHP_EOL . PHP_EOL . $this->getText('delay'));
        }
    }
}
