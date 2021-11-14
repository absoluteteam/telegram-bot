<?php
require_once 'database.php';
require_once 'user.php';
class Order {
    private int $id;
    private int $place_id;
    private ?int $assigned;
    private ?array $acts;
    private ?string $actstring;
    private int $created;
    private ?int $completed;
    private int $status;
    private array $addresses;
    private array $addrcover;
    function __construct(int $id) {
        global $sql;
        $q = $sql->query("SELECT * FROM orders WHERE order_id = '$id'");
        if ($q->num_rows == 0) throw new InvalidArgumentException("Order do not exists!");
        $res = $q->fetch_assoc();
        $this->id = $id;
        $this->place_id = $res['place_id'];
        $this->assigned = $res['assigned'];
        $this->actstring = $res['act_ids'];
        $this->acts = explode(",", $res['act_ids']);
        $this->created = strtotime($res['created']);
        $this->completed = (is_null($res['completed'])) ? NULL : strtotime($res['completed']);
        $this->status = $res['status'];
        $addr = $res['addresses'];
        $addrsplit = explode(";", $addr);
        $re = array();
        foreach ($addrsplit as $dataset) {
            $re[] = explode(":", $dataset);
        }
        $this->addresses = $re;
        $this->addrcover = explode(",", $res['addrcover']);
    }
    private function buildActOnString(): void {
        $this->acts = explode(",", $this->actstring);
    }
    public function isEnroute(): int|false {
        $cover = $this->addrcover;
        for ($i = 0; $i < count($cover); $i++) {
            if ($cover[$i] == 1) return $i;
        }
        return false;
    }
    public function updateCover(int $covernum, int $value): void {
        global $sql;
        if ($covernum < 0 || $covernum >= count($this->addrcover)) throw new InvalidArgumentException();
        elseif ($value < 0 || $value > 2) throw new InvalidArgumentException();
        $this->addrcover[$covernum] = $value;
        $str = "";
        foreach ($this->addrcover as $a) {
            $str .= $a.",";
        }
        $str = mb_substr($str, 0, -1);
        $sql->query("UPDATE orders SET addrcover = '$str' WHERE order_id = '$this->id'");
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
    public function appendAct(int $id): void {
        global $sql;
        $na = $this->actstring."{$id},";
        $sql->query("UPDATE orders SET act_ids = '$na' WHERE order_id = '$this->id'");
        $this->actstring = $na;
        $this->buildActOnString();
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
    public function getAddresses(): array {
        return $this->addresses;
    }
    public function getAddressesLimited(): array {
        $addr = $this->addresses;
        $na = array();
        for ($i = 0; $i < count($addr); $i++) {
            if ($this->addrcover[$i] != 2) {
                $temp[0] = $addr[$i];
                $temp[1] = $i;
                $na[] = $temp;
            }
        }
        return $na;
    }
    public function getAddressesParsed(): string {
        $os = "";
        $alim = $this->getAddressesLimited();
        for ($i = 1; $i <= count($alim); $i++) {
            $os .= "{$i}. {$alim[$i-1][0][0]}\n";
        }
        return $os;
    }
    public function finish(): void {
        global $sql;
        $sql->query("UPDATE orders SET completed = NOW(), status = 99, assigned = NULL WHERE order_id = '$this->id'");
    }
}