<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$aulas_disponibles = [];
$busqueda_realizada = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $dia_semana = $_POST['dia_semana'];
    $hora_inicio = $_POST['hora_inicio'] . ":00";
    $hora_fin = $_POST['hora_fin'] . ":00";
    $busqueda_realizada = true;

    if ($hora_inicio < $hora_fin) {
        // Buscamos aulas cuyo ID NO se encuentre en la lista de reservas que se cruzan en ese día y horario
        $sql = "SELECT a.*, n.nombre as nivel_nombre 
                FROM aulas a 
                LEFT JOIN niveles n ON a.nivel_id = n.id 
                WHERE a.id NOT IN (
                    SELECT aula_id FROM reservas 
                    WHERE dia_semana = ? AND (hora_inicio < ? AND hora_fin > ?)
                )";
        
        $stmt = $conexion->prepare($sql);
        $stmt->execute([$dia_semana, $hora_fin, $hora_inicio]);
        $aulas_disponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buscar Disponibilidad - Sistema de Aulas</title>
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
        .container { max-width: 1000px; margin: 30px auto; padding: 0 20px; }
        .card { background: white; border-radius: 10px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); padding: 30px; margin-bottom: 20px; }
        h2 { color: #1e3a8a; margin-bottom: 20px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .form-group { margin-bottom: 10px; }
        label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 14px; color: #475569; }
        select, input { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; }
        button { background-color: #005f73; color: white; border: none; padding: 12px 20px; border-radius: 6px; font-size: 16px; font-weight: bold; cursor: pointer; width: 100%; transition: background 0.2s; }
        button:hover { background-color: #0a9396; }
        
        .results-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px; margin-top: 20px; }
        .aula-card { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 15px; }
        .aula-card h3 { color: #166534; font-size: 16px; margin-bottom: 5px; }
        .badge { background: #22c55e; color: white; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
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
            <a href="disponibilidad.php" class="active">🔍 Buscar Disponibilidad</a>
            <a href="aulas.php">🏛️ Aulas y Recursos</a>
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
            <h2>Buscar Aulas Disponibles</h2>
            <p style="font-size: 13px; color: #64748b; margin-bottom: 20px;">
                Indica el día y el rango de horas que necesitas para consultar qué salones están libres.
            </p>

            <form action="disponibilidad.php" method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="dia_semana">Día:</label>
                        <select name="dia_semana" id="dia_semana" required>
                            <option value="Lunes">Lunes</option>
                            <option value="Martes">Martes</option>
                            <option value="Miércoles">Miércoles</option>
                            <option value="Jueves">Jueves</option>
                            <option value="Viernes">Viernes</option>
                            <option value="Sábado">Sábado</option>
                            <option value="Domingo">Domingo</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="hora_inicio">Hora Inicio:</label>
                        <select name="hora_inicio" id="hora_inicio" required>
                            <?php for ($i = 6; $i <= 21; $i++): ?>
                                <option value="<?= str_pad($i, 2, "0", STR_PAD_LEFT) . ":00" ?>"><?= str_pad($i, 2, "0", STR_PAD_LEFT) ?>:00</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="hora_fin">Hora Fin:</label>
                        <select name="hora_fin" id="hora_fin" required>
                            <?php for ($i = 7; $i <= 22; $i++): ?>
                                <option value="<?= str_pad($i, 2, "0", STR_PAD_LEFT) . ":00" ?>"><?= str_pad($i, 2, "0", STR_PAD_LEFT) ?>:00</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <button type="submit">Consultar Disponibilidad</button>
            </form>
        </div>

        <?php if ($busqueda_realizada): ?>
            <div class="card">
                <h3>Resultados de Disponibilidad</h3>
                <?php if (count($aulas_disponibles) > 0): ?>
                    <div class="results-grid">
                        <?php foreach ($aulas_disponibles as $aula): ?>
                            <div class="aula-card">
                                <h3>Aula <?= htmlspecialchars($aula['codigo']) ?></h3>
                                <p style="font-size: 13px; color: #4b5563; margin-bottom: 8px;">
                                    <?= $aula['es_aula_magna'] ? 'Aula Magna (Exclusiva)' : 'Nivel ' . $aula['nivel_id'] ?> | Capacidad: <?= $aula['capacidad'] ?> pers.
                                </p>
                                <span class="badge">Disponible</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="margin-top: 15px; color: #b91c1c; font-weight: 500;">Lo sentimos, no hay aulas disponibles en ese día y rango horario.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>