// SPDX-FileCopyrightText: 2021-2026 Timo Triebensky <contact@binsky.org>
//
// SPDX-License-Identifier: MIT

openITCOCKPIT.config(function($stateProvider) {
    $stateProvider
        .state('TelegramSettingsIndex', {
            url: '/telegram_module/TelegramSettings/index',
            templateUrl: "/telegram_module/TelegramSettings/index.html",
            controller: "TelegramSettingsIndexController"
        })
});
