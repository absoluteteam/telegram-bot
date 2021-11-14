<?php
/*
 * Handler of Telegram callbacks
 */

require '../service/requireall.php';

$content = file_get_contents("php://input");
$update = json_decode($content);

if (!$update) {
    // received wrong update, must not happen
    exit;
}

if (isset($update->message)) {
    // new message
    $msg = $update->message;
    $msg = new Message($msg);
    $peer = $msg->getPeer();
    $uid = $msg->getUserID();
    $text = $msg->getText();
    $uptext = $msg->getUptext();
    $contact = $msg->getContact();
    $image = $msg->getImage();

    //API::sendMessage(327371196, print_r($update, true), 1);
    // БЛОК РЕГИСТРАЦИИ
    try {
        $user = new User(id: $uid);
    } catch (UserNotFoundException $e) {
        if (!is_null($contact)) {
            if ($contact->user_id != $uid) {
                API::sendMessage($peer, "Произошла ошибка! Повторите операцию!");
                exit;
            }
            $phone = $contact->phone_number;
            try {
                $user = new User($uid, true, $phone);
            } catch (Exception $e) {
                API::sendMessage($peer, "Пользователь не зарегистрирован в системе!");
                exit;
            }
            API::sendKeyboardedMessage($peer, "Вы успешно авторизовались!", json_encode(array("remove_keyboard" => true), JSON_UNESCAPED_UNICODE));
            API::sendMessage($peer, "Вы успешно авторизовались!");
        } else {
            $keyboard = array(
                "resize_keyboard" => true,
                "one_time_keyboard" => true,
                "keyboard" => array(
                    array(
                        array(
                            "text" => "Отправить номер телефона",
                            "request_contact" => true
                        )
                    )
                )
            );
            $kb = json_encode($keyboard, JSON_UNESCAPED_UNICODE);
            API::sendKeyboardedMessage($peer, "Для использования бота войдите с помощью номера телефона.", $kb);
            exit;
        }
    }

    // ОЖИДАНИЕ АДРЕСА
    if ($user->getOrderStatus() !== NULL && ($user->getOrderStatus()) % 2 == 1) {
        if (is_numeric($text)) {
            $choice = intval($text);
            $addr = $user->getActiveOrder()->getAddressesLimited();
            if ($choice < 1 || $choice > count($addr)) {
                API::sendMessage($peer,"Извините, такого варианта нет, необходимо отправить цифру с выбранным вариантом.");
                exit;
            }
            $addr = $addr[$choice-1];
            $addrtext = $addr[0][0];
            $lat = $addr[0][1];
            $lon = $addr[0][2];
            API::sendMessage($peer,"Вы выбрали адрес {$addrtext}. По приезду не забудьте отправить акт!");
            API::sendLocation($peer, $lat, $lon);
            $user->setOrderStatus(2);
            $user->getActiveOrder()->updateCover($addr[1], 1); // todo ?
        } else API::sendMessage($peer,"Извините, необходимо отправить цифру с выбранным вариантом.");
        exit;
    }

    // ФОТО АКТА
    if (!is_null($image)) {
        if ($user->getOrderStatus() === 0 || ($user->getActiveOrder() !== NULL && $user->getActiveOrder()->isEnroute() !== false)) {
            // ЭТО АКТ
            $file_id = $image[count($image)-1]->file_id;
            $gfile = API::getFile($file_id);
            $fpath = $gfile->file_path;
            $file = API::downloadFile($fpath);
            $fresult = 1;
            while ($fresult !== false) {
                $rand = bin2hex(random_bytes(60));
                $fresult = @file_get_contents("../acts/{$rand}.png");
            }
            file_put_contents("../acts/{$rand}.png", $file);
            $stat = $user->getOrderStatus();
            $order = $user->getActiveOrder();
            switch ($stat) {
                case 0:
                    $place = new Shop($order->getPlaceID());
                    $cloc = $place->getShopLocation();
                    new ActConstructor($user, "{$rand}.png", $cloc[0], $cloc[1]);
                    $addr = $order->getAddressesParsed();
                    API::sendMessage($peer, "Спасибо! Адреса нуждающихся:\n\n{$addr}\nВыберите адрес.");
                    break;
                default:
                    $enr = $order->isEnroute();
                    $cloc = $order->getAddresses()[$enr];
                    $cloc = [$cloc[1], $cloc[2]];
                    new ActConstructor($user, "{$rand}.png", $cloc[0], $cloc[1], 1);
                    $order->updateCover($enr, 2);
                    break;
            }
            $addr = $order->getAddressesParsed();
            if (empty($addr)) {
                API::sendMessage($peer,"Спасибо, на сегодня всё! Всего хорошего!");
                $order->finish();
                $user->assignToOrder(NULL);
            } else {
                API::sendMessage($peer, "Спасибо! Адреса нуждающихся:\n\n{$addr}\nВыберите адрес.");
                $user->setOrderStatus($stat + 1);
            }
            exit;
        }
    }
}
