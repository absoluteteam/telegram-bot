<?php
require_once 'database.php';
class Company {
    protected int $id;
    protected string $name;
    function __construct(int $id) {
        global $sql;
        $q = $sql->query("SELECT company_name FROM companies WHERE company_id = '$id'");
        if ($q->num_rows != 1) throw new InvalidArgumentException("Company does not exists!");
        $g = $q->fetch_array();
        $this->id = $id;
        $this->name = $g[0];
    }
    public function getCompanyName(): string {
        return $this->name;
    }
}
class Shop extends Company {
    private int $place_id;
    private int $group_id;
    private array $geo;
    private string $address;
    function __construct(int $place_id)
    {
        global $sql;
        $q = $sql->query("SELECT * FROM places WHERE place_id = '$place_id'");
        if ($q->num_rows != 1) throw new InvalidArgumentException("Place does not exists!");
        $fa = $q->fetch_assoc();
        $this->place_id = $place_id;
        $this->group_id = $fa['group_id'];
        $id = $fa['company_id'];
        $this->geo = [$fa['geo_data_lat'], $fa['geo_data_lon']];
        $this->address = $fa['address'];
        try {
            parent::__construct($id);
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
    }
    public function getShopLocation(): array {
        return $this->geo;
    }
    public function getShopAddress(): string {
        return $this->address;
    }
}