<?php

declare(strict_types=1);

namespace TelegramModule\Command;

use Cake\Console\Arguments;
use Cake\Console\Command;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use itnovum\openITCOCKPIT\Exceptions\HostNotFoundException;
use itnovum\openITCOCKPIT\Exceptions\ServiceNotFoundException;
use TelegramBot\Api\Exception;
use TelegramBot\Api\InvalidArgumentException;
use TelegramModule\Lib\TelegramActions;

/**
 * TelegramModule command, used as cronjob for one-way update processing.
 * Trigger it manually by running: oitc cronjobs -f -t TelegramProcessUpdates
 */
class TelegramProcessUpdatesCommand extends Command
{
    /**
     * @param ConsoleOptionParser $parser
     * @return ConsoleOptionParser
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);

        $parser->addOptions([
            'api-token' => ['help' => __d('oitc_console', 'Overwrites the stored Telegram bot HTTP API token')],
        ]);

        return $parser;
    }

    /**
     * @param Arguments $args
     * @param ConsoleIo $io
     * @return void
     * @throws Exception
     * @throws HostNotFoundException
     * @throws InvalidArgumentException
     * @throws ServiceNotFoundException
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        echo '-------------------------------------------------------------------------------' . PHP_EOL;

        if ($args->hasOption('api-token') && $args->getOption('api-token') != '') {
            $token = $args->getOption('api-token');
        }

        $telegramActions = new TelegramActions(isset($token) && $token !== "" ? $token : null);
        if ($telegramActions->isTwoWayWebhookEnabled()) {
            echo 'Telegram api requests are disabled (and not needed) because webhooks are configured!' . PHP_EOL;
        } else {
            $this->processOneWayUpdates($telegramActions);
        }
    }

    /**
     * @throws ServiceNotFoundException
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws HostNotFoundException
     */
    private function processOneWayUpdates(TelegramActions $telegramActions)
    {
        $updates = $telegramActions->getUpdates();
        if ($updates instanceof \Exception) {
            if ($updates instanceof Exception && $updates->getCode() == 409) {
                // Conflict: can't use getUpdates method while webhook is active;
                // use bot->deleteWebhook() within $telegramActions->disableWebhook() to delete the webhook first
                $telegramActions->disableWebhook();
                echo 'Disable maybe misconfigured Telegram api webhook.' . PHP_EOL;

                // fetch updates again after disabling the webhook
                $updates = $telegramActions->getUpdates();
            } else {
                echo $updates->getMessage() . PHP_EOL;
                exit($updates->getCode());
            }
        }

        if (!($updates instanceof \Exception) && sizeof($updates) > 0) {
            $telegramActions->processUpdates($updates);
            echo sizeof($updates) . ' Telegram updates processed.' . PHP_EOL;
        } else {
            echo 'No Telegram updates processed.' . PHP_EOL;
        }
    }
}
