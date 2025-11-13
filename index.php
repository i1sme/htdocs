<?php
require_once 'config.php';

// CORS headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// DB connection
$conn = new mysqli(Config::DB_HOST, Config::DB_USER, Config::DB_PASS, Config::DB_NAME);
if ($conn->connect_error) {
    http_response_code(503);
    echo json_encode(["message" => "Database connection failed: " . $conn->connect_error]);
    exit;
}

// Class pre-load
spl_autoload_register(function ($class_name) {
    if (file_exists($class_name . '.php')) {
        include $class_name . '.php';
    }
});

// Route receiving
$request_uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Request settings deletion
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/index.php', '', $path);

// Routing
try {
    switch(true) {
        // Authentification
        case $path == '/auth' && $method == 'POST':
            handleAuthentication($conn);
            break;
            
        // products
        case $path == '/product' && $method == 'GET':
            $controller = new ProductController($conn);
            $controller->getAllProducts();
            break;
            
        case preg_match('#^/product/(\d+)$#', $path, $matches) && $method == 'GET':
            $controller = new ProductController($conn);
            $controller->getProduct($matches[1]);
            break;
            
        case $path == '/product' && $method == 'POST':
            $controller = new ProductController($conn);
            $controller->createProduct();
            break;
            
        case preg_match('#^/product/(\d+)$#', $path, $matches) && $method == 'PUT':
            $controller = new ProductController($conn);
            $controller->updateProduct($matches[1]);
            break;
            
        case preg_match('#^/product/(\d+)$#', $path, $matches) && $method == 'DELETE':
            $controller = new ProductController($conn);
            $controller->deleteProduct($matches[1]);
            break;
            
        // Categorys
        case $path == '/category' && $method == 'GET':
            $controller = new CategoryController($conn);
            $controller->getAllCategories();
            break;
            
        case preg_match('#^/category/(\d+)$#', $path, $matches) && $method == 'GET':
            $controller = new CategoryController($conn);
            $controller->getCategory($matches[1]);
            break;
            
        case $path == '/category' && $method == 'POST':
            $controller = new CategoryController($conn);
            $controller->createCategory();
            break;
            
        case preg_match('#^/category/(\d+)$#', $path, $matches) && $method == 'PUT':
            $controller = new CategoryController($conn);
            $controller->updateCategory($matches[1]);
            break;
            
        case preg_match('#^/category/(\d+)$#', $path, $matches) && $method == 'DELETE':
            $controller = new CategoryController($conn);
            $controller->deleteCategory($matches[1]);
            break;
            
        // Docus
        case $path == '/openapi.yaml' && $method == 'GET':
            header('Content-Type: application/yaml');
            readfile('openapi.yaml');
            break;
            
        // Swagger UI
        case ($path == '/docs' || $path == '/') && $method == 'GET':
            header('Content-Type: text/html');
            echo '<html>
                <head>
                    <title>"Shop" API Documentation</title>
                    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@3/swagger-ui.css">
                </head>
                <body>
                    <div id="swagger-ui"></div>
                    <script src="https://unpkg.com/swagger-ui-dist@3/swagger-ui-bundle.js"></script>
                    <script>
                        SwaggerUIBundle({
                            url: "openapi.yaml",
                            dom_id: "#swagger-ui",
                            presets: [
                                SwaggerUIBundle.presets.apis,
                                SwaggerUIBundle.presets.standalone
                            ]
                        });
                    </script>
                </body>
            </html>';
            break;
            
        default:
            http_response_code(404);
            echo json_encode(["message" => "Endpoint not found"]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "Server error: " . $e->getMessage()]);
}

$conn->close();

// Func auth
function handleAuthentication($conn) {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!isset($data['username']) || !isset($data['password'])) {
        http_response_code(400);
        echo json_encode(["message" => "Username and password required"]);
        exit;
    }
    
    // Checking user in... users
    $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ?");
    $stmt->bind_param("s", $data['username']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(401);
        echo json_encode(["message" => "Invalid credentials"]);
        return;
    }
    
    $user = $result->fetch_assoc();
    
    // Password check
    if (password_verify($data['password'], $user['password'])) {
        $payload = [
            'iss' => 'online-shop-api',
            'iat' => time(),
            'exp' => time() + Config::JWT_EXPIRATION,
            'user_id' => $user['id'],
            'username' => $user['username']
        ];
        
        $token = \ReallySimpleJWT\Token::customPayload($payload, Config::JWT_SECRET);
        
        echo json_encode([
            "message" => "Authentication successful",
            "token" => $token,
            "expires_in" => Config::JWT_EXPIRATION,
            "user" => [
                "id" => $user['id'],
                "username" => $user['username']
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode(["message" => "Invalid credentials"]);
    }
}
?>