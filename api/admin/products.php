<?php
header("Content-Type: application/json; charset=UTF-8");
require_once '../../config/database.php';
require_once '../../config/session.php';
requireAdmin();

$db = (new Database())->getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input")) ?: $_POST;

try {
    // GET - List all products
    if ($method === 'GET') {
        $stmt = $db->query("SELECT p.*, c.name as category_name, c.slug as category_slug 
                            FROM products p 
                            LEFT JOIN categories c ON p.category_id = c.id 
                            ORDER BY p.created_at DESC");
        $products = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $products]);
    }
    
    // POST - Create product
    elseif ($method === 'POST') {
        $stmt = $db->prepare("INSERT INTO products (name, category_id, description, price, stock, image_url, label, rating) 
                              VALUES (:name, :cat, :desc, :price, :stock, :img, :label, :rating)");
        $stmt->execute([
            ':name' => htmlspecialchars($data->name),
            ':cat' => $data->category_id,
            ':desc' => htmlspecialchars($data->description ?? ''),
            ':price' => $data->price,
            ':stock' => $data->stock ?? 0,
            ':img' => $data->image_url ?? '',
            ':label' => $data->label ?? '',
            ':rating' => $data->rating ?? 0
        ]);
        echo json_encode(['success' => true, 'id' => $db->lastInsertId(), 'message' => 'Produk ditambahkan']);
    }
    
    // PUT - Update product
    elseif ($method === 'PUT') {
        $stmt = $db->prepare("UPDATE products SET name=:name, category_id=:cat, description=:desc, 
                              price=:price, stock=:stock, image_url=:img, label=:label, rating=:rating 
                              WHERE id=:id");
        $stmt->execute([
            ':name' => htmlspecialchars($data->name),
            ':cat' => $data->category_id,
            ':desc' => htmlspecialchars($data->description ?? ''),
            ':price' => $data->price,
            ':stock' => $data->stock ?? 0,
            ':img' => $data->image_url ?? '',
            ':label' => $data->label ?? '',
            ':rating' => $data->rating ?? 0,
            ':id' => $data->id
        ]);
        echo json_encode(['success' => true, 'message' => 'Produk diupdate']);
    }
    
    // DELETE - Delete product
    elseif ($method === 'DELETE') {
        $id = $_GET['id'] ?? $data->id ?? null;
        if (!$id) throw new Exception('ID tidak valid');
        
        $stmt = $db->prepare("UPDATE products SET is_available = 0 WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(['success' => true, 'message' => 'Produk dihapus']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>