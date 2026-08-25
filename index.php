<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$stmt_aulas = $conexion->query("SELECT * FROM aulas");
$aulas = $stmt_aulas->fetchAll(PDO::FETCH_ASSOC);

$aula_id_actual = isset($_GET['aula_id']) ? $_GET['aula_id'] : ($aulas[0]['id'] ?? 1);
$vista = isset($_GET['vista']) ? $_GET['vista'] : 'semanal';
$fecha_actual = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

$timestamp = strtotime($fecha_actual);

// Lógica para los botones de Anterior y Siguiente
if ($vista === 'semanal') {
    $fecha_anterior = date('Y-m-d', strtotime('-1 week', $timestamp));
    $fecha_siguiente = date('Y-m-d', strtotime('+1 week', $timestamp));
    
    $inicio_semana = strtotime('monday this week', $timestamp);
    if (date('l', $timestamp) == 'Monday') {
        $inicio_semana = $timestamp;
    }
    $dias_calendario = [];
    for ($i = 0; $i < 7; $i++) {
        $dias_calendario[] = date('Y-m-d', strtotime("+$i days", $inicio_semana));
    }
} else {
    // Vista Mensual: sumamos/restamos 1 mes exacto
    $fecha_anterior = date('Y-m-d', strtotime('-1 month', $timestamp));
    $fecha_siguiente = date('Y-m-d', strtotime('+1 month', $timestamp));

    $mes = date('m', $timestamp);
    $anio = date('Y', $timestamp);
    $primer_dia_mes = strtotime("$anio-$mes-01");
    $ultimo_dia_mes = strtotime("last day of $anio-$mes");
    
    $inicio_grid = strtotime('monday this week', $primer_dia_mes);
    if (date('l', $primer_dia_mes) == 'Monday') {
        $inicio_grid = $primer_dia_mes;
    }
    $fin_grid = strtotime('sunday this week', $ultimo_dia_mes);
    if (date('l', $ultimo_dia_mes) == 'Sunday') {
        $fin_grid = $ultimo_dia_mes;
    }

    $dias_calendario = [];
    $curr = $inicio_grid;
    while ($curr <= $fin_grid) {
        $dias_calendario[] = date('Y-m-d', $curr);
        $curr = strtotime('+1 day', $curr);
    }
}

$fecha_min = $dias_calendario[0];
$fecha_max = end($dias_calendario);

$stmt_res = $conexion->prepare("SELECT r.*, u.nombre as profesor_nombre FROM reservas r JOIN usuarios u ON r.usuario_id = u.id WHERE r.aula_id = ? AND r.fecha BETWEEN ? AND ? ORDER BY r.hora_inicio ASC");
$stmt_res->execute([$aula_id_actual, $fecha_min, $fecha_max]);
$reservas_raw = $stmt_res->fetchAll(PDO::FETCH_ASSOC);

