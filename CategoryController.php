<?php
class CategoryController {
    private $conn;
    private $auth;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->auth = new Auth();
        
        if ($_SERVER['REQUEST_METHOD'] != 'OPTIONS') {
            $this->auth->verifyToken();
        }
    }

    public function getAllCategories() {
        $result = $this->conn->query("SELECT * FROM category ORDER BY category_id DESC");
        
        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = [
                'category_id' => (int)$row['category_id'],
                'active' => (bool)$row['active'],
                'name' => $row['name']
            ];
        }

        echo json_encode($categories);
    }
    //Choosing category by ID
    public function getCategory($id) {
        $stmt = $this->conn->prepare("SELECT * FROM category WHERE category_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            http_response_code(404);
            echo json_encode(["message" => "Category not found"]);
            return;
        }

        $category = $result->fetch_assoc();
        echo json_encode([
            'category_id' => (int)$category['category_id'],
            'active' => (bool)$category['active'],
            'name' => $category['name']
        ]);
    }
   //Creating category
    public function createCategory() {
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (!isset($data['name']) || empty($data['name'])) {
            http_response_code(400);
            echo json_encode(["message" => "Missing required field: name"]);
            return;
        }

        $stmt = $this->conn->prepare("INSERT INTO category (active, name) VALUES (?, ?)");
        $active = isset($data['active']) ? (int)$data['active'] : 1;
        $stmt->bind_param("is", $active, $data['name']);

        if ($stmt->execute()) {
            http_response_code(201);
            echo json_encode([
                "message" => "Category created successfully", 
                "category_id" => $stmt->insert_id
            ]);
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Error creating category: " . $stmt->error]);
        }
    }

    public function updateCategory($id) {
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Checking categories if they exist
        $check_stmt = $this->conn->prepare("SELECT category_id FROM category WHERE category_id = ?");
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            http_response_code(404);
            echo json_encode(["message" => "Category not found"]);
            return;
        }

        $fields = [];
        $types = '';
        $values = [];
        
        $allowed_fields = ['active', 'name'];
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
        
        $types .= 'i';
        $values[] = $id;
        
        $sql = "UPDATE category SET " . implode(', ', $fields) . " WHERE category_id = ?";
        $stmt = $this->conn->prepare($sql);
        
        $stmt->bind_param($types, ...$values);
        
        if ($stmt->execute()) {
            echo json_encode(["message" => "Category updated successfully"]);
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Error updating category: " . $stmt->error]);
        }
    }
    //Category deletion
    public function deleteCategory($id) {
        // Checking categories if they exist
        $check_stmt = $this->conn->prepare("SELECT category_id FROM category WHERE category_id = ?");
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            http_response_code(404);
            echo json_encode(["message" => "Category not found"]);
            return;
        }

        // Checinkg if products are in this category
        $check_products = $this->conn->prepare("SELECT COUNT(*) as product_count FROM product WHERE id_category = ?");
        $check_products->bind_param("i", $id);
        $check_products->execute();
        $result = $check_products->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['product_count'] > 0) {
            http_response_code(400);
            echo json_encode(["message" => "Cannot delete category with associated products"]);
            return;
        }

        $stmt = $this->conn->prepare("DELETE FROM category WHERE category_id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            echo json_encode(["message" => "Category deleted successfully"]);
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Error deleting category: " . $stmt->error]);
        }
    }

    private function getBindType($value) {
        if (is_int($value)) return 'i';
        return 's';
    }
}
?>