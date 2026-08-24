<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$rol_usuario = $_SESSION['rol'];
$nombre_usuario = $_SESSION['nombre'];

// Obtenemos aulas
$stmt_aulas = $conexion->query("SELECT a.*, n.nombre as nivel_nombre FROM aulas a LEFT JOIN niveles n ON a.nivel_id = n.id");
$aulas = $stmt_aulas->fetchAll(PDO::FETCH_ASSOC);

$aula_seleccionada_id = isset($_GET['aula_id']) ? $_GET['aula_id'] : ($aulas[0]['id'] ?? 1);
$vista = isset($_GET['vista']) ? $_GET['vista'] : 'semanal'; // semanal o mensual

// Manejo de fechas y navegación
$fecha_actual = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
$timestamp = strtotime($fecha_actual);

if (isset($_GET['accion'])) {
    if ($_GET['accion'] == 'hoy') {
        $fecha_actual = date('Y-m-d');
        $timestamp = time();
    } elseif ($_GET['accion'] == 'prev') {
        $timestamp = ($vista === 'mensual') ? strtotime("-1 month", $timestamp) : strtotime("-1 week", $timestamp);
        $fecha_actual = date('Y-m-d', $timestamp);
    } elseif ($_GET['accion'] == 'next') {
        $timestamp = ($vista === 'mensual') ? strtotime("+1 month", $timestamp) : strtotime("+1 week", $timestamp);
        $fecha_actual = date('Y-m-d', $timestamp);
    }
}