$reservas_por_fecha = [];
foreach ($reservas_raw as $res) {
    $reservas_por_fecha[$res['fecha']][] = $res;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario - Sistema de Reservas</title>
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

        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        .card { background: white; border-radius: 10px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); padding: 25px; margin-bottom: 20px; }
        
        .controls-bar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px; }
        .control-group { display: flex; align-items: center; gap: 10px; }
        select, input[type="date"] { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; background: white; }
        
        .btn { background: #0284c7; color: white; border: none; padding: 8px 14px; border-radius: 6px; font-size: 14px; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; }
        .btn:hover { background: #0369a1; }
        .btn-secondary { background: #64748b; }
        .btn-secondary:hover { background: #475569; }

        .legend { display: flex; gap: 15px; align-items: center; font-size: 13px; margin-bottom: 15px; }
        .legend-item { display: flex; align-items: center; gap: 5px; }
        .dot { width: 12px; height: 12px; border-radius: 3px; }
        .dot.Presencial { background: #ef4444; }
        .dot.Síncrona { background: #eab308; }
        .dot.Asíncrona { background: #22c55e; }

        .calendar-table { width: 100%; border-collapse: collapse; background: white; }
        .calendar-table th { background: #f1f5f9; color: #1e293b; padding: 12px; text-align: center; border: 1px solid #e2e8f0; font-size: 14px; width: 14.28%; }
        .calendar-table td { height: 120px; border: 1px solid #e2e8f0; vertical-align: top; padding: 6px; position: relative; background: #fff; }
        .calendar-table td.other-month { background: #f8fafc; color: #94a3b8; }
        
        .day-number { font-size: 13px; font-weight: bold; margin-bottom: 6px; color: #475569; display: block; text-align: right; }

        .class-card { padding: 6px 8px; border-radius: 6px; font-size: 12px; margin-bottom: 4px; color: white; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .class-card.Presencial { background-color: #ef4444; }
        .class-card.Síncrona { background-color: #ca8a04; }
        .class-card.Asíncrona { background-color: #16a34a; }
        .class-card strong { display: block; font-size: 12px; }
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
            <h2>Calendario de Aulas</h2>
            
            <form method="GET" action="index.php" class="controls-bar" style="margin-top: 15px;" id="calendar-form">
                <div class="control-group">
                    <label for="aula_id"><strong>Aula:</strong></label>
                 
                 
                    <select name="aula_id" id="aula_id" onchange="this.form.submit()">
    <?php foreach ($aulas as $a): ?>
        <?php $seleccionado = ($a['id'] == $aula_id_actual) ? 'selected' : ''; ?>
        <option value="<?= $a['id'] ?>" <?= $seleccionado ?>>
            <?= $a['es_aula_magna'] ? 'Aula Magna' : 'Aula ' . htmlspecialchars($a['codigo']) . ' - Nivel ' . htmlspecialchars($a['nivel_id']) ?>
        </option>
    <?php endforeach; ?>
</select>


                </div>

                <div class="control-group">
                    <label for="vista"><strong>Vista:</strong></label>
                    <select name="vista" id="vista" onchange="this.form.submit()">
                        <option value="semanal" <?= $vista === 'semanal' ? 'selected' : '' ?>>Semanal</option>
                        <option value="mensual" <?= $vista === 'mensual' ? 'selected' : '' ?>>Mensual</option>
                    </select>
                </div>

                <div class="control-group">
                    <!-- Botón Anterior (Flecha Izquierda) -->
                    <a href="index.php?aula_id=<?= $aula_id_actual ?>&vista=<?= $vista ?>&fecha=<?= $fecha_anterior ?>" class="btn">◀ Anterior</a>
                    
                    <a href="index.php?aula_id=<?= $aula_id_actual ?>&vista=<?= $vista ?>&fecha=<?= date('Y-m-d') ?>" class="btn btn-secondary">Hoy</a>
                    
                    <input type="date" name="fecha" value="<?= htmlspecialchars($fecha_actual) ?>" onchange="this.form.submit()">
                    
                    <!-- Botón Siguiente (Flecha Derecha) -->
                    <a href="index.php?aula_id=<?= $aula_id_actual ?>&vista=<?= $vista ?>&fecha=<?= $fecha_siguiente ?>" class="btn">Siguiente ▶</a>
                </div>
            </form>

            <div class="legend">
                <div class="legend-item"><div class="dot Presencial"></div> Presencial</div>
                <div class="legend-item"><div class="dot Síncrona"></div> Síncrona</div>
                <div class="legend-item"><div class="dot Asíncrona"></div> Asíncrona</div>
            </div>

            <h3 style="margin-bottom: 15px; color: #1e3a8a; text-align: center;">
                <?= $vista === 'semanal' ? 'Semana del ' . $dias_calendario[0] . ' al ' . end($dias_calendario) : date('F Y', $timestamp) ?>
            </h3>

            <table class="calendar-table">
                <thead>
                    <tr>
                        <th>Lunes</th>
                        <th>Martes</th>
                        <th>Miércoles</th>
                        <th>Jueves</th>
                        <th>Viernes</th>
                        <th>Sábado</th>
                        <th>Domingo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_dias = count($dias_calendario);
                    for ($i = 0; $i < $total_dias; $i += 7): 
                    ?>
                        <tr>
                            <?php for ($j = 0; $j < 7; $j++): 
                                $idx = $i + $j;
                                if ($idx >= $total_dias) break;
                                $dia_fecha = $dias_calendario[$idx];
                                $es_mes_actual = ($vista === 'semanal') || (date('m', strtotime($dia_fecha)) === date('m', $timestamp));
                                $clase_celda = $es_mes_actual ? '' : 'other-month';
                            ?>
                                <td class="<?= $clase_celda ?>">
                                    <span class="day-number"><?= date('j', strtotime($dia_fecha)) ?></span>
                                    
                                    <?php if (isset($reservas_por_fecha[$dia_fecha])): ?>
                                        <?php foreach ($reservas_por_fecha[$dia_fecha] as $clase): ?>
                                            <div class="class-card <?= htmlspecialchars($clase['modalidad']) ?>">
                                                <strong><?= htmlspecialchars($clase['materia']) ?></strong>
                                                <span><?= substr($clase['hora_inicio'], 0, 5) ?> - <?= substr($clase['hora_fin'], 0, 5) ?></span><br>
                                                <small style="opacity: 0.9; font-size: 10px;"><?= htmlspecialchars($clase['profesor_nombre']) ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                            <?php endfor; ?>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>