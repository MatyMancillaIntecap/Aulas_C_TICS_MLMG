<?php
session_start();
include 'conexion.php';

// Si no ha iniciado sesión, lo mandamos al login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$mensaje = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $password_actual = $_POST['password_actual'];
    $password_nueva = $_POST['password_nueva'];
    $password_confirmar = $_POST['password_confirmar'];
    $usuario_id = $_SESSION['usuario_id'];

    if (empty($password_actual) || empty($password_nueva) || empty($password_confirmar)) {
        $error = "Todos los campos son obligatorios.";
    } elseif ($password_nueva !== $password_confirmar) {
        $error = "Las nuevas contraseñas no coinciden.";
    } else {
        // Consultamos la contraseña actual en la base de datos
        $stmt = $conexion->prepare("SELECT password FROM usuarios WHERE id = ?");
        $stmt->execute([$usuario_id]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        // password_verify compara la contraseña escrita con el hash de la base de datos
        if ($usuario && password_verify($password_actual, $usuario['password'])) {
            // Si coincide, encriptamos la nueva contraseña
            $nuevo_hash = password_hash($password_nueva, PASSWORD_DEFAULT);
            
            $stmt_update = $conexion->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
            if ($stmt_update->execute([$nuevo_hash, $usuario_id])) {
                $mensaje = "¡Contraseña actualizada con éxito!";
            } else {
                $error = "Error al actualizar la contraseña en la base de datos.";
            }
        } else {
            $error = "La contraseña actual es incorrecta.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cambiar Contraseña - Sistema de Reservas</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f8fafc; color: #334155; }
        header { background: white; border-bottom: 1px solid #e2e8f0; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .container { max-width: 500px; margin: 50px auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        h2 { color: #1e3a8a; margin-bottom: 20px; font-size: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #475569; }
        input[type="password"] { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; }
        .btn { background-color: #005f73; color: white; border: none; padding: 10px 18px; border-radius: 6px; font-size: 14px; font-weight: bold; cursor: pointer; width: 100%; }
        .btn:hover { background-color: #0a9396; }
        .alert-success { background-color: #d1fae5; color: #065f46; padding: 12px; border-radius: 6px; margin-bottom: 15px; font-size: 13px; }
        .alert-error { background-color: #fee2e2; color: #991b1b; padding: 12px; border-radius: 6px; margin-bottom: 15px; font-size: 13px; }
        .back-link { display: block; text-align: center; margin-top: 15px; color: #0284c7; text-decoration: none; font-size: 14px; font-weight: 600; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>

    <div class="container">
        <h2>Cambiar mi Contraseña</h2>

        <?php if (!empty($mensaje)): ?>
            <div class="alert-success"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="cambiar_password.php" method="POST">
            <div class="form-group">
                <label for="password_actual">Contraseña Actual (o Temporal):</label>
                <input type="password" id="password_actual" name="password_actual" required>
            </div>
            <div class="form-group">
                <label for="password_nueva">Nueva Contraseña:</label>
                <input type="password" id="password_nueva" name="password_nueva" required>
            </div>
            <div class="form-group">
                <label for="password_confirmar">Confirmar Nueva Contraseña:</label>
                <input type="password" id="password_confirmar" name="password_confirmar" required>
            </div>
            <button type="submit" class="btn">Actualizar Contraseña</button>
        </form>

        <a href="index.php" class="back-link">← Volver al Sistema</a>
    </div>

</body>
</html>