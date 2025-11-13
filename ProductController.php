<?php
class ProductController {
    private $conn;
    private $auth;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->auth = new Auth();
        
        // Checking JWT except the OPTIONS
        if ($_SERVER['REQUEST_METHOD'] != 'OPTIONS') {
            $this->auth->verifyToken();
        }
    }

    public function getAllProducts() {
        $sql = "SELECT p.*, c.name as category_name 
                FROM product p 
                LEFT JOIN category c ON p.id_category = c.category_id
                ORDER BY p.product_id DESC";
        $result = $this->conn->query($sql);

        $products = [];
        while ($row = $result->fetch_assoc()) {
            $products[] = [
                'product_id' => (int)$row['product_id'],
                'sku' => $row['sku'],
                'active' => (bool)$row['active'],
                'id_category' => $row['id_category'] ? (int)$row['id_category'] : null,
                'name' => $row['name'],
                'image' => $row['image'],
                'description' => $row['description'],
                'price' => (float)$row['price'],
                'stock' => (int)$row['stock'],
                'category_name' => $row['category_name']
            ];
        }

        echo json_encode($products);
    }

    public function getProduct($id) {
        $stmt = $this->conn->prepare("SELECT p.*, c.name as category_name FROM product p LEFT JOIN category c ON p.id_category = c.category_id WHERE p.product_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            http_response_code(404);
            echo json_encode(["message" => "Product not found"]);
            return;
        }

        $product = $result->fetch_assoc();
        echo json_encode([
            'product_id' => (int)$product['product_id'],
            'sku' => $product['sku'],
            'active' => (bool)$product['active'],
            'id_category' => $product['id_category'] ? (int)$product['id_category'] : null,
            'name' => $product['name'],
            'image' => $product['image'],
            'description' => $product['description'],
            'price' => (float)$product['price'],
            'stock' => (int)$product['stock'],
            'category_name' => $product['category_name']
        ]);
    }

    public function createProduct() {
        $data = json_decode(file_get_contents("php://input"), true);
        
        $required = ['sku', 'name', 'price', 'stock'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                http_response_code(400);
                echo json_encode(["message" => "Missing required field: $field"]);
                return;
            }
        }

        // Checking SKU to be unique
        $check_stmt = $this->conn->prepare("SELECT product_id FROM product WHERE sku = ?");
        $check_stmt->bind_param("s", $data['sku']);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            http_response_code(400);
            echo json_encode(["message" => "SKU already exists"]);
            return;
        }

        $stmt = $this->conn->prepare("INSERT INTO product (sku, active, id_category, name, image, description, price, stock) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $active = isset($data['active']) ? (int)$data['active'] : 1;
        $id_category = !empty($data['id_category']) ? $data['id_category'] : null;
        $image = $data['image'] ?? '';
        $description = $data['description'] ?? '';
        
        $stmt->bind_param(
            "siisssdi",
            $data['sku'],
            $active,
            $id_category,
            $data['name'],
            $image,
            $description,
            $data['price'],
            $data['stock']
        );

        if ($stmt->execute()) {
            http_response_code(201);
            echo json_encode([
                "message" => "Product created successfully", 
                "product_id" => $stmt->insert_id
            ]);
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Error creating product: " . $stmt->error]);
        }
    }

    public function updateProduct($id) {
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Checking products if they exist
        $check_stmt = $this->conn->prepare("SELECT product_id FROM product WHERE product_id = ?");
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            http_response_code(404);
            echo json_encode(["message" => "Product not found"]);
            return;
        }

        $fields = [];
        $types = '';
        $values = [];
        
        $allowed_fields = ['sku', 'active', 'id_category', 'name', 'image', 'description', 'price', 'stock'];
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $types .= $this->getBindType($data[$field]);
                $values[] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            http_response_code(400);
            echo json_encode(["message" => "No fields to update"]);
            return;
        }
        
        // SKU check after update
        if (isset($data['sku'])) {
            $check_stmt = $this->conn->prepare("SELECT product_id FROM product WHERE sku = ? AND product_id != ?");
            $check_stmt->bind_param("si", $data['sku'], $id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                http_response_code(400);
                echo json_encode(["message" => "SKU already exists"]);
                return;
            }
        }
        
        $types .= 'i';
        $values[] = $id;
        
        $sql = "UPDATE product SET " . implode(', ', $fields) . " WHERE product_id = ?";
        $stmt = $this->conn->prepare($sql);
        
        $stmt->bind_param($types, ...$values);
        
        if ($stmt->execute()) {
            echo json_encode(["message" => "Product updated successfully"]);
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Error updating product: " . $stmt->error]);
        }
    }

    public function deleteProduct($id) {
        // Checking product existence
        $check_stmt = $this->conn->prepare("SELECT product_id FROM product WHERE product_id = ?");
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            http_response_code(404);
            echo json_encode(["message" => "Product not found"]);
            return;
        }

        $stmt = $this->conn->prepare("DELETE FROM product WHERE product_id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            echo json_encode(["message" => "Product deleted successfully"]);
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Error deleting product: " . $stmt->error]);
        }
    }

    private function getBindType($value) {
        if (is_int($value)) return 'i';
        if (is_float($value) || is_double($value)) return 'd';
        return 's';
    }
}
?>