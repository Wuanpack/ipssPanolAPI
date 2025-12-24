<?php

class HerramientasModel
{
    private function getConnection(): array
    {
        $con = new Conexion();
        return [$con, $con->getConnection()];
    }

    public function getHerramientaById(int $id): ?array
    {
        [$con, $conn] = $this->getConnection();

        $stmt = $conn->prepare(
            "SELECT id, n_parte, nombre, figura, indice, pagina, cantidad, cantidad_disponible, activo
            FROM herramientas
            WHERE id = ?"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $herr = $stmt->get_result()->fetch_assoc();

        $con->closeConnection();

        return $herr ?: null;
    }

    public function getInventario(): array
    {
        [$con, $conn] = $this->getConnection();

        try {
            $sql = "SELECT id, n_parte, nombre, figura, indice, pagina, cantidad, cantidad_disponible, activo
                    FROM herramientas";

            $stmt = $conn->prepare($sql);
            $stmt->execute();

            $result = $stmt->get_result();
            $inventario = [];

            while ($row = $result->fetch_assoc()) {
                $inventario[] = $row;
            }

            return $inventario;
        } finally {
            $con->closeConnection();
        }
    }

    public function updateHerramienta(int $id, array $data): void
    {
        [$con, $conn] = $this->getConnection();

        try {
            $conn->begin_transaction();

            $actual = $this->getHerramientaById($id);
            if (!$actual) {
                throw new Exception("Herramienta no encontrada");
            }

            $camposPermitidos = ['n_parte','nombre','figura','indice','pagina','cantidad','cantidad_disponible'];
            $set = [];
            $params = [];
            $types = "";

            // Validación de n_parte si se quiere cambiar
            if (isset($data['n_parte']) && $data['n_parte'] !== $actual['n_parte']) {
                $stmt = $conn->prepare(
                    "SELECT id FROM herramientas WHERE n_parte = ? AND id != ? AND activo = 1"
                );
                $stmt->bind_param("si", $data['n_parte'], $id);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                if ($row) {
                    throw new Exception("El número de parte ya existe en otra herramienta");
                }
            }

            foreach ($camposPermitidos as $campo) {
                if (!array_key_exists($campo, $data)) continue;

                if ($data[$campo] != $actual[$campo]) {
                    $set[] = "$campo = ?";
                    $params[] = $data[$campo];
                    // Manejo de tipos para bind_param
                    if (in_array($campo, ['cantidad','cantidad_disponible'], true)) {
                        $params[count($params)-1] = (int)$data[$campo]; // aseguramos entero
                        $types .= "i";
                    } else {
                        $types .= "s";
                    }
                }
            }

            if (empty($set)) {
                throw new Exception("No hay cambios para actualizar");
            }

            // Validación de negocio para cantidades
            $cantidad = $data['cantidad'] ?? $actual['cantidad'];
            $cantidadDisponible = $data['cantidad_disponible'] ?? $actual['cantidad_disponible'];
            if ($cantidadDisponible > $cantidad) {
                throw new Exception("La cantidad disponible no puede ser mayor a la cantidad total");
            }

            $sql = "UPDATE herramientas SET " . implode(", ", $set) . " WHERE id = ?";
            $params[] = $id;
            $types .= "i";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);

            if (!$stmt->execute()) {
                throw new Exception("Error al actualizar herramienta");
            }

            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        } finally {
            $con->closeConnection();
        }
    }

    public function setEstadoHerramienta(int $id, int $activo): void
    {
        if (!in_array($activo, [0, 1], true)) {
            throw new Exception("Estado inválido");
        }

        [$con, $conn] = $this->getConnection();

        try {
            $conn->begin_transaction();

            // Obtener herramienta con lock
            $stmt = $conn->prepare("SELECT activo FROM herramientas WHERE id = ? FOR UPDATE");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $herr = $stmt->get_result()->fetch_assoc();

            if (!$herr) throw new Exception("Herramienta no existe");
            if ((int)$herr['activo'] === $activo) {
                throw new Exception("La herramienta ya se encuentra en ese estado");
            }

            // Validación de negocio: no desactivar si hay préstamos activos
            if ($activo === 0) {
                $stmt = $conn->prepare(
                    "SELECT COUNT(*) AS total
                    FROM movimiento
                    WHERE herramienta_id = ?
                    AND tipo_movimiento_id = 2
                    AND activo = 1"
                );
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();

                if ((int)$row['total'] > 0) {
                    throw new Exception(
                        "No se puede desactivar la herramienta con préstamos activos"
                    );
                }
            }

            // Actualizar estado
            $stmt = $conn->prepare("UPDATE herramientas SET activo = ? WHERE id = ?");
            $stmt->bind_param("ii", $activo, $id);
            $stmt->execute();

            $conn->commit();

        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        } finally {
            $con->closeConnection();
        }
    }
}