<?php
session_start();
header('Content-Type: application/json');
require_once 'conexion.php';

$response = ["success" => false, "message" => "Error desconocido"];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input = json_decode(file_get_contents('php://input'), true);

    $nombres = isset($input['nombres']) ? trim($input['nombres']) : '';
    $apellidos = isset($input['apellidos']) ? trim($input['apellidos']) : '';
    $escuela = isset($input['escuela']) ? trim($input['escuela']) : '';
    $email = isset($input['email']) ? trim($input['email']) : '';
    $password = isset($input['password']) ? trim($input['password']) : '';
    $tipoUsuario = isset($input['tipoUsuario']) ? trim($input['tipoUsuario']) : '';

    if (empty($nombres) || empty($apellidos) || empty($escuela) || empty($email) || empty($password) || empty($tipoUsuario)) {
        echo json_encode(["success" => false, "message" => "Todos los campos son obligatorios."]);
        exit;
    }

    try {
        $passwordHash = $password; 

        // MODIFICADO: CALL directo sin variables OUT
        $stmt = $pdo->prepare("CALL RegistrarUsuario(:email, :pass, :nombres, :apellidos, :tipo, :escuela)");
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':pass', $passwordHash, PDO::PARAM_STR);
        $stmt->bindParam(':nombres', $nombres, PDO::PARAM_STR);
        $stmt->bindParam(':apellidos', $apellidos, PDO::PARAM_STR);
        $stmt->bindParam(':tipo', $tipoUsuario, PDO::PARAM_STR);
        $stmt->bindParam(':escuela', $escuela, PDO::PARAM_STR);
        
        $stmt->execute();
        
        // MODIFICADO: Fetch directo del resultado del SELECT interno
        $output = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $mensajeDB = $output['mensaje'];

        if (strpos($mensajeDB, 'ÉXITO') !== false) {
            $response["success"] = true;
            $response["message"] = "Registro completado con éxito. Redirigiendo...";
        } else {
            $response["success"] = false;
            $response["message"] = $mensajeDB;
        }

    } catch (PDOException $e) {
        $response["message"] = "Error de Base de Datos: " . $e->getMessage();
    }
} else {
    $response["message"] = "Método no permitido.";
}

echo json_encode($response);
?>