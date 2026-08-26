<?php
session_start();
include 'conexion.php';

// Verificamos que el usuario haya iniciado sesión
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$mensaje = "";

// Lógica para anular/eliminar una reserva
if (isset($_GET['eliminar_id'])) {
    $reserva_id = $_GET['eliminar_id'];
    
    // Verificamos si la reserva pertenece a un grupo recurrente para dar la opción de borrar todo el bloque
    $stmt_g = $conexion->prepare("SELECT grupo_reserva_id FROM reservas WHERE id = ? AND usuario_id = ?");
    $stmt_g->execute([$reserva_id, $usuario_id]);
    $res = $stmt_g->fetch(PDO::FETCH_ASSOC);

    if ($res) {
        $grupo_id = $res['grupo_reserva_id'];
        if (!empty($grupo_id) && isset($_GET['tipo']) && $_GET['tipo'] === 'grupo') {
            // Borramos toda la recurrencia del grupo
            $stmt_del = $conexion->prepare("DELETE FROM reservas WHERE grupo_reserva_id = ? AND usuario_id = ?");
            $stmt_del->execute([$grupo_id, $usuario_id]);
            $mensaje = "¡Se ha anulado toda la serie recurrente de reservas con éxito!";
        } else {
            // Borramos únicamente esta reserva individual
            $stmt_del = $conexion->prepare("DELETE FROM reservas WHERE id = ? AND usuario_id = ?");
            $stmt_del->execute([$reserva_id, $usuario_id]);
            $mensaje = "¡Reserva anulada con éxito!";
        }
    }
}

// Consultamos todas las reservas hechas por este usuario, unidas con el nombre del aula
$sql = "SELECT r.*, a.codigo as aula_codigo, a.es_aula_magna, n.nombre as nivel_nombre 
        FROM reservas r 
        JOIN aulas a ON r.aula_id = a.id 
        LEFT JOIN niveles n ON a.nivel_id = n.id 
        WHERE r.usuario_id = ? 
        ORDER BY r.fecha DESC, r.hora_inicio ASC";
$stmt = $conexion->prepare($sql);
$stmt->execute([$usuario_id]);
$mis_reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Reservas - Sistema de Aulas</title>
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
        .card { background: white; border-radius: 10px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); padding: 25px; margin-bottom: 20px; }
        h2 { color: #1e3a8a; margin-bottom: 15px; }

        table { width: 100%; border-collapse: collapse; margin-top: 15px; background: white; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
        th { background-color: #f1f5f9; color: #1e293b; font-weight: bold; }
        tr:hover { background-color: #f8fafc; }
        
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; color: white; }
        .badge.Presencial { background-color: #ef4444; }
        .badge.Síncrona { background-color: #eab308; color: #1e293b; }
        .badge.Asíncrona { background-color: #22c55e; }

        .btn-cancel { background-color: #ef4444; color: white; padding: 6px 10px; border-radius: 4px; text-decoration: none; font-size: 12px; font-weight: bold; display: inline-block; margin-right: 5px; }
        .btn-cancel:hover { background-color: #dc2626; }
        .btn-cancel-group { background-color: #b91c1c; }

        .alert-success { background-color: #d1fae5; color: #065f46; padding: 12px; border-radius: 6px; margin-bottom: 15px; border: 1px solid #a7f3d0; font-size: 14px; }
    </style>
</head>
<body>

    <header>
        <div class="logo-area">
            <img src="Intecap_Logo.png" alt="Logo INTECAP">
            <h1>Sistema de Reservas</h1>
        </div>




        <nav>
    <a href="index.php">📅 Calendario</a>
    <a href="reservas.php">➕ Registrar Reservas</a>
    <a href="mis_reservas.php">📋 Mis Reservas</a>
    <a href="disponibilidad.php">🔍 Buscar Disponibilidad</a>
    <a href="aulas.php">🏛️ Aulas y Recursos</a>
    <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'administrador'): ?>
        <a href="admin_panel.php" style="color: #d97706; font-weight: bold;">⚙️ Panel Admin</a>
    <?php endif; ?>
</nav>



        <div>
            <span class="user-info">Hola, <?= htmlspecialchars($_SESSION['nombre']) ?></span>
            <a href="logout.php" class="btn-logout">Salir</a>
        </div>
    </header>

    <div class="container">
        <div class="card">
            <h2>Mis Reservas Registradas</h2>
            <p style="font-size: 13px; color: #64748b; margin-bottom: 20px;">
                Aquí puedes visualizar el listado histórico de todas tus clases programadas y anular las que necesites.
            </p>

            <?php if (!empty($mensaje)): ?>
                <div class="alert-success"><?= htmlspecialchars($mensaje) ?></div>
            <?php endif; ?>

            <table>
                <thead>
                    <tr>
                        <th>Aula</th>
                        <th>Materia</th>
                        <th>Día / Fecha</th>
                        <th>Horario</th>
                        <th>Modalidad</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($mis_reservas) > 0): ?>
                        <?php foreach ($mis_reservas as $res): ?>
                            <tr>
                                <td><strong>Aula <?= htmlspecialchars($res['aula_codigo']) ?></strong></td>
                                <td><?= htmlspecialchars($res['materia']) ?></td>
                                <td>
                                    <?= htmlspecialchars($res['dia_semana']) ?><br>
                                    <small style="color: #64748b;"><?= htmlspecialchars($res['fecha']) ?></small>
                                </td>
                                <td><?= substr($res['hora_inicio'], 0, 5) ?> - <?= substr($res['hora_fin'], 0, 5) ?></td>
                                <td><span class="badge <?= htmlspecialchars($res['modalidad']) ?>"><?= htmlspecialchars($res['modalidad']) ?></span></td>
                                <td>


                                <a href="editar_reserva.php?id=<?= $res['id'] ?>" class="btn-edit" style="background-color: #0284c7; color: white; padding: 6px 10px; border-radius: 4px; text-decoration: none; font-size: 12px; font-weight: bold; display: inline-block; margin-right: 5px;">Editar</a>
                                    <a href="mis_reservas.php?eliminar_id=<?= $res['id'] ?>" class="btn-cancel" onclick="return confirm('¿Estás seguro de anular esta reserva individual?')">Anular</a>
                                    <?php if (!empty($res['grupo_reserva_id'])): ?>
                                        <a href="mis_reservas.php?eliminar_id=<?= $res['id'] ?>&tipo=grupo" class="btn-cancel btn-cancel-group" onclick="return confirm('¿Estás seguro de anular TODA la serie recurrente de este bloque?')">Anular Serie</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: #94a3b8; padding: 30px;">No tienes reservas registradas en este momento.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>