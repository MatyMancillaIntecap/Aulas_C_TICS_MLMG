<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Consultamos las aulas
$stmt_aulas = $conexion->query("SELECT a.*, n.nombre as nivel_nombre FROM aulas a LEFT JOIN niveles n ON a.nivel_id = n.id");
$aulas = $stmt_aulas->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aulas y Equipamiento - Sistema de Aulas</title>
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
        h2 { color: #1e3a8a; margin-bottom: 20px; }
        
        .aulas-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        .aula-box { background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
        .aula-box h3 { color: #0f172a; font-size: 18px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; }
        .badge { background: #38bdf8; color: white; padding: 3px 8px; border-radius: 4px; font-size: 11px; }
        .badge.magna { background: #ef4444; }
        .info-p { font-size: 13px; color: #64748b; margin-bottom: 12px; }
        .recursos-list { background: #f8fafc; padding: 10px; border-radius: 6px; font-size: 13px; }
        .recursos-list strong { color: #334155; display: block; margin-bottom: 5px; }
    </style>
</head>
<body>

    <header>
        <div class="logo-area">
            <img src="logo_intecap.png" alt="Logo INTECAP">
            <h1>Sistema de Reservas</h1>
        </div>
        <nav>
            <a href="index.php">📅 Calendario</a>
            <a href="reservar.php">➕ Registrar Reserva</a>
            <a href="disponibilidad.php">🔍 Buscar Disponibilidad</a>
            <a href="aulas.php" class="active">🏛️ Aulas y Recursos</a>
            <?php if ($_SESSION['rol'] === 'administrador'): ?>
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
            <h2>Catálogo de Aulas y Equipamiento Tecnológico</h2>
            <p style="font-size: 13px; color: #64748b; margin-bottom: 25px;">
                Listado general de los salones disponibles en la institución y los recursos asignados a cada uno.
            </p>

            <div class="aulas-grid">
                <?php foreach ($aulas as $aula): ?>
                    <div class="aula-box">
                        <h3>
                            Aula <?= htmlspecialchars($aula['codigo']) ?>
                            <?php if ($aula['es_aula_magna']): ?>
                                <span class="badge magna">Aula Magna</span>
                            <?php else: ?>
                                <span class="badge">Nivel <?= $aula['nivel_id'] ?></span>
                            <?php endif; ?>
                        </h3>
                        <p class="info-p">Capacidad máxima: <strong><?= $aula['capacidad'] ?> personas</strong></p>

                        <div class="recursos-list">
                            <strong>Equipamiento disponible:</strong>
                            <ul>
                                <?php
                                // Consultamos los recursos específicos de esta aula mediante la tabla intermedia aula_recurso
                                $stmt_rec = $conexion->prepare("SELECT r.nombre, ar.cantidad FROM aula_recurso ar JOIN recursos r ON ar.recurso_id = r.id WHERE ar.aula_id = ?");
                                $stmt_rec->execute([$aula['id']]);
                                $recursos = $stmt_rec->fetchAll(PDO::FETCH_ASSOC);

                                if (count($recursos) > 0) {
                                    foreach ($recursos as $rec) {
                                        echo '<li>' . $rec['cantidad'] . 'x ' . htmlspecialchars($rec['nombre']) . '</li>';
                                    }
                                } else {
                                    echo '<li style="color: #94a3b8;">Sin recursos registrados</li>';
                                }
                                ?>
                            </ul>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

</body>
</html>