// Consultamos reservas para el aula seleccionada
$stmt_reservas = $conexion->prepare("SELECT r.*, u.nombre as catedratico FROM reservas r JOIN usuarios u ON r.usuario_id = u.id WHERE r.aula_id = ?");
$stmt_reservas->execute([$aula_seleccionada_id]);
$reservas = $stmt_reservas->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario - Sistema de Aulas</title>
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
        
        .controls-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .control-group { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        select, input[type="date"] { padding: 8px 12px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 14px; }
        
        .nav-buttons { display: flex; gap: 5px; align-items: center; }
        .btn-nav { background: #e2e8f0; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px; color: #334155; text-decoration: none; }
        .btn-nav:hover { background: #cbd5e1; }
        .btn-today { background: #0284c7; color: white; }
        .btn-today:hover { background: #0369a1; }

        .calendar-container { overflow-x: auto; margin-top: 15px; }
        .calendar-grid { display: grid; grid-template-columns: 80px repeat(7, 1fr); min-width: 800px; border-top: 1px solid #e2e8f0; border-left: 1px solid #e2e8f0; }
        .monthly-grid { display: grid; grid-template-columns: repeat(7, 1fr); min-width: 800px; border-top: 1px solid #e2e8f0; border-left: 1px solid #e2e8f0; }
        
        .calendar-header-cell, .calendar-time-cell, .calendar-slot, .month-cell { border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; padding: 8px; font-size: 12px; text-align: center; }
        .calendar-header-cell { background-color: #f1f5f9; font-weight: bold; color: #1e293b; }
        .calendar-time-cell { background-color: #f8fafc; color: #64748b; font-weight: 600; display: flex; align-items: center; justify-content: center; }
        .calendar-slot { background: white; min-height: 55px; position: relative; }
        .month-cell { background: white; min-height: 100px; text-align: left; vertical-align: top; }
        
        /* Colores según modalidad */
        .reserva-block { color: white; padding: 6px; border-radius: 4px; font-size: 11px; text-align: left; height: 100%; width: 100%; box-shadow: 0 1px 2px rgba(0,0,0,0.1); margin-bottom: 4px; }
        .reserva-block.Presencial { background-color: #ef4444; } /* Rojo */
        .reserva-block.Síncrona { background-color: #eab308; color: #1e293b; font-weight: 600; } /* Amarillo */
        .reserva-block.Asíncrona { background-color: #22c55e; } /* Verde */

        .legend { display: flex; gap: 15px; margin-top: 15px; font-size: 13px; align-items: center; flex-wrap: wrap; }
        .legend-item { display: flex; align-items: center; gap: 5px; }
        .color-box { width: 14px; height: 14px; border-radius: 3px; }
    </style>
</head>
<body>

    <header>
        <div class="logo-area">
            <img src="Intecap_Logo.png" alt="Logo INTECAP">
            <h1>Sistema de Reservas</h1>
        </div>
        <nav>
            <a href="index.php" class="active">📅 Calendario</a>
            <a href="reservar.php">➕ Registrar Reserva</a>
            <a href="disponibilidad.php">🔍 Buscar Disponibilidad</a>
            <a href="aulas.php">🏛️ Aulas y Recursos</a>
            <?php if ($rol_usuario === 'administrador'): ?>
                <a href="admin_panel.php" style="color: #d97706; font-weight: bold;">⚙️ Panel Admin</a>
            <?php endif; ?>
        </nav>
        <div>
            <span class="user-info">Hola, <?= htmlspecialchars($nombre_usuario) ?></span>
            <a href="logout.php" class="btn-logout">Salir</a>
        </div>
    </header>

    <div class="container">
        <div class="card">
            <h2>Calendario de Aulas</h2>
            
            <form method="GET" action="index.php" class="controls-bar">
                <div class="control-group">
                    <label for="aula_id"><strong>Aula:</strong></label>
                    <select name="aula_id" id="aula_id" onchange="this.form.submit()">
                        <?php foreach ($aulas as $a): ?>
                            <option value="<?= $a['id'] ?>" <?= $a['id'] == $aula_seleccionada_id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($a['codigo']) ?> <?= $a['es_aula_magna'] ? '(Aula Magna)' : '- Nivel ' . $a['nivel_id'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="vista" style="margin-left: 15px;"><strong>Vista:</strong></label>
                    <select name="vista" id="vista" onchange="this.form.submit()">
                        <option value="semanal" <?= $vista === 'semanal' ? 'selected' : '' ?>>Semanal</option>
                        <option value="mensual" <?= $vista === 'mensual' ? 'selected' : '' ?>>Mensual</option>
                    </select>
                </div>

                <div class="control-group">
                    <div class="nav-buttons">
                        <a href="index.php?aula_id=<?= $aula_seleccionada_id ?>&vista=<?= $vista ?>&accion=prev&fecha=<?= $fecha_actual ?>" class="btn-nav">◀ Anterior</a>
                        <a href="index.php?aula_id=<?= $aula_seleccionada_id ?>&vista=<?= $vista ?>&accion=hoy" class="btn-nav btn-today">Hoy</a>
                        <a href="index.php?aula_id=<?= $aula_seleccionada_id ?>&vista=<?= $vista ?>&accion=next&fecha=<?= $fecha_actual ?>" class="btn-nav">Siguiente ▶</a>
                    </div>
                    <input type="date" name="fecha" value="<?= $fecha_actual ?>" onchange="this.form.submit()">
                </div>
            </form>

            <div class="legend">
                <span><strong>Modalidades:</strong></span>
                <div class="legend-item"><div class="color-box" style="background: #ef4444;"></div> Presencial</div>
                <div class="legend-item"><div class="color-box" style="background: #eab308;"></div> Síncrona</div>
                <div class="legend-item"><div class="color-box" style="background: #22c55e;"></div> Asíncrona</div>
            </div>

            <div class="calendar-container">
                <?php if ($vista === 'semanal'): ?>
                    <!-- VISTA SEMANAL -->
                    <div class="calendar-grid">
                        <div class="calendar-header-cell">Hora</div>
                        <div class="calendar-header-cell">Lunes</div>
                        <div class="calendar-header-cell">Martes</div>
                        <div class="calendar-header-cell">Miércoles</div>
                        <div class="calendar-header-cell">Jueves</div>
                        <div class="calendar-header-cell">Viernes</div>
                        <div class="calendar-header-cell">Sábado</div>
                        <div class="calendar-header-cell">Domingo</div>

                        <?php 
                        $dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
                        for ($hora = 6; $hora < 22; $hora++): 
                            $hora_actual_str = str_pad($hora, 2, "0", STR_PAD_LEFT) . ":00:00";
                            $hora_etiqueta = str_pad($hora, 2, "0", STR_PAD_LEFT) . ":00";
                        ?>
                            <div class="calendar-time-cell"><?= $hora_etiqueta ?></div>
                            
                            <?php foreach ($dias as $dia): ?>
                                <div class="calendar-slot">
                                    <?php 
                                    foreach ($reservas as $res) {
                                        if ($res['dia_semana'] === $dia) {
                                            if ($hora_actual_str >= $res['hora_inicio'] && $hora_actual_str < $res['hora_fin']) {
                                                echo '<div class="reserva-block ' . htmlspecialchars($res['modalidad']) . '">';
                                                echo '<strong>' . htmlspecialchars($res['materia']) . '</strong><br>';
                                                echo '<small>' . substr($res['hora_inicio'],0,5) . '-' . substr($res['hora_fin'],0,5) . '</small><br>';
                                                echo '<small>Prof. ' . htmlspecialchars($res['catedratico']) . '</small>';
                                                echo '</div>';
                                            }
                                        }
                                    }
                                    ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endfor; ?>
                    </div>
                <?php else: ?>
                    <!-- VISTA MENSUAL -->
                    <?php 
                    $num_dias_mes = date('t', $timestamp);
                    $mes_nombre = date('F Y', $timestamp);
                    $primer_dia_mes_w = date('w', strtotime(date('Y-m-01', $timestamp))); // 0 (domingo) a 6 (sábado)
                    // Ajustamos para que lunes sea el inicio (0)
                    $primer_dia_mes = ($primer_dia_mes_w == 0) ? 6 : $primer_dia_mes_w - 1;
                    ?>
                    <h3 style="margin-bottom: 15px; color: #1e3a8a; text-align: center;"><?= $mes_nombre ?></h3>
                    
                    <div class="monthly-grid" style="grid-template-columns: repeat(7, 1fr);">
                        <div class="calendar-header-cell">Lunes</div>
                        <div class="calendar-header-cell">Martes</div>
                        <div class="calendar-header-cell">Miércoles</div>
                        <div class="calendar-header-cell">Jueves</div>
                        <div class="calendar-header-cell">Viernes</div>
                        <div class="calendar-header-cell">Sábado</div>
                        <div class="calendar-header-cell">Domingo</div>

                        <?php
                        // Celdas vacías antes del primer día del mes
                        for ($i = 0; $i < $primer_dia_mes; $i++) {
                            echo '<div class="month-cell" style="background: #f8fafc;"></div>';
                        }

                        // Días del mes
                        for ($dia_n = 1; $dia_n <= $num_dias_mes; $dia_n++) {
                            // Determinamos el día de la semana correspondiente
                            $fecha_iter = date('Y-m', $timestamp) . '-' . str_pad($dia_n, 2, '0', STR_PAD_LEFT);
                            $w_dia = date('N', strtotime($fecha_iter)); // 1 (Lunes) a 7 (Domingo)
                            $nombres_dias_map = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
                            $nombre_dia_str = $nombres_dias_map[$w_dia];

                            echo '<div class="month-cell">';
                            echo '<strong style="font-size: 13px; color: #1e293b;">' . $dia_n . '</strong><hr style="margin: 4px 0; border:0; border-top:1px solid #e2e8f0;">';

                            // Mostramos reservas que correspondan a este día de la semana
                            foreach ($reservas as $res) {
                                if ($res['dia_semana'] === $nombre_dia_str) {
                                    echo '<div class="reserva-block ' . htmlspecialchars($res['modalidad']) . '">';
                                    echo '<strong>' . htmlspecialchars($res['materia']) . '</strong><br>';
                                    echo '<small>' . substr($res['hora_inicio'],0,5) . '-' . substr($res['hora_fin'],0,5) . '</small>';
                                    echo '</div>';
                                }
                            }
                            echo '</div>';
                        }
                        ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

</body>
</html>