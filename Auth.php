<?php
require_once 'config.php';

class Auth {
    public function verifyToken() {
        $headers = apache_request_headers();
        $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : null;
        
        if (!$authHeader) {
            http_response_code(401);
            echo json_encode(["message" => "Authorization header missing"]);
            exit;
        }
        
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
        } else {
            http_response_code(401);
            echo json_encode(["message" => "Invalid authorization format"]);
            exit;
        }
        
        try {
            // TOken validation
            $result = \ReallySimpleJWT\Token::validate($token, Config::JWT_SECRET);
            
            if (!$result) {
                throw new Exception('Invalid token signature');
            }
            
            $payload = \ReallySimpleJWT\Token::getPayload($token, Config::JWT_SECRET);
            $payloadData = json_decode($payload, true);
            
            // Expiration check
            if (isset($payloadData['exp']) && $payloadData['exp'] < time()) {
                throw new Exception('Token expired');
            }
            
            return $payloadData;
            
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(["message" => "Invalid token: " . $e->getMessage()]);
            exit;
        }
    }
}
?>