<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$mensaje = "";
$error = "";

if (!isset($_GET['id'])) {
    header("Location: mis_reservas.php");
    exit();
}

$reserva_id = $_GET['id'];

// Obtenemos los datos de la reserva actual
$stmt = $conexion->prepare("SELECT * FROM reservas WHERE id = ? AND usuario_id = ?");
$stmt->execute([$reserva_id, $usuario_id]);
$reserva = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reserva) {
    header("Location: mis_reservas.php");
    exit();
}

$stmt_aulas = $conexion->query("SELECT * FROM aulas");
$aulas = $stmt_aulas->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $aula_id = $_POST['aula_id'];
    $nueva_fecha = $_POST['fecha'];
    $hora_inicio = $_POST['hora_inicio'] . ":00";
    $hora_fin = $_POST['hora_fin'] . ":00";
    $materia = trim($_POST['materia']);
    $modalidad = $_POST['modalidad'];

    // Calculamos el día de la semana correspondiente a la nueva fecha
    $dias_map = ['Sunday' => 'Domingo', 'Monday' => 'Lunes', 'Tuesday' => 'Martes', 'Wednesday' => 'Miércoles', 'Thursday' => 'Jueves', 'Friday' => 'Viernes', 'Saturday' => 'Sábado'];
    $dia_semana = $dias_map[date('l', strtotime($nueva_fecha))];

    if ($hora_inicio >= $hora_fin) {
        $error = "La hora de inicio debe ser menor a la hora de fin.";
    } else {
        // Validamos si se presionó el botón de guardar serie
        if (isset($_POST['guardar_serie']) && !empty($reserva['grupo_reserva_id'])) {
            $grupo_id = $reserva['grupo_reserva_id'];
            $stmt_update = $conexion->prepare("UPDATE reservas SET aula_id = ?, hora_inicio = ?, hora_fin = ?, materia = ?, modalidad = ? WHERE grupo_reserva_id = ? AND usuario_id = ?");
            if ($stmt_update->execute([$aula_id, $hora_inicio, $hora_fin, $materia, $modalidad, $grupo_id, $usuario_id])) {
                $mensaje = "¡Toda la serie recurrente fue actualizada con éxito!";
            } else {
                $error = "Ocurrió un error al actualizar la serie.";
            }
        } else {
            // Actualización únicamente de este día específico
            $stmt_cruce = $conexion->prepare("SELECT * FROM reservas WHERE aula_id = ? AND fecha = ? AND id != ? AND (hora_inicio < ? AND hora_fin > ?)");
            $stmt_cruce->execute([$aula_id, $nueva_fecha, $reserva_id, $hora_fin, $hora_inicio]);
            $cruce = $stmt_cruce->fetch(PDO::FETCH_ASSOC);

            if ($cruce) {
                $error = "¡Conflicto de horario! El aula ya está ocupada en esa fecha y horario.";
            } else {
                $stmt_update = $conexion->prepare("UPDATE reservas SET aula_id = ?, fecha = ?, dia_semana = ?, hora_inicio = ?, hora_fin = ?, materia = ?, modalidad = ? WHERE id = ? AND usuario_id = ?");
                if ($stmt_update->execute([$aula_id, $nueva_fecha, $dia_semana, $hora_inicio, $hora_fin, $materia, $modalidad, $reserva_id, $usuario_id])) {
                    $mensaje = "¡Reserva individual actualizada con éxito!";
                } else {
                    $error = "Ocurrió un error al actualizar la reserva.";
                }
            }
        }

        // Refrescamos los datos en pantalla
        $stmt->execute([$reserva_id, $usuario_id]);
        $reserva = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Reserva - Sistema de Aulas</title>
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
        h2 { color: #1e3a8a; margin-bottom: 10px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; color: #475569; }
        select, input[type="text"], input[type="date"] { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; }
        
        /* Botones de acción organizados abajo */
        .btn-primary { background-color: #0284c7; color: white; border: none; padding: 12px 20px; border-radius: 6px; font-size: 15px; font-weight: bold; cursor: pointer; width: 100%; transition: background 0.2s; margin-bottom: 10px; }
        .btn-primary:hover { background-color: #0369a1; }

        .btn-serie { background-color: #d97706; color: white; border: none; padding: 12px 20px; border-radius: 6px; font-size: 15px; font-weight: bold; cursor: pointer; width: 100%; transition: background 0.2s; margin-bottom: 15px; }
        .btn-serie:hover { background-color: #b45309; }

        .btn-regresar { display: block; text-align: center; text-decoration: none; color: #64748b; font-weight: 600; font-size: 14px; }
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
            <a href="mis_reservas.php" class="active">📋 Mis Reservas</a>
            <a href="disponibilidad.php">🔍 Buscar Disponibilidad</a>
            <a href="aulas.php">🏛️ Aulas y Recursos</a>
        </nav>
        <div>
            <span class="user-info">Hola, <?= htmlspecialchars($_SESSION['nombre']) ?></span>
            <a href="logout.php" class="btn-logout">Salir</a>
        </div>
    </header>

    <div class="container">
        <div class="card">
            <h2>Editar Reserva Completa</h2>
            <p style="font-size: 13px; color: #64748b; margin-bottom: 20px;">
                Modifica cualquier parámetro de esta sesión de clases. Guarda solo este día o actualiza toda la serie recurrente de golpe.
            </p>

            <?php if (!empty($mensaje)): ?>
                <div class="alert-success"><?= htmlspecialchars($mensaje) ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form action="editar_reserva.php?id=<?= $reserva['id'] ?>" method="POST">
                <div class="form-group">
                    <label for="aula_id">Seleccionar Aula:</label>
                    <select name="aula_id" id="aula_id" required>
                        <?php foreach ($aulas as $a): ?>
                            <option value="<?= $a['id'] ?>" <?= $a['id'] == $reserva['aula_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($a['codigo']) ?> <?= $a['es_aula_magna'] ? '(Aula Magna)' : '- Nivel ' . $a['nivel_id'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="fecha">Fecha Específica:</label>
                    <input type="date" id="fecha" name="fecha" required value="<?= htmlspecialchars($reserva['fecha']) ?>">
                </div>

                <div class="form-group">
                    <label for="hora_inicio">Hora de Inicio:</label>
                    <select name="hora_inicio" id="hora_inicio" required>
                        <?php for ($i = 6; $i <= 21; $i++): 
                            $val = str_pad($i, 2, "0", STR_PAD_LEFT) . ":00";
                            $selected = (substr($reserva['hora_inicio'], 0, 5) === $val) ? 'selected' : '';
                        ?>
                            <option value="<?= $val ?>" <?= $selected ?>><?= $val ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="hora_fin">Hora de Fin:</label>
                    <select name="hora_fin" id="hora_fin" required>
                        <?php for ($i = 7; $i <= 22; $i++): 
                            $val = str_pad($i, 2, "0", STR_PAD_LEFT) . ":00";
                            $selected = (substr($reserva['hora_fin'], 0, 5) === $val) ? 'selected' : '';
                        ?>
                            <option value="<?= $val ?>" <?= $selected ?>><?= $val ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="materia">Nombre de la Materia / Curso:</label>
                    <input type="text" id="materia" name="materia" required value="<?= htmlspecialchars($reserva['materia']) ?>">
                </div>

                <div class="form-group">
                    <label for="modalidad">Modalidad de Clase:</label>
                    <select name="modalidad" id="modalidad" required>
                        <option value="Presencial" <?= $reserva['modalidad'] === 'Presencial' ? 'selected' : '' ?>>Presencial (Rojo)</option>
                        <option value="Síncrona" <?= $reserva['modalidad'] === 'Síncrona' ? 'selected' : '' ?>>Síncrona (Amarillo)</option>
                        <option value="Asíncrona" <?= $reserva['modalidad'] === 'Asíncrona' ? 'selected' : '' ?>>Asíncrona (Verde)</option>
                    </select>
                </div>

                <!-- Botón principal para guardar solo este día -->
                <button type="submit" class="btn-primary">💾 Guardar Cambios (Solo este día)</button>

                <!-- Botón de Editar Serie ubicado hasta abajo -->
                <?php if (!empty($reserva['grupo_reserva_id'])): ?>
                    <button type="submit" name="guardar_serie" value="1" class="btn-serie" onclick="return confirm('¿Estás seguro de actualizar toda la serie recurrente con estos nuevos datos?')">🔄 Guardar Cambios en Toda la Serie</button>
                <?php endif; ?>

                <a href="mis_reservas.php" class="btn-regresar">← Volver a Mis Reservas</a>
            </form>
        </div>
    </div>

</body>
</html>