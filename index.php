<?php
include 'conexion.php';

try {
    // Consultamos las aulas
    $sqlAulas = "SELECT id, codigo, capacidad, es_aula_magna FROM aulas";
    $resultadoAulas = $conexion->query($sqlAulas);
    $aulas = $resultadoAulas->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Sistema de Aulas - Listado con Recursos</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background-color: #f4f4f9; }
        h1 { color: #333; }
        table { width: 100%; border-collapse: collapse; background: #fff; margin-top: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #007bff; color: white; }
        ul { margin: 0; padding-left: 20px; }
    </style>
</head>
<body>

    <h1>Listado de Aulas y su Equipamiento</h1>
    <p>Salones registrados en la institución:</p>

    <table>
        <tr>
            <th>ID</th>
            <th>Código</th>
            <th>Capacidad</th>
            <th>Tipo</th>
            <th>Recursos Disponibles</th>
        </tr>
        <?php foreach ($aulas as $aula): ?>
        <tr>
            <td><?php echo $aula['id']; ?></td>
            <td><?php echo $aula['codigo']; ?></td>
            <td><?php echo $aula['capacidad']; ?> personas</td>
            <td><?php echo $aula['es_aula_magna'] == 1 ? 'Aula Magna' : 'Aula Normal'; ?></td>
            <td>
                <ul>
                    <?php
                    // Consultamos los recursos específicos de ESTE aula usando su ID
                    $aula_id = $aula['id'];
                    $sqlRecursos = "SELECT r.nombre, ar.cantidad 
                                    FROM aula_recurso ar 
                                    JOIN recursos r ON ar.recurso_id = r.id 
                                    WHERE ar.aula_id = $aula_id";
                    $resRecursos = $conexion->query($sqlRecursos);
                    $recursos = $resRecursos->fetchAll(PDO::FETCH_ASSOC);

                    if (count($recursos) > 0) {
                        foreach ($recursos as $rec) {
                            echo "<li>" . $rec['cantidad'] . " " . $rec['nombre'] . "(s)</li>";
                        }
                    } else {
                        echo "<li>Sin recursos asignados</li>";
                    }
                    ?>
                </ul>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>

</body>
</html>