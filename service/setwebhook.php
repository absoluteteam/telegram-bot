<?php
/*
 * Sets Telegram Webhook server address or retrieves information about it
 */

require 'requireall.php';

$api = new API();

try {
    if (isset($_GET['url'])) { // URL that webhook should be sent to
        $params = array(
            'url' => $_GET['url']
        );
        var_dump($api->executeMethodJSON("setWebhook", $params));
    } else var_dump($api->executeMethodJSON("getWebhookInfo"));
} catch (TelegramException $e) {
    echo "Script was unable to set webhook or retrieve information due to TelegramException: {$e->getMessage()}";
} catch (Exception $e) {
    echo "Script was unable to set webhook or retrieve information due to fail of cURL request: {$e->getMessage()}";
}