<?php

declare(strict_types=1);

namespace TelegramModule\Command;

use Cake\Console\Arguments;
use Cake\Console\Command;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use TelegramModule\Lib\TelegramActions;

/**
 * TelegramModule command.
 */
class TelegramProcessUpdatesCommand extends Command {

    /**
     * @param ConsoleOptionParser $parser
     * @return ConsoleOptionParser
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser {
        $parser = parent::buildOptionParser($parser);

        $parser->addOptions([
            'api-token' => ['help' => __d('oitc_console', 'Overwrites the stored Telegram bot HTTP API token')],
        ]);

        return $parser;
    }

    /**
     * @param Arguments $args
     * @param ConsoleIo $io
     */
    public function execute(Arguments $args, ConsoleIo $io) {
        echo '-------------------------------------------------------------------------------' . PHP_EOL;

        if ($args->hasOption('api-token') && $args->getOption('api-token') != '') {
            $token = $args->getOption('api-token');
        }

        $TelegramActions = new TelegramActions(isset($token) && $token !== "" ? $token : null);
        if (!$TelegramActions->isTwoWayWebhookEnabled()) {
            $updates = $TelegramActions->getUpdates();
            if ($updates instanceof \Exception) {
                if ($updates instanceof \TelegramBot\Api\Exception && $updates->getCode() == 409) {
                    // Conflict: can't use getUpdates method while webhook is active; use deleteWebhook to delete the webhook first
                    $TelegramActions->disableWebhook();
                    echo 'Disable maybe misconfigured Telegram api webhook.' . PHP_EOL;
                    $updates = $TelegramActions->getUpdates();
                } else {
                    echo $updates->getMessage() . PHP_EOL;
                    exit($updates->getCode());
                }
            }

            if (!($updates instanceof \Exception) && sizeof($updates) > 0) {
                $TelegramActions->processUpdates($updates);
                echo sizeof($updates) . ' Telegram updates processed.' . PHP_EOL;
            } else {
                echo 'No Telegram updates processed.' . PHP_EOL;
            }
        } else {
            echo 'Telegram api requests are disabled because webhooks are configured!' . PHP_EOL;
        }
    }
}
