<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'administrador') {
    header("Location: login.php");
    exit();
}

$mensaje = "";
$error = "";

// 1. Lógica para registrar un nuevo catedrático
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['registrar_catedratico'])) {
    $nombre = trim($_POST['nombre']);
    $correo = trim($_POST['correo']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $rol = 'catedratico';
    $aulas_asignadas = isset($_POST['aulas']) ? $_POST['aulas'] : [];

    if (empty($nombre) || empty($correo) || empty($_POST['password'])) {
        $error = "Todos los campos del catedrático son obligatorios.";
    } else {
        $stmt_check = $conexion->prepare("SELECT id FROM usuarios WHERE correo = ?");
        $stmt_check->execute([$correo]);
        if ($stmt_check->fetch()) {
            $error = "El correo electrónico ya está registrado.";
        } else {
            $stmt_insert = $conexion->prepare("INSERT INTO usuarios (nombre, correo, password, rol) VALUES (?, ?, ?, ?)");
            if ($stmt_insert->execute([$nombre, $correo, $password, $rol])) {
                $nuevo_usuario_id = $conexion->lastInsertId();
                if (!empty($aulas_asignadas)) {
                    $stmt_ua = $conexion->prepare("INSERT INTO usuario_aulas (usuario_id, aula_id) VALUES (?, ?)");
                    foreach ($aulas_asignadas as $aula_id) {
                        $stmt_ua->execute([$nuevo_usuario_id, $aula_id]);
                    }
                }
                $mensaje = "¡Catedrático registrado con éxito!";
            } else {
                $error = "Error al registrar el usuario.";
            }
        }
    }
}

// 2. Lógica para actualizar las aulas a cargo de un catedrático existente
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar_catedratico'])) {
    $usuario_id = $_POST['usuario_id'];
    $aulas_asignadas = isset($_POST['aulas']) ? $_POST['aulas'] : [];

    // Borramos las asignaciones anteriores y guardamos las nuevas
    $conexion->prepare("DELETE FROM usuario_aulas WHERE usuario_id = ?")->execute([$usuario_id]);

    if (!empty($aulas_asignadas)) {
        $stmt_ua = $conexion->prepare("INSERT INTO usuario_aulas (usuario_id, aula_id) VALUES (?, ?)");
        foreach ($aulas_asignadas as $aula_id) {
            $stmt_ua->execute([$usuario_id, $aula_id]);
        }
    }
    $mensaje = "¡Aulas a cargo del catedrático actualizadas con éxito!";
}

// 3. Lógica para actualizar un Aula existente (Capacidad, Estado, Equipamiento, Aula Magna)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar_aula'])) {
    $codigo = trim($_POST['codigo']);
    $capacidad = $_POST['capacidad'];
    $es_aula_magna = isset($_POST['es_aula_magna']) ? 1 : 0;
    $estado = $_POST['estado'];
    $equipamiento_texto = trim($_POST['equipamiento_texto']);

    $stmt_upd = $conexion->prepare("UPDATE aulas SET capacidad = ?, es_aula_magna = ?, estado = ?, equipamiento_texto = ? WHERE codigo = ?");
    if ($stmt_upd->execute([$capacidad, $es_aula_magna, $estado, $equipamiento_texto, $codigo])) {
        $mensaje = "¡Aula actualizada con éxito!";
    } else {
        $error = "Error al actualizar el aula.";
    }
}

// 4. Lógica para eliminar catedrático
if (isset($_GET['eliminar_cat'])) {
    $id_eliminar = $_GET['eliminar_cat'];
    if ($id_eliminar != $_SESSION['usuario_id']) {
        $conexion->prepare("DELETE FROM usuario_aulas WHERE usuario_id = ?")->execute([$id_eliminar]);
        $conexion->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$id_eliminar]);
        $mensaje = "Catedrático eliminado.";
    }
}

