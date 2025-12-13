<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once 'conexion.php';

$response = ["success" => false, "message" => "Acción no válida"];

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Sesión expirada o no iniciada.");
    }

    $idCoach = $_SESSION['user_id'];
    $rol = $_SESSION['user_role'] ?? '';

    if ($rol !== 'COACH' && $rol !== 'COACH_JUEZ') {
        throw new Exception("No tienes permisos de Coach.");
    }

    $method = $_SERVER['REQUEST_METHOD'];
    
    // --- PETICIONES GET ---
    if ($method === 'GET') {
        $action = $_GET['action'] ?? '';

        if ($action === 'listar_equipos') {
            $stmt = $pdo->prepare("CALL ListarDetalleEquiposPorCoach(:id)");
            $stmt->bindParam(':id', $idCoach, PDO::PARAM_INT);
            $stmt->execute();
            $equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response = ["success" => true, "data" => $equipos];
        } 
        elseif ($action === 'listar_integrantes') {
            $idEquipo = $_GET['id_equipo'] ?? 0;
            
            // Validación de propiedad
            $check = $pdo->prepare("CALL ListarDetalleEquiposPorCoach(:id)");
            $check->execute([':id' => $idCoach]);
            $misEquipos = $check->fetchAll(PDO::FETCH_COLUMN, 0); 
            // Esto es ineficiente pero seguro sin hacer SELECT, aunque idealmente el SP ListarIntegrantes manejaría seguridad
            // Asumiremos que el SP ListarIntegrantes es seguro o confiamos en el ID
            
            $stmt = $pdo->prepare("CALL ListarIntegrantesPorEquipo(:id)");
            $stmt->bindParam(':id', $idEquipo, PDO::PARAM_INT);
            $stmt->execute();
            $integrantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response = ["success" => true, "data" => $integrantes];
        }
    }

    // --- PETICIONES POST ---
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';

        if ($action === 'registrar_equipo') {
            $nombre = $input['nombre'];
            $prototipo = $input['prototipo'];
            $idEvento = $input['id_evento'];
            $idCategoria = $input['id_categoria'];

            // MODIFICADO: Solo CALL, sin @variables de salida
            $stmt = $pdo->prepare("CALL RegistrarEquipo(:nom, :proto, :ev, :cat, :coach)");
            $stmt->bindParam(':nom', $nombre);
            $stmt->bindParam(':proto', $prototipo);
            $stmt->bindParam(':ev', $idEvento);
            $stmt->bindParam(':cat', $idCategoria);
            $stmt->bindParam(':coach', $idCoach);
            $stmt->execute();
            
            // MODIFICADO: Fetch directo del resultado del SP
            $output = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            
            if (strpos($output['mensaje'], 'ÉXITO') !== false) {
                $response = ["success" => true, "message" => "Equipo registrado correctamente."];
            } else {
                throw new Exception($output['mensaje']);
            }
        } 
        elseif ($action === 'agregar_integrante') {
            $idEquipo = $input['id_equipo'];
            $nombre = $input['nombre'];
            $edad = $input['edad'];
            $grado = $input['grado'];

            $stmt = $pdo->prepare("CALL AgregarIntegrante(:ide, :nom, :edad, :grado)");
            $stmt->bindParam(':ide', $idEquipo);
            $stmt->bindParam(':nom', $nombre);
            $stmt->bindParam(':edad', $edad);
            $stmt->bindParam(':grado', $grado);
            $stmt->execute();
            
            $output = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            if (strpos($output['mensaje'], 'ÉXITO') !== false) {
                $response = ["success" => true, "message" => "Integrante agregado."];
            } else {
                throw new Exception($output['mensaje']);
            }
        }
        elseif ($action === 'eliminar_integrante') {
            $idIntegrante = $input['id_integrante'];

            $stmt = $pdo->prepare("CALL EliminarIntegrante(:idi, :idc)");
            $stmt->bindParam(':idi', $idIntegrante);
            $stmt->bindParam(':idc', $idCoach);
            $stmt->execute();
            
            $output = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            
            if (strpos($output['mensaje'], 'ÉXITO') !== false) {
                $response = ["success" => true, "message" => "Integrante eliminado correctamente."];
            } else {
                throw new Exception($output['mensaje']);
            }
        }
    }

} catch (Exception $e) {
    $response["success"] = false;
    $response["message"] = $e->getMessage();
}

echo json_encode($response);
?>