<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$category = isset($_GET['category']) ? $_GET['category'] : 'all';

try {
    if ($category === 'all') {
        $stmt = $db->prepare("SELECT p.*, c.slug as category_slug,
                                     COALESCE(r.average_rating, p.rating) AS display_rating,
                                     COALESCE(r.review_count, 0) AS review_count
                              FROM products p
                              LEFT JOIN categories c ON p.category_id = c.id 
                              LEFT JOIN (
                                  SELECT product_id, AVG(rating) AS average_rating, COUNT(*) AS review_count
                                  FROM product_reviews WHERE status = 'approved' GROUP BY product_id
                              ) r ON r.product_id = p.id
                              WHERE p.is_available = 1 ORDER BY p.sold_count DESC");
        $stmt->execute();
    } else {
        $stmt = $db->prepare("SELECT p.*, c.slug as category_slug,
                                     COALESCE(r.average_rating, p.rating) AS display_rating,
                                     COALESCE(r.review_count, 0) AS review_count
                              FROM products p
                              LEFT JOIN categories c ON p.category_id = c.id 
                              LEFT JOIN (
                                  SELECT product_id, AVG(rating) AS average_rating, COUNT(*) AS review_count
                                  FROM product_reviews WHERE status = 'approved' GROUP BY product_id
                              ) r ON r.product_id = p.id
                              WHERE c.slug = :cat AND p.is_available = 1");
        $stmt->bindParam(':cat', $category);
        $stmt->execute();
    }
    
    $products = $stmt->fetchAll();
    echo json_encode(["success" => true, "data" => $products]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>