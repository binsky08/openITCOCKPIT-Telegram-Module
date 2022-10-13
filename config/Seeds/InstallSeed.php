<?php

declare(strict_types=1);

use Migrations\AbstractSeed;

/**
 * Class InstallSeed
 *
 * Created:
 * oitc bake seed -p TelegramModule --table commands --data Install
 *
 * Apply:
 * oitc migrations seed -p TelegramModule
 */
class InstallSeed extends AbstractSeed {
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeds is available here:
     * https://book.cakephp.org/migrations/3/en/index.html#seed-seeding-your-database
     *
     * @return void
     */
    public function run() {

        //Commands
        $table = $this->table('commands');

        //Migrate wrong typed host notification command from host-notifiy-by-telegram to host-notify-by-telegram
        $this->getAdapter()->getQueryBuilder()->update($table->getName())
            ->set('name', 'host-notify-by-telegram')
            ->where([
                'command_type' => NOTIFICATION_COMMAND,
                'name'         => 'host-notifiy-by-telegram'
            ])
            ->execute();

        $data = [
            [
                'name'         => 'host-notify-by-telegram',
                'command_line' => '/opt/openitc/frontend/bin/cake TelegramModule.TelegramNotification --type Host --notificationtype $NOTIFICATIONTYPE$ --hostuuid "$HOSTNAME$" --state "$HOSTSTATEID$" --output "$HOSTOUTPUT$" --ackauthor "$NOTIFICATIONAUTHOR$" --ackcomment "$NOTIFICATIONCOMMENT$" --contactuuid "$CONTACTNAME$"',
                'command_type' => NOTIFICATION_COMMAND,
                'human_args'   => null,
                'uuid'         => \itnovum\openITCOCKPIT\Core\UUID::v4(),
                'description'  => 'Send host notifications to Telegram',
            ],
            [
                'name'         => 'service-notify-by-telegram',
                'command_line' => '/opt/openitc/frontend/bin/cake TelegramModule.TelegramNotification --type Service --notificationtype $NOTIFICATIONTYPE$ --hostuuid "$HOSTNAME$" --serviceuuid "$SERVICEDESC$" --state "$SERVICESTATEID$" --output "$SERVICEOUTPUT$" --ackauthor "$NOTIFICATIONAUTHOR$" --ackcomment "$NOTIFICATIONCOMMENT$" --contactuuid "$CONTACTNAME$"',
                'command_type' => NOTIFICATION_COMMAND,
                'human_args'   => null,
                'uuid'         => \itnovum\openITCOCKPIT\Core\UUID::v4(),
                'description'  => 'Send service notifications to Telegram',
            ]
        ];

        //Check if records exists
        foreach ($data as $index => $record) {
            $QueryBuilder = $this->getAdapter()->getQueryBuilder();

            $stm = $QueryBuilder->select('*')
                ->from($table->getName())
                ->where([
                    'command_type' => NOTIFICATION_COMMAND,
                    'name'         => $record['name']
                ])
                ->execute();
            $result = $stm->fetchAll();

            if (empty($result)) {
                $table->insert($record)->save();
            } else {
                $QueryBuilder
                    ->update($table->getName())
                    ->where([
                        'command_type' => NOTIFICATION_COMMAND,
                        'name'         => $record['name']
                    ])
                    ->set('command_line', $record['command_line'])
                    ->execute();
            }
        }

        //Cronjobs
        $table = $this->table('cronjobs');

        $data = [
            [
                'task'     => 'TelegramProcessUpdates',
                'plugin'   => 'TelegramModule',
                'interval' => '1',
                'enabled'  => '1',
            ]
        ];

        //Check if records exists
        foreach ($data as $index => $record) {
            $QueryBuilder = $this->getAdapter()->getQueryBuilder();

            $stm = $QueryBuilder->select('*')
                ->from($table->getName())
                ->where([
                    'plugin' => $record['plugin'],
                    'task'   => $record['task']
                ])
                ->execute();
            $result = $stm->fetchAll();

            if (empty($result)) {
                $table->insert($record)->save();
            }
        }
    }
}
