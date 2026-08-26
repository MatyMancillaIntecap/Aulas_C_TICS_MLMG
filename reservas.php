<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$mensaje = "";
$error = "";

$stmt_aulas = $conexion->query("SELECT * FROM aulas");
$aulas = $stmt_aulas->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $aula_id = $_POST['aula_id'];
    $usuario_id = $_SESSION['usuario_id'];
    $dias_seleccionados = isset($_POST['dias']) ? $_POST['dias'] : [];
    $fecha_inicio_rango = $_POST['fecha_inicio'];
    $fecha_fin_rango = $_POST['fecha_fin'];
    $hora_inicio = $_POST['hora_inicio'] . ":00";
    $hora_fin = $_POST['hora_fin'] . ":00";
    $materia = trim($_POST['materia']);
    $modalidad = $_POST['modalidad'];

    if (empty($dias_seleccionados)) {
        $error = "Debes seleccionar al menos un día de la semana.";
    } elseif ($fecha_inicio_rango > $fecha_fin_rango) {
        $error = "La fecha de inicio del rango no puede ser mayor a la fecha de fin.";
    } elseif ($hora_inicio >= $hora_fin) {
        $error = "La hora de inicio debe ser menor a la hora de fin.";
    } else {
        $grupo_id = uniqid('rec_');
        $conflictos = 0;
        $exitos = 0;

        $dias_map = [
            'Lunes' => 'Monday',
            'Martes' => 'Tuesday',
            'Miércoles' => 'Wednesday',
            'Jueves' => 'Thursday',
            'Viernes' => 'Friday',
            'Sábado' => 'Saturday',
            'Domingo' => 'Sunday'
        ];

        foreach ($dias_seleccionados as $dia_esp) {
            if (!isset($dias_map[$dia_esp])) continue;
            
            $dia_ingles = $dias_map[$dia_esp];
            $current_time = strtotime("next $dia_ingles", strtotime($fecha_inicio_rango . " -1 day"));
            $end_time = strtotime($fecha_fin_rango);

            if (date('l', strtotime($fecha_inicio_rango)) === $dia_ingles) {
                $current_time = strtotime($fecha_inicio_rango);
            }

            while ($current_time <= $end_time) {
                $fecha_actual_str = date('Y-m-d', $current_time);

                $stmt_cruce = $conexion->prepare("SELECT * FROM reservas WHERE aula_id = ? AND fecha = ? AND (hora_inicio < ? AND hora_fin > ?)");
                $stmt_cruce->execute([$aula_id, $fecha_actual_str, $hora_fin, $hora_inicio]);
                $cruce = $stmt_cruce->fetch(PDO::FETCH_ASSOC);

                if ($cruce) {
                    $conflictos++;
                } else {
                    $stmt_insert = $conexion->prepare("INSERT INTO reservas (aula_id, usuario_id, fecha, fecha_fin, dia_semana, hora_inicio, hora_fin, materia, modalidad, grupo_reserva_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    if ($stmt_insert->execute([$aula_id, $usuario_id, $fecha_actual_str, $fecha_fin_rango, $dia_esp, $hora_inicio, $hora_fin, $materia, $modalidad, $grupo_id])) {
                        $exitos++;
                    }
                }
                $current_time = strtotime("+1 week", $current_time);
            }
        }

        if ($conflictos > 0) {
            $error = "Se crearon $exitos clases, pero se omitieron $conflictos fechas por conflictos de horario.";
        } else {
            $mensaje = "¡Reserva recurrente registrada con éxito!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Reserva - Sistema de Aulas</title>
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
        .container { max-width: 700px; margin: 30px auto; padding: 0 20px; }
        .card { background: white; border-radius: 10px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); padding: 30px; }
        h2 { color: #1e3a8a; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #475569; }
        select, input[type="text"], input[type="date"] { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; }
        .days-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(90px, 1fr)); gap: 10px; background: #f8fafc; padding: 15px; border-radius: 6px; border: 1px solid #e2e8f0; }
        .day-checkbox { display: flex; align-items: center; gap: 8px; font-size: 14px; cursor: pointer; font-weight: 500; }
        .day-checkbox input { width: 18px; height: 18px; cursor: pointer; }
        .date-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        button { background-color: #005f73; color: white; border: none; padding: 12px 20px; border-radius: 6px; font-size: 16px; font-weight: bold; cursor: pointer; width: 100%; transition: background 0.2s; }
        button:hover { background-color: #0a9396; }
        .alert-success { background-color: #d1fae5; color: #065f46; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #a7f3d0; }
        .alert-error { background-color: #fee2e2; color: #991b1b; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #fecaca; }
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
            <h2>Registrar Reserva Recurrente</h2>

            <?php if (!empty($mensaje)): ?>
                <div class="alert-success"><?= htmlspecialchars($mensaje) ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form action="reservas.php" method="POST">
                <div class="form-group">
                    <label for="aula_id">Seleccionar Aula:</label>
                    <select name="aula_id" id="aula_id" required>
                        <?php foreach ($aulas as $a): ?>
                            <option value="<?= $a['id'] ?>">
                                <?= htmlspecialchars($a['codigo']) ?> <?= $a['es_aula_magna'] ? '(Aula Magna)' : '- Nivel ' . $a['nivel_id'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Seleccionar Días de la Semana:</label>
                    <div class="days-container">
                        <label class="day-checkbox"><input type="checkbox" name="dias[]" value="Lunes"> Lunes</label>
                        <label class="day-checkbox"><input type="checkbox" name="dias[]" value="Martes"> Martes</label>
                        <label class="day-checkbox"><input type="checkbox" name="dias[]" value="Miércoles"> Miércoles</label>
                        <label class="day-checkbox"><input type="checkbox" name="dias[]" value="Jueves"> Jueves</label>
                        <label class="day-checkbox"><input type="checkbox" name="dias[]" value="Viernes"> Viernes</label>
                        <label class="day-checkbox"><input type="checkbox" name="dias[]" value="Sábado"> Sábado</label>
                        <label class="day-checkbox"><input type="checkbox" name="dias[]" value="Domingo"> Domingo</label>
                    </div>
                </div>

                <div class="date-grid">
                    <div class="form-group">
                        <label for="fecha_inicio">Fecha Inicio:</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label for="fecha_fin">Fecha Fin:</label>
                        <input type="date" id="fecha_fin" name="fecha_fin" required value="<?= date('Y-m-d', strtotime('+1 month')) ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="hora_inicio">Hora de Inicio:</label>
                    <select name="hora_inicio" id="hora_inicio" required>
                        <?php for ($i = 6; $i <= 21; $i++): ?>
                            <option value="<?= str_pad($i, 2, "0", STR_PAD_LEFT) . ":00" ?>"><?= str_pad($i, 2, "0", STR_PAD_LEFT) ?>:00</option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="hora_fin">Hora de Fin:</label>
                    <select name="hora_fin" id="hora_fin" required>
                        <?php for ($i = 7; $i <= 22; $i++): ?>
                            <option value="<?= str_pad($i, 2, "0", STR_PAD_LEFT) . ":00" ?>"><?= str_pad($i, 2, "0", STR_PAD_LEFT) ?>:00</option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="materia">Nombre de la Materia / Curso:</label>
                    <input type="text" id="materia" name="materia" required placeholder="Ej. Programación 1">
                </div>

                <div class="form-group">
                    <label for="modalidad">Modalidad de Clase:</label>
                    <select name="modalidad" id="modalidad" required>
                        <option value="Presencial">Presencial (Rojo)</option>
                        <option value="Síncrona">Síncrona (Amarillo)</option>
                        <option value="Asíncrona">Asíncrona (Verde)</option>
                    </select>
                </div>

                <button type="submit">Guardar Reserva Recurrente</button>
            </form>
        </div>
    </div>

</body>
</html>