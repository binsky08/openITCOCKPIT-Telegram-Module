# TelegramModule plugin for openITCOCKPIT 4

![image](https://github.com/binsky08/openITCOCKPIT-Telegram-Module/assets/30630233/f1c2da54-cf3c-44e8-b083-1ad5726e13ca)

## Features
- Receive notifications in Telegram as usual based on openITCOCKPIT contacts
- Multiple Telegram connections for different openITCOCKPIT contacts possible
- Notifications sent by the module can be received by any chat with a real telegram user or by channels
- Acknowledge issues with a button in the bot message (optional with enabled two-way integration)

Read [Usage](#usage) for more information

## Installation

### Latest version from GitHub
- Clone the module contents into /opt/openitc/frontend/plugins/TelegramModule
- Run `composer install` in /opt/openitc/frontend/plugins/TelegramModule
- Run `openitcockpit-update --cc`

#### Commands step by step:
```bash
git clone https://github.com/binsky08/openITCOCKPIT-Telegram-Module /opt/openitc/frontend/plugins/TelegramModule

apt-get install composer
composer -d /opt/openitc/frontend/plugins/TelegramModule install

openitcockpit-update --cc
```

Note for existing installations: the customization of `/opt/openitc/frontend/src/Lib/PluginManager.php` is no longer needed!

## Configuration

### Basic configuration

- Open [BotFather](https://t.me/botfather) in telegram
- Run `/newbot`
    - define e.g. openITCOCKPIT as name
    - define e.g. oitcTGBot as username

Your new bot is now located at [t.me/oitcTGBot](https://t.me/oitcTGBot)

- Copy the HTTP API token which was generated by the BotFather.

**Keep your token secure and store it safely, it can be used by anyone to control your bot!**

- Open the openITCOCKPIT Telegram configuration, enter the copied bot token and save the configuration.

- Add the 'host-notify-by-telegram' and 'service-notify-by-telegram' commands to your notification contact.

- To apply the changed notification contact (with the Telegram notification commands), run an export in openITCOCKPIT.

### Extended configuration (recommended)

To enable real-time interactions from the bot with openITCOCKIT, setup the two-way webhook integration.

- Open the openITCOCKPIT Telegram configuration and check the optional field "Enable two-way webhook integration".

- Make sure the configured "External webhook domain" is correct and openITCOCKPIT is reachable from the Internet using this domain.

- Create a new openITCOCKPIT API Key for your user and insert it into the "Webhook api key" field.

- Save the changes and try it out.

openITCOCKPIT automatically set up the webhook for the bot with the given token. This connection can be removed by saving with an unchecked "Enable two-way webhook integration" field.

## Usage

Notifications sent by the openITCOCKPIT Telegram Module can be received by any chat with a real telegram user or by channels.

A Telegram chat gets only the notifications for a defined openITCOCKPIT contact. (identified by custom authentication codes)

To start the interaction, search the bot username in the Telegram global search field and start a chat with your bot.

After starting the chat you need to authorize yourself with an authentication code for a openITCOCKPIT Contact, that can be manually generated in the openITCOCKPIT Telegram configuration for Contacts containing a Telegram notification command. (type `/auth xxx` in the chat with your key as xxx)

If the authorization was successful, use the bot control command `/start` to enable openITCOCKPIT notifications. Otherwise you will not receive any notifications.

With enabled two-way integration, issues can be acknowledged by simply clicking the button for an action provided by the bot message.

If your openITCOCKPIT is reachable from the Internet and you configured the two-way webhook integration, interactions with the bot are processed within seconds.

If your openITCOCKPIT is not reachable from the Internet, the built-in basic one-way integration calls up interactions cached by Telegram and processes them every minute. (Therefore, interactions with the bot can take up to a minute to process!)

Run `/help` in bot chat to get more information about how to control the bot.

## Update

- Extract the current release archive over the existing TelegramModule folder or pull from the main branch
- Install dependencies
- Run an openitcockpit-update
- Apply new user roles if required
- Refresh monitoring configuration in the openITCOCKPIT web frontend

![grafik](https://user-images.githubusercontent.com/30630233/147828242-40f4b3a1-4404-4169-9b8c-c57017eb08fe.png)


### Commands
```
git -C /opt/openitc/frontend/plugins/TelegramModule pull
composer -d /opt/openitc/frontend/plugins/TelegramModule install
openitcockpit-update --cc
```

## Troubleshooting
- If you aren't using the two-way integration or switching from two-way to one-way, it could happen that the cronjob is not going to be executed by openITCOCKPIT.
  - That problem should be solved after running it once manually: `oitc cronjobs -f -t TelegramProcessUpdates`
  - Note that the cron job is not needed if you use a two-way setup of this plugin
- It seems that you `/auth` command works, but you chat is not appearing in the openITCOCKPIT Telegram settings?
  - At the moment it's required to have a Telegram username specified - set it and try it again
