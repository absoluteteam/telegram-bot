<?php
class UserNotFoundException extends Exception {}
class User {
    private int $id;
    private int $access;

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
        $this->access = $d['access_level'];
    }

    public function getID(): int {
        return $this->id;
    }

    public function getAccessLevel(): int {
        return $this->access;
    }
}