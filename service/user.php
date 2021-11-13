<?php
require_once '../bot/api.php';
class UserNotFoundException extends Exception {}
class User {
    private int $id;
    private int $user_id;
    private int $access;
    private ?int $active_order;
    private ?int $active_status;

    function __construct(int $id, bool $createuser = false, ?int $phone_number = NULL) {
        global $sql;
        $q = $sql->query("SELECT * FROM users WHERE tg_id = '$id'") or die($sql->error);
        if ($q->num_rows != 1) {
            if ($createuser) {
                if (is_null($phone_number)) throw new InvalidArgumentException("Phone number is NULL");
                $q = $sql->query("SELECT * FROM users WHERE phone = '$phone_number'");
                if ($q->num_rows != 1) throw new Exception("User has no ability to use bot!");
                $sql->query("UPDATE users SET tg_id = '$id' WHERE phone = '$phone_number'");
            }
            else throw new UserNotFoundException("User is not found!");
        }
        $d = $q->fetch_assoc();
        $this->id = $id;
        $this->user_id = $d['user_id'];
        $this->access = $d['access_level'];
        $this->active_order = $d['active_order'];
        $this->active_status = $d['active_status'];
    }

    public function getID(): int {
        return $this->id;
    }

    public function getAccessLevel(): int {
        return $this->access;
    }

    public function sendNotification(string $text): void {
        API::sendMessage($this->id, $text, 1);
    }

    public function setOrderStatus(int $status): void {
        global $sql;
        if (is_null($this->active_order)) throw new Exception();
        $sql->query("UPDATE users SET active_status = '$status' WHERE user_id = '$this->user_id'");
    }

    public function assignToOrder(?int $orderid): void {
        global $sql;
        if (!is_null($this->active_order)) throw new Exception();
        if (is_null($orderid)) {
            $sql->query("UPDATE users SET active_order = NULL, active_status = NULL WHERE user_id = '$this->user_id'");
            $this->active_status = NULL;
            $this->active_order = NULL;
        } else {
            $sql->query("UPDATE users SET active_order = {$orderid}, active_status = 0 WHERE user_id = '$this->user_id'") or die($sql->error);
            $this->active_order = $orderid;
            $this->active_status = 0;
        }
    }

    public function getActiveOrder(): int|null {
        return $this->active_order;
    }

    public function getOrderStatus(): int|null {
        return $this->active_status;
    }
}