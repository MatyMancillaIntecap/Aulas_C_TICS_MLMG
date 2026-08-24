<?php
session_start();
include 'conexion.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $correo = trim($_POST['correo']);
    $contrasena_ingresada = trim($_POST['contrasena']);

    // Buscamos al usuario por su correo utilizando la columna 'password' de tu tabla
    $stmt = $conexion->prepare("SELECT * FROM usuarios WHERE correo = ?");
    $stmt->execute([$correo]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        // Verificamos si la contraseña coincide (soporta texto plano o hash seguro)
        $password_bd = $usuario['password'] ?? $usuario['contrasena'] ?? '';
        
        $es_valida = false;
        if (password_get_info($password_bd)['algo'] != 0) {
            $es_valida = password_verify($contrasena_ingresada, $password_bd);
        } else {
            $es_valida = ($contrasena_ingresada === $password_bd);
        }

        if ($es_valida) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['nombre'] = $usuario['nombre'];
            $_SESSION['rol'] = $usuario['rol'] ?? 'catedratico';

            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Correo o contraseña incorrectos.";
        }
    } else {
        $error = "Correo o contraseña incorrectos.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Aulas Centro TIC'S</title>
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
            background: linear-gradient(135deg, #f1f5f9 0%, #cbd5e1 100%); 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            margin: 0; 
            padding: 15px;
            overflow-x: hidden;
        }
        .login-card { 
            background: #ffffff; 
            padding: 35px 25px; 
            border-radius: 16px; 
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08); 
            width: 100%; 
            max-width: 400px; 
            text-align: center;
        }
        .logo-container {
            margin-bottom: 15px;
        }
        .logo-container img {
            max-width: 160px;
            height: auto;
        }
        h2 { 
            margin: 0 0 25px 0; 
            color: #1e293b; 
            font-size: 18px; 
            font-weight: 600;
        }
        .form-group {
            text-align: left;
            margin-bottom: 15px;
        }
        label { 
            display: block; 
            margin-bottom: 6px; 
            font-weight: 600; 
            font-size: 13px; 
            color: #475569; 
        }
        input { 
            width: 100%; 
            padding: 12px 14px; 
            border: 1px solid #cbd5e1; 
            border-radius: 8px; 
            font-size: 14px; 
            transition: all 0.3s ease;
            background: #f8fafc;
        }
        input:focus {
            outline: none;
            border-color: #2563eb;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }
        button { 
            background: #2563eb; 
            color: white; 
            border: none; 
            padding: 12px; 
            width: 100%; 
            margin-top: 15px; 
            cursor: pointer; 
            border-radius: 8px; 
            font-weight: bold; 
            font-size: 15px; 
            transition: background 0.2s ease;
        }
        button:hover { 
            background: #1d4ed8; 
        }
        .error { 
            background: #fde8e8; 
            color: #991b1b; 
            padding: 10px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
            font-size: 13px; 
            font-weight: 600; 
            border: 1px solid #fecaca;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="logo-container">
            <img src="intecap_-02.png" alt="Logo INTECAP">
        </div>
        
        <h2>Aulas Centro TIC'S</h2>

        <?php if (!empty($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="correo">Correo electrónico:</label>
                <input type="email" id="correo" name="correo" required placeholder="ejemplo@correo.com" value="jorge@correo.com">
            </div>

            <div class="form-group">
                <label for="contrasena">Contraseña:</label>
                <input type="password" id="contrasena" name="contrasena" required placeholder="••••••••">
            </div>

            <button type="submit">Iniciar Sesión</button>
        </form>
    </div>

</body>
</html>