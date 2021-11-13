require_once 'database.php';
class ActConstructor {
    function __construct(User $creator, string $img_path, float $lat, float $lon, int $type = 0) {
        global $sql;
        $stmt = $sql->prepare("INSERT INTO acts(creator_id, pic_path, type, geo_data_lat, geo_data_lon) VALUES (?, ?, ?, ?, ?)");
        $id = $creator->getUserID();
        $stmt->bind_param("isidd", $id, $img_path, $type, $lat, $lon);
        if ($type == 0) {
            $orderid = $creator->getActiveOrder();
        }
        if ($stmt->execute() === false) throw new Exception($stmt->error);
    }
}