// Cargar datos si se va a editar un catedrático específico
$catedratico_a_editar = null;
$aulas_del_catedratico = [];
if (isset($_GET['editar_cat'])) {
    $id_edit_cat = $_GET['editar_cat'];
    $stmt_cat_ed = $conexion->prepare("SELECT * FROM usuarios WHERE id = ? AND rol = 'catedratico'");
    $stmt_cat_ed->execute([$id_edit_cat]);
    $catedratico_a_editar = $stmt_cat_ed->fetch(PDO::FETCH_ASSOC);

    if ($catedratico_a_editar) {
        $stmt_aulas_cat = $conexion->prepare("SELECT aula_id FROM usuario_aulas WHERE usuario_id = ?");
        $stmt_aulas_cat->execute([$id_edit_cat]);
        $aulas_del_catedratico = $stmt_aulas_cat->fetchAll(PDO::FETCH_COLUMN);
    }
}

// Estadísticas y listas generales
$total_aulas = $conexion->query("SELECT COUNT(*) FROM aulas")->fetchColumn();
$total_reservas = $conexion->query("SELECT COUNT(*) FROM reservas")->fetchColumn();
$total_catedraticos = $conexion->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'catedratico'")->fetchColumn();

$aulas = $conexion->query("SELECT a.*, n.nombre as nivel_nombre FROM aulas a LEFT JOIN niveles n ON a.nivel_id = n.id ORDER BY a.nivel_id ASC, a.codigo ASC")->fetchAll(PDO::FETCH_ASSOC);

$sql_profes = "SELECT u.id, u.nombre, u.correo, GROUP_CONCAT(a.codigo SEPARATOR ', ') as aulas_cargo 
               FROM usuarios u 
               LEFT JOIN usuario_aulas ua ON u.id = ua.usuario_id 
               LEFT JOIN aulas a ON ua.aula_id = a.id 
               WHERE u.rol = 'catedratico' 
               GROUP BY u.id, u.nombre, u.correo";
$catedraticos = $conexion->query($sql_profes)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administrativo - Sistema de Aulas</title>
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
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border-left: 4px solid #0284c7; }
        .stat-card h3 { font-size: 14px; color: #64748b; margin-bottom: 8px; }
        .stat-card .number { font-size: 24px; font-weight: bold; color: #1e3a8a; }

        .card { background: white; border-radius: 10px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); padding: 25px; margin-bottom: 25px; }
        h2 { color: #1e3a8a; margin-bottom: 10px; font-size: 20px; }
        
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 15px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: #475569; }
        input[type="text"], input[type="email"], input[type="password"], input[type="number"], select, textarea { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; background: white; }
        textarea { resize: vertical; height: 80px; }
        
        .checkbox-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; background: #f8fafc; padding: 12px; border-radius: 6px; border: 1px solid #e2e8f0; max-height: 180px; overflow-y: auto; }
        .checkbox-item { display: flex; align-items: center; gap: 6px; font-size: 13px; cursor: pointer; }

        .btn { background-color: #005f73; color: white; border: none; padding: 10px 18px; border-radius: 6px; font-size: 14px; font-weight: bold; cursor: pointer; transition: background 0.2s; }
        .btn:hover { background-color: #0a9396; }
        .btn-edit { background-color: #0284c7; color: white; padding: 6px 10px; border-radius: 4px; text-decoration: none; font-size: 12px; font-weight: bold; display: inline-block; }
        .btn-edit:hover { background-color: #0369a1; }

        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
        th { background-color: #f1f5f9; color: #1e293b; font-weight: bold; }
        tr:hover { background-color: #f8fafc; }
        
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; color: white; }
        .badge.activa { background-color: #22c55e; }
        .badge.inactiva { background-color: #ef4444; }

        .btn-delete { background-color: #ef4444; color: white; padding: 6px 10px; border-radius: 4px; text-decoration: none; font-size: 12px; font-weight: bold; display: inline-block; }
        .btn-delete:hover { background-color: #dc2626; }

        .alert-success { background-color: #d1fae5; color: #065f46; padding: 12px; border-radius: 6px; margin-bottom: 15px; border: 1px solid #a7f3d0; font-size: 13px; }
        .alert-error { background-color: #fee2e2; color: #991b1b; padding: 12px; border-radius: 6px; margin-bottom: 15px; border: 1px solid #fecaca; font-size: 13px; }
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
            <a href="admin_panel.php" class="active" style="color: #d97706; font-weight: bold;">⚙️ Panel Admin</a>
        </nav>
        <div>
            <span class="user-info">Hola, <?= htmlspecialchars($_SESSION['nombre']) ?></span>
            <a href="logout.php" class="btn-logout">Salir</a>
        </div>
    </header>

    <div class="container">
        <div class="stats-grid">
            <div class="stat-card" style="border-left-color: #0284c7;">
                <h3>Total de Aulas Registradas</h3>
                <div class="number"><?= $total_aulas ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #22c55e;">
                <h3>Reservas Activas</h3>
                <div class="number"><?= $total_reservas ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #eab308;">
                <h3>Catedráticos en el Sistema</h3>
                <div class="number"><?= $total_catedraticos ?></div>
            </div>
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="alert-success"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- SECCIÓN: CONFIGURACIÓN DE AULAS -->
        <div class="card">
            <h2>Configuración de Aulas Existentes</h2>
            <p style="font-size: 13px; color: #64748b; margin-bottom: 20px;">
                Selecciona un salón del catálogo institucional para actualizar su capacidad, estado operativo y el detalle de su equipamiento.
            </p>

            <form action="admin_panel.php" method="POST">
                <input type="hidden" name="actualizar_aula" value="1">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="codigo">Seleccionar Aula:</label>
                        <select name="codigo" id="codigo" required>
                            <option value="">-- Seleccione un aula --</option>
                            <?php foreach ($aulas as $au): 
                                $texto_mostrar = $au['es_aula_magna'] ? 'Aula Magna' : 'Aula ' . $au['codigo'];
                            ?>
                                <option value="<?= htmlspecialchars($au['codigo']) ?>">
                                    <?= $texto_mostrar ?> (<?= $au['es_aula_magna'] ? 'Nivel 2' : htmlspecialchars($au['nivel_nombre']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="capacidad">Capacidad (Personas):</label>
                        <input type="number" id="capacidad" name="capacidad" value="35" required>
                    </div>
                    <div class="form-group">
                        <label for="estado">Estado Operativo:</label>
                        <select name="estado" id="estado">
                            <option value="activa">Activa (Disponible)</option>
                            <option value="inactiva">Inactiva (Bodega / Mantenimiento)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 15px;">
                    <label for="equipamiento_texto">Equipamiento / Recursos (Escribir manualmente):</label>
                    <textarea id="equipamiento_texto" name="equipamiento_texto" placeholder="Ej. 22 computadoras, 1 proyector, 1 impresora"></textarea>
                </div>

                <button type="submit" class="btn" style="margin-top: 10px;">Guardar Cambios del Aula</button>
            </form>

            <h3 style="margin-top: 25px; margin-bottom: 10px; font-size: 16px; color: #1e3a8a;">Listado de Aulas en el Sistema</h3>
            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nivel / Tipo</th>
                        <th>Capacidad</th>
                        <th>Equipamiento</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($aulas as $au): ?>
                        <tr>
                            <td><strong><?= $au['es_aula_magna'] ? 'Aula Magna' : 'Aula ' . htmlspecialchars($au['codigo']) ?></strong></td>
                            <td><?= $au['es_aula_magna'] ? 'Nivel 2' : htmlspecialchars($au['nivel_nombre']) ?></td>
                            <td><?= htmlspecialchars($au['capacidad']) ?> personas</td>
                            <td><small><?= htmlspecialchars($au['equipamiento_texto'] ?? 'Sin equipamiento') ?></small></td>
                            <td><span class="badge <?= htmlspecialchars($au['estado']) ?>"><?= ucfirst(htmlspecialchars($au['estado'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- SECCIÓN: GESTIÓN DE CATEDRÁTICOS (REGISTRO Y EDICIÓN DE AULAS A CARGO) -->
        <div class="card">
            <h2><?= $catedratico_a_editar ? 'Editar Aulas a Cargo de: ' . htmlspecialchars($catedratico_a_editar['nombre']) : 'Gestión de Catedráticos' ?></h2>
            <p style="font-size: 13px; color: #64748b; margin-bottom: 20px;">
                <?= $catedratico_a_editar ? 'Modifica las aulas asignadas a este profesor.' : 'Agrega nuevos profesores al sistema y asígnales sus aulas a cargo.' ?>
            </p>

            <form action="admin_panel.php" method="POST">
                <?php if ($catedratico_a_editar): ?>
                    <input type="hidden" name="actualizar_catedratico" value="1">
                    <input type="hidden" name="usuario_id" value="<?= $catedratico_a_editar['id'] ?>">
                <?php else: ?>
                    <input type="hidden" name="registrar_catedratico" value="1">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nombre">Nombre Completo:</label>
                            <input type="text" id="nombre" name="nombre" required placeholder="Ej. Carlos Mendoza">
                        </div>
                        <div class="form-group">
                            <label for="correo">Correo Electrónico:</label>
                            <input type="email" id="correo" name="correo" required placeholder="profesor@intecap.edu.gt">
                        </div>
                        <div class="form-group">
                            <label for="password">Contraseña Temporal:</label>
                            <input type="password" id="password" name="password" required placeholder="******">
                        </div>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label><?= $catedratico_a_editar ? 'Modificar Aulas Asignadas:' : 'Asignar Aulas a Cargo:' ?></label>
                    <div class="checkbox-container">
                        <?php foreach ($aulas as $a): 
                            // Marca automáticamente las casillas si el profesor ya tiene esa aula asignada
                            $checked = in_array($a['id'], $aulas_del_catedratico) ? 'checked' : '';
                        ?>
                            <label class="checkbox-item">
                                <input type="checkbox" name="aulas[]" value="<?= $a['id'] ?>" <?= $checked ?>> 
                                <?= $a['es_aula_magna'] ? 'Aula Magna' : 'Aula ' . htmlspecialchars($a['codigo']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="submit" class="btn"><?= $catedratico_a_editar ? 'Guardar Cambios de Aulas' : 'Guardar Catedrático y Asignaciones' ?></button>
                <?php if ($catedratico_a_editar): ?>
                    <a href="admin_panel.php" class="btn" style="background-color: #64748b; text-decoration: none; display: inline-block; margin-left: 10px; text-align: center;">Cancelar Edición</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- SECCIÓN: PROFESORES REGISTRADOS -->
        <div class="card">
            <h2>Profesores Registrados</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Correo Electrónico</th>
                        <th>Aulas a Cargo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($catedraticos) > 0): ?>
                        <?php foreach ($catedraticos as $cat): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($cat['id']) ?></strong></td>
                                <td><?= htmlspecialchars($cat['nombre']) ?></td>
                                <td><?= htmlspecialchars($cat['correo']) ?></td>
                                <td><strong><?= !empty($cat['aulas_cargo']) ? 'Aula(s): ' . htmlspecialchars($cat['aulas_cargo']) : '<span style="color: #94a3b8;">Sin aulas asignadas</span>' ?></strong></td>
                                <td>
                                    <a href="admin_panel.php?editar_cat=<?= $cat['id'] ?>" class="btn-edit" style="margin-right: 5px;">Editar Aulas</a>
                                    <?php if ($cat['id'] != $_SESSION['usuario_id']): ?>
                                        <a href="admin_panel.php?eliminar_cat=<?= $cat['id'] ?>" class="btn-delete" onclick="return confirm('¿Estás seguro de eliminar a este catedrático?')">Eliminar</a>
                                    <?php else: ?>
                                        <span style="font-size: 12px; color: #64748b;">(Usuario Actual)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #94a3b8; padding: 25px;">No hay catedráticos registrados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>