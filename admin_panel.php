<?php
session_start();
include 'conexion.php';

// Verificamos que haya iniciado sesión y que sea estrictamente Administrador
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'administrador') {
    header("Location: login.php");
    exit();
}

$mensaje = "";
$error = "";

// Lógica para agregar un nuevo catedrático
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear_catedratico') {
    $nombre = trim($_POST['nombre']);
    $correo = trim($_POST['correo']);
    $password = trim($_POST['password']);
    $rol = 'catedratico'; // Por defecto los crea como catedráticos

    if (!empty($nombre) && !empty($correo) && !empty($password)) {
        // Verificamos si el correo ya existe
        $stmt_check = $conexion->prepare("SELECT id FROM usuarios WHERE correo = ?");
        $stmt_check->execute([$correo]);
        if ($stmt_check->fetch()) {
            $error = "El correo electrónico ya está registrado en el sistema.";
        } else {
            // Guardamos la contraseña (puedes usar hash o texto plano según prefieras)
            $stmt_insert = $conexion->prepare("INSERT INTO usuarios (nombre, correo, password, rol) VALUES (?, ?, ?, ?)");
            if ($stmt_insert->execute([$nombre, $correo, $password, $rol])) {
                $mensaje = "¡Catedrático registrado con éxito!";
            } else {
                $error = "Error al registrar al catedrático.";
            }
        }
    } else {
        $error = "Por favor completa todos los campos para el nuevo usuario.";
    }
}

// Lógica para eliminar un catedrático
if (isset($_GET['eliminar'])) {
    $id_a_eliminar = $_GET['eliminar'];
    // Evitamos que el admin se borre a sí mismo
    if ($id_a_eliminar != $_SESSION['usuario_id']) {
        $stmt_del = $conexion->prepare("DELETE FROM usuarios WHERE id = ? AND rol = 'catedratico'");
        $stmt_del->execute([$id_a_eliminar]);
        header("Location: admin_panel.php");
        exit();
    }
}

// Estadísticas rápidas para el dashboard
$total_aulas = $conexion->query("SELECT COUNT(*) FROM aulas")->fetchColumn();
$total_reservas = $conexion->query("SELECT COUNT(*) FROM reservas")->fetchColumn();
$total_catedraticos = $conexion->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'catedratico'")->fetchColumn();

// Listado de catedráticos actuales
$catedraticos = $conexion->query("SELECT * FROM usuarios WHERE rol = 'catedratico'")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - Sistema de Aulas</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f8fafc; color: #334155; }
        header { background: white; border-bottom: 1px solid #e2e8f0; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .logo-area { display: flex; align-items: center; gap: 15px; }
        .logo-area img { height: 40px; }
        .logo-area h1 { font-size: 18px; color: #1e3a8a; }
        nav { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        nav a { text-decoration: none; color: #475569; font-weight: 600; font-size: 14px; padding: 8px 12px; border-radius: 6px; transition: background 0.2s; }
        nav a:hover, nav a.active { background-color: #e0f2fe; color: #0284c7; }
        .user-info { font-size: 14px; color: #64748b; font-weight: 500; }
        .btn-logout { background-color: #fee2e2; color: #991b1b; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: bold; }
        
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        
        /* Tarjetas de Estadísticas (KPIs) */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border-left: 4px solid #0284c7; }
        .stat-card.green { border-left-color: #22c55e; }
        .stat-card.orange { border-left-color: #f59e0b; }
        .stat-card h3 { font-size: 14px; color: #64748b; margin-bottom: 5px; }
        .stat-card .number { font-size: 28px; font-weight: bold; color: #1e293b; }

        .card { background: white; border-radius: 10px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); padding: 25px; margin-bottom: 25px; }
        h2 { color: #1e3a8a; margin-bottom: 15px; }
        
        /* Formularios y Tablas */
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 15px; }
        .form-group { margin-bottom: 10px; }
        label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #475569; }
        input { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; }
        button { background-color: #005f73; color: white; border: none; padding: 10px 20px; border-radius: 6px; font-size: 14px; font-weight: bold; cursor: pointer; transition: background 0.2s; }
        button:hover { background-color: #0a9396; }

        table { width: 100%; border-collapse: collapse; margin-top: 15px; background: white; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
        th { background-color: #f1f5f9; color: #1e293b; font-weight: bold; }
        tr:hover { background-color: #f8fafc; }
        .btn-delete { background-color: #ef4444; color: white; padding: 6px 10px; border-radius: 4px; text-decoration: none; font-size: 12px; font-weight: bold; }
        .btn-delete:hover { background-color: #dc2626; }
        
        .alert-success { background-color: #d1fae5; color: #065f46; padding: 12px; border-radius: 6px; margin-bottom: 15px; border: 1px solid #a7f3d0; font-size: 14px; }
        .alert-error { background-color: #fee2e2; color: #991b1b; padding: 12px; border-radius: 6px; margin-bottom: 15px; border: 1px solid #fecaca; font-size: 14px; }
    </style>
</head>
<body>

    <header>
        <div class="logo-area">
            <img src="Intecap_Logo.png" alt="Logo INTECAP">
            <h1>Panel de Administración</h1>
        </div>
        <nav>
            <a href="index.php">📅 Calendario</a>
            <a href="reservar.php">➕ Registrar Reserva</a>
            <a href="disponibilidad.php">🔍 Buscar Disponibilidad</a>
            <a href="aulas.php">🏛️ Aulas y Recursos</a>
            <a href="admin_panel.php" class="active" style="color: #d97706; font-weight: bold;">⚙️ Panel Admin</a>
        </nav>
        <div>
            <span class="user-info">Admin: <?= htmlspecialchars($_SESSION['nombre']) ?></span>
            <a href="logout.php" class="btn-logout">Salir</a>
        </div>
    </header>

    <div class="container">
        <!-- Tarjetas de Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total de Aulas Registradas</h3>
                <div class="number"><?= $total_aulas ?></div>
            </div>
            <div class="stat-card green">
                <h3>Reservas Activas</h3>
                <div class="number"><?= $total_reservas ?></div>
            </div>
            <div class="stat-card orange">
                <h3>Catedráticos en el Sistema</h3>
                <div class="number"><?= $total_catedraticos ?></div>
            </div>
        </div>

        <!-- Sección de Gestión de Catedráticos -->
        <div class="card">
            <h2>Gestión de Catedráticos</h2>
            <p style="font-size: 13px; color: #64748b; margin-bottom: 20px;">
                Como administrador, puedes agregar nuevos profesores al sistema o dar de baja a los existentes.
            </p>

            <?php if (!empty($mensaje)): ?>
                <div class="alert-success"><?= htmlspecialchars($mensaje) ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Formulario para agregar catedrático -->
            <form action="admin_panel.php" method="POST" style="margin-bottom: 30px; background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0;">
                <input type="hidden" name="accion" value="crear_catedratico">
                <h3 style="font-size: 15px; color: #1e3a8a; margin-bottom: 15px;">➕ Registrar Nuevo Catedrático</h3>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nombre">Nombre Completo:</label>
                        <input type="text" id="nombre" name="nombre" required placeholder="Ej. Carlos Mendoza">
                    </div>
                    <div class="form-group">
                        <label for="correo">Correo Electrónico:</label>
                        <input type="email" id="correo" name="correo" required placeholder="carlos@intecap.edu.gt">
                    </div>
                    <div class="form-group">
                        <label for="password">Contraseña Temporal:</label>
                        <input type="password" id="password" name="password" required placeholder="••••••••">
                    </div>
                </div>
                <button type="submit">Guardar Catedrático</button>
            </form>

            <!-- Listado de Catedráticos -->
            <h3 style="font-size: 15px; color: #1e293b; margin-bottom: 10px;">📋 Profesores Registrados</h3>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Correo Electrónico</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($catedraticos) > 0): ?>
                        <?php foreach ($catedraticos as $cat): ?>
                            <tr>
                                <td><?= $cat['id'] ?></td>
                                <td><strong><?= htmlspecialchars($cat['nombre']) ?></strong></td>
                                <td><?= htmlspecialchars($cat['correo']) ?></td>
                                <td>
                                    <a href="admin_panel.php?eliminar=<?= $cat['id'] ?>" class="btn-delete" onclick="return confirm('¿Estás seguro de eliminar a este catedrático del sistema?')">Eliminar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: #94a3b8;">No hay catedráticos registrados además del administrador.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>