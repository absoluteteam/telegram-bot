<?php
/**
 * Called by API class regarding any issues during exchange with Telegram API
 */
class TelegramException extends Exception { }

/**
 * Used for the communication with Telegram API
 */
class API {
    /**
     * Token issued by Telegram for the API
     */
    private const TOKEN = 'TOKEN';

    /**
     * Fast-constant that consists of Telegram API URL with API token inserted
     */
    private const API_URL = 'https://api.telegram.org/bot' . self::TOKEN . '/';

    /**
     * Used to issue answers to callback webhooks. Only can be used once as webhook is accepting answer and disconnects.
     * @param string $method Telegram API method that will be called
     * @param array|false $params Parameters for desired API method. If false, empty array will be passed.
     */
    private static function issueWebhookAnswer(string $method, array|false $params = false): void {
        if ($params === false) {
            $params = array();
        }
        $params['method'] = $method;
        header("Content-Type: application/json");
        echo json_encode($params); // issueWebhookAnswer echoes json payload to webhook instead of actually calling Telegram API
    }

    /**
     * Used to execute cURL request
     * @param CurlHandle $handle cURL handle to be executed
     * @return mixed Decoded result of JSON object
     * @throws Exception In case cURL request failed
     * @throws InvalidArgumentException In case invalid Telegram API token provided
     * @throws TelegramException In case request fails for non-cURL related reasons
     */
    private static function curlExec(CurlHandle $handle): mixed {
        $response = curl_exec($handle);

        if ($response === false) { // cURL request failed
            $errno = curl_errno($handle);
            $error = curl_error($handle);
            curl_close($handle);
            throw new Exception("CURL returned error {$errno}: {$error}");
        }

        $http_code = intval(curl_getinfo($handle, CURLINFO_HTTP_CODE));
        curl_close($handle);

        if ($http_code >= 500) { // do not want to DDOS server if something goes wrong
            sleep(10);
            return self::curlExec($handle); // Try executing request again
        } elseif ($http_code != 200) { // If request was not successful
            $response = json_decode($response, true);
            if ($http_code == 401) {
                throw new InvalidArgumentException('Invalid access token provided');
            } else {
                throw new TelegramException("Request has failed with error {$response['error_code']}: {$response['description']}");
            }
        } else {
            $response = json_decode($response);
            $response = $response->result;
        }
        return $response;
    }

    /**
     * Used to execute Telegram API method
     * @param string $method Telegram API method that will be called
     * @param array|false $params Parameters for desired API method. If false, empty array will be passed.
     * @return mixed Decoded result of JSON object
     * @throws TelegramException In case request fails for non-cURL related reasons
     * @throws Exception In case cURL request failed
     */
    public static function executeMethod(string $method, array|false $params = false): mixed {
        if ($params === false) {
            $params = array();
        }
        foreach ($params as $key => &$val) {
            // encoding to JSON array parameters, for example reply_markup
            if (!is_numeric($val) && !is_string($val)) {
                $val = json_encode($val);
            }
        }
        $url = self::API_URL . $method . '?' . http_build_query($params);

        $handle = curl_init($url); // Init cURL handle with required options
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($handle, CURLOPT_TIMEOUT, 60);

        try {
            return self::curlExec($handle);
        } catch (TelegramException $e) {
            throw new TelegramException($e->getMessage(), $e->getCode(), $e);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Used to execute Telegram API method by POST request and JSON-encoding all parameters
     * @param string $method Telegram API method that will be called
     * @param array|false $parameters Parameters for desired API method. If false, empty array will be passed.
     * @return mixed Decoded result of JSON object
     * @throws TelegramException In case request fails for non-cURL related reasons
     * @throws Exception In case cURL request failed
     */
    public static function executeMethodJSON(string $method, array|false $parameters = false): mixed {
        if ($parameters === false) {
            $parameters = array();
        }

        $parameters["method"] = $method;

        $handle = curl_init(self::API_URL);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($handle, CURLOPT_TIMEOUT, 60);
        curl_setopt($handle, CURLOPT_POST, true);
        curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($parameters));
        curl_setopt($handle, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));

        try {
            return self::curlExec($handle);
        } catch (TelegramException $e) {
            throw new TelegramException($e->getMessage(), $e->getCode(), $e);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Used to fastly differentiate between all API executors
     * @param string $method Telegram API method that will be called
     * @param array|false $array Parameters for desired API method. If false, empty array will be passed.
     * @param int $executor Executor that will be called. 0 (or any out of bounds integer) - issueWebhookAnswer, 1 - executeMethodJSON, 2 - executeMethod
     * @throws TelegramException In case request fails for non-cURL related reasons
     * @throws Exception In case cURL request failed
     */
    private static function methodExecutor(string $method, array|false $array = false, int $executor = 0): void {
        if ($array === false) $array = array();
        try {
            switch ($executor) {
                case 0: default:
                    self::issueWebhookAnswer($method, $array);
                    break;
                case 1:
                    self::executeMethodJSON($method, $array);
                    break;
                case 2:
                    self::executeMethod($method, $array);
                    break;
            }
        } catch (TelegramException $e) {
            throw new TelegramException($e->getMessage(), $e->getCode(), $e);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Used to send message to Telegram chat
     * @param int|string $chat Chat ID message will be sent into
     * @param string $msg Message to send
     * @param int $type Executor that will be called. 0 (or any out of bounds integer) - issueWebhookAnswer, 1 - executeMethodJSON, 2 - executeMethod
     * @throws InvalidArgumentException If trying to send empty message
     * @throws TelegramException In case request fails for non-cURL related reasons
     * @throws Exception In case cURL request failed
     */
    public static function sendMessage(int|string $chat, string $msg, int $type = 0): void {
        if (empty($msg)) {
            throw new InvalidArgumentException("You are trying to send blank message!");
        }
        try {
            self::methodExecutor("sendMessage", array('chat_id' => $chat, "text" => $msg), $type);
        } catch (TelegramException $e) {
            throw new TelegramException($e->getMessage(), $e->getCode(), $e);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Used to send message with keyboard to Telegram chat
     * @param int|string $chat Chat ID message will be sent into
     * @param string $msg Message to send along with keyboard
     * @param string $kb JSON representation of keyboard
     * @param int $type Executor that will be called. 0 (or any out of bounds integer) - issueWebhookAnswer, 1 - executeMethodJSON, 2 - executeMethod
     * @throws InvalidArgumentException If trying to send empty message or empty keyboard
     * @throws TelegramException In case request fails for non-cURL related reasons
     * @throws Exception In case cURL request failed
     */
    public static function sendKeyboardedMessage(int|string $chat, string $msg, string $kb, int $type = 0): void {
        if (empty($msg)) {
            throw new InvalidArgumentException("You are trying to send blank message!");
        } elseif (empty($kb)) {
            throw new InvalidArgumentException("You are trying to send empty keyboard");
        }
        try {
            self::methodExecutor("sendMessage", array('chat_id' => $chat, "text" => $msg, "reply_markup" => $kb), $type);
        } catch (TelegramException $e) {
            throw new TelegramException($e->getMessage(), $e->getCode(), $e);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Used to edit messages send earlier by bot
     * @param int $editid ID of message to edit
     * @param int|string $chatid Chat ID that contains message
     * @param string $msg New next of the message
     * @param string $kb New keyboard of the message
     * @param int $type Executor that will be called. 0 (or any out of bounds integer) - issueWebhookAnswer, 1 - executeMethodJSON, 2 - executeMethod
     * @throws InvalidArgumentException If trying to send empty message or empty keyboard
     * @throws TelegramException In case request fails for non-cURL related reasons
     * @throws Exception In case cURL request failed
     */
    public static function editInlineMessage(int $editid, int|string $chatid, string $msg, string $kb, int $type = 0): void {
        if (empty($msg)) {
            throw new InvalidArgumentException("You are trying to send blank message!");
        } elseif (empty($kb)) {
            throw new InvalidArgumentException("You are trying to send empty keyboard!");
        }
        try {
            self::methodExecutor("editMessageText", array('chat_id' => $chatid, 'message_id' => $editid, "text" => $msg, "reply_markup" => $kb), $type);
        } catch (TelegramException $e) {
            throw new TelegramException($e->getMessage(), $e->getCode(), $e);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }
    }
}

/**
 * Represents incoming message to Telegram bot
 */
class Message
{
    /**
     * @var int|string Chat ID message is coming from
     */
    private int|string $peer;

    /**
     * @var int User that is author of the message
     */
    private int $uid;

    /**
     * @var string|null Text that user sent to the bot
     */
    private ?string $text;

    /**
     * @var object|null Object that represents contact sent to the bot
     */
    private ?object $contact;

    /**
     * Constructs Message
     * @param object $msg Message object from Telegram callback API
     * @throws InvalidArgumentException If chat argument is absent in object
     */
    function __construct(object $msg)
    {
        if (!isset($msg->chat)) throw new InvalidArgumentException("No chat arg, wrong var passed?");
        $this->peer = $msg->chat->id;
        $this->uid = $msg->from->id;
        $this->text = $msg->text ?? NULL;
        $this->contact = $msg->contact ?? NULL;
    }

    /**
     * Get chat ID message is coming from
     * @return int|string
     */
    public function getPeer(): int|string {
        return $this->peer;
    }

    /**
     * Get user ID that is author of message
     * @return int
     */
    public function getUserID(): int {
        return $this->uid;
    }

    /**
     * Get text that user sent
     * @return string|null
     */
    public function getText(): ?string {
        return $this->text;
    }

    /**
     * Get text that user sent, but uppercase
     * @return string
     */
    public function getUptext(): string {
        return mb_strtoupper($this->text);
    }

    /**
     * Get object of contact that user send or NULL
     * @return object|null
     */
    public function getContact(): ?object {
        return $this->contact;
    }
}
