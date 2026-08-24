<?php
session_start();
include 'conexion.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $correo = trim($_POST['correo']);
    $password = trim($_POST['password']);

    if (!empty($correo) && !empty($password)) {
        // Buscamos al usuario por correo en la base de datos
        $stmt = $conexion->prepare("SELECT * FROM usuarios WHERE correo = ?");
        $stmt->execute([$correo]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        // Validamos la contraseña (soporta texto plano o hash)
        if ($usuario && ($password === $usuario['password'] || password_verify($password, $usuario['password']))) {
            
            // Creamos las variables de sesión (el gafete digital de acceso)
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['nombre'] = $usuario['nombre'];
            $_SESSION['rol'] = $usuario['rol'];

            // Redirigimos según el rol del usuario
            if ($usuario['rol'] === 'administrador') {
                header("Location: admin_panel.php");
            } else {
                header("Location: index.php");
            }
            exit();
        } else {
            $error = "Correo o contraseña incorrectos.";
        }
    } else {
        $error = "Por favor completa todos los campos.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Sistema de Reservas de Aulas</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f0f4f8; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
        .login-card { background: white; padding: 40px 30px; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.08); width: 100%; max-width: 420px; text-align: center; }
        .logo-container { margin-bottom: 25px; }
        .logo-container img { max-width: 180px; height: auto; }
        h2 { color: #1e3a8a; margin-bottom: 25px; font-size: 22px; }
        .form-group { margin-bottom: 20px; text-align: left; }
        label { display: block; margin-bottom: 8px; color: #475569; font-weight: 600; font-size: 14px; }
        input { width: 100%; padding: 12px 15px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; transition: border-color 0.2s; }
        input:focus { border-color: #0284c7; outline: none; box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.1); }
        button { width: 100%; padding: 12px; background-color: #005f73; color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; transition: background 0.3s; }
        button:hover { background-color: #0a9396; }
        .error { background-color: #fee2e2; color: #991b1b; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; border: 1px solid #fecaca; }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="logo-container">
            <!-- Mostramos el logo de INTECAP -->
            <img src="Intecap_Logo.png" alt="Logo INTECAP">
        </div>
        
        <h2>Sistema de Reservas de Aulas</h2>
        
        <?php if (!empty($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="correo">Correo Electrónico</label>
                <input type="email" id="correo" name="correo" required placeholder="ejemplo@intecap.edu.gt">
            </div>
            
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required placeholder="••••••••">
            </div>

            <button type="submit">Iniciar Sesión</button>
        </form>
    </div>

</body>
</html>