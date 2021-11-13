<?php
require_once 'database.php';
require_once 'user.php';
class Order {
    private int $id;
    private int $place_id;
    private ?int $assigned;
    private array $acts = [NULL, NULL];
    private int $created;
    private ?int $completed;
    private int $status;

    function __construct(int $id) {
        global $sql;
        $q = $sql->query("SELECT * FROM orders WHERE order_id = '$id'");
        if ($q->num_rows == 0) throw new InvalidArgumentException("Order do not exists!");
        $res = $q->fetch_assoc();
        $this->id = $id;
        $this->place_id = $res['place_id'];
        $this->assigned = $res['assigned'];
        $this->acts = [$res['act1_id'], $res['act2_id']];
        $this->created = strtotime($res['created']);
        $this->completed = (is_null($res['completed'])) ? NULL : strtotime($res['completed']);
        $this->status = $res['status'];
    }
    public function getID(): int {
        return $this->id;
    }
    public function getPlaceID(): int {
        return $this->place_id;
    }
    public function getAssigned(): ?int {
        return $this->assigned;
    }
    public function setAssigned(User|NULL $user): void {
        global $sql;
        if (is_null($user)) {
            $sql->query("UPDATE orders SET assigned = NULL WHERE order_id = '$this->id'");
            $this->assigned = NULL;
            $this->setStatus(0);
        } else {
            $this->assigned = $user->getUserID();
            $sql->query("UPDATE orders SET assigned = '$this->assigned' WHERE order_id = '$this->id'");
            $this->setStatus(1);
        }
    }
    public function getAct(int $act): ?int {
        if ($act < 0 || $act > 1) throw new InvalidArgumentException("Act is out of bounds!");
        return $this->acts[$act];
    }
    public function setAct(int $act, int $id): void {
        global $sql;
        if ($act < 0 || $act > 1) throw new InvalidArgumentException("Act is out of bounds!");
        $sql->query("UPDATE orders SET act{$act}_id = '$id' WHERE order_id = '$this->id'");
        $this->acts[$act] = $id;
    }
    public function getCreated(): int {
        return $this->created;
    }
    public function getCompleted(): ?int {
        return $this->completed;
    }
    public function setCompleted(int $unix): void {
        global $sql;
        $uform = date("Y-m-d H:i:s", $unix);
        $sql->query("UPDATE orders SET completed = '$uform' WHERE order_id = '$this->id'");
        $this->completed = $unix;
    }
    public function getStatus(): int {
        return $this->status;
    }
    public function setStatus(int $status): void {
        global $sql;
        $sql->query("UPDATE orders SET status = '$status' WHERE order_id = '$this->id'");
        $this->status = $status;
    }
}