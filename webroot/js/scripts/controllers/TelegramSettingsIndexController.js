angular.module('openITCOCKPIT')
    .controller('TelegramSettingsIndexController', function ($scope, $http, $state, NotyService, RedirectService) {

        $scope.telegramSettings = {
            token: '',
            access_key: '',
            two_way: true,
            use_proxy: false,
            external_webhook_domain: '',
            webhook_api_key: ''
        };
        $scope.contacts = [];
        $scope.contactsAccessKeys = [];
        $scope.chats = [];

        $scope.hasError = null;

        $scope.load = function () {
            $http.get("/telegram_module/TelegramSettings/index.json", {
                params: {
                    'angular': true
                }
            }).then(function (result) {
                $scope.telegramSettings = result.data.telegramSettings;
                $scope.contacts = result.data.contacts;
                $scope.contactsAccessKeys = result.data.contactsAccessKeys;
                $scope.chats = result.data.chats;

            }, function errorCallback(result) {
                if (result.status === 403) {
                    $state.go('403');
                }

                if (result.status === 404) {
                    $state.go('404');
                }
            });
        };


        $scope.submit = function () {
            if ($scope.telegramSettings.two_way && ($scope.telegramSettings.external_webhook_domain === "" || $scope.telegramSettings.webhook_api_key === "")) {
                NotyService.genericError({message: "Fill out all required fields!"});
            } else {
                $http.post("/telegram_module/TelegramSettings/index.json?angular=true",
                    $scope.telegramSettings
                ).then(function (result) {
                    NotyService.genericSuccess();
                    $scope.errors = null;
                }, function errorCallback(result) {
                    NotyService.genericError();
                    if (result.data.hasOwnProperty('error')) {
                        $scope.errors = result.data.error;
                    }
                });
            }
        };

        $scope.isContactAccessKeyGenerated = function (contact_uuid) {
            for (const contactAccessKeyObj of $scope.contactsAccessKeys) {
                if (contactAccessKeyObj.contact_uuid === contact_uuid) {
                    return contactAccessKeyObj.access_key;
                }
            }
            return false;
        };

        $scope.generateAccessKeyForContact = function (contact_uuid) {
            $http.post("/telegram_module/TelegramSettings/genKey.json?angular=true",
                {
                    'contact_uuid': contact_uuid
                }
            ).then(function (result) {
                if (result.data.contactsAccessKeys) {
                    $scope.contactsAccessKeys = result.data.contactsAccessKeys;
                    NotyService.genericSuccess();
                    $scope.errors = null;
                } else {
                    NotyService.genericError();
                }
            }, function errorCallback(result) {
                NotyService.genericError();
                if (result.data.hasOwnProperty('error')) {
                    $scope.errors = result.data.error;
                }
            });
        };

        $scope.removeAccessKeyForContact = function (contact_uuid) {
            $http.post("/telegram_module/TelegramSettings/rmKey.json?angular=true",
                {
                    'contact_uuid': contact_uuid
                }
            ).then(function (result) {
                if (result.data.contactsAccessKeys) {
                    $scope.contactsAccessKeys = result.data.contactsAccessKeys;
                    NotyService.genericSuccess();
                    $scope.errors = null;
                } else {
                    NotyService.genericError();
                }
            }, function errorCallback(result) {
                NotyService.genericError();
                if (result.data.hasOwnProperty('error')) {
                    $scope.errors = result.data.error;
                }
            });
        };

        $scope.getContactForUuid = function (contact_uuid) {
            for (const contact of $scope.contacts) {
                if (contact.uuid === contact_uuid) {
                    return contact;
                }
            }
            return null;
        };

        $scope.deleteChat = function (id) {
            $http.post("/telegram_module/TelegramSettings/rmChat.json?angular=true",
                {
                    'id': id
                }
            ).then(function (result) {
                if (result.data.chats) {
                    $scope.chats = result.data.chats;
                    NotyService.genericSuccess();
                    $scope.errors = null;
                } else {
                    NotyService.genericError();
                }
            }, function errorCallback(result) {
                NotyService.genericError();
                if (result.data.hasOwnProperty('error')) {
                    $scope.errors = result.data.error;
                }
            });
        };

        $scope.sendTestChatMessage = function (id) {
            $http.post("/telegram_module/TelegramSettings/sendTestChatMessage.json?angular=true",
                {
                    'id': id
                }
            ).then(function (result) {
                if (result.data.responseMessage && result.data.success !== undefined) {
                    if (result.data.success) {
                        NotyService.genericSuccess({message: result.data.responseMessage});
                    } else {
                        NotyService.genericError({message: result.data.responseMessage});
                    }
                } else {
                    NotyService.genericError();
                }
            }, function errorCallback(result) {
                NotyService.genericError();
            });
        };

        $scope.load();
    });
