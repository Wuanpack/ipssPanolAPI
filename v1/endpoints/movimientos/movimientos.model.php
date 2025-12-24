<?php
class MovimientosModel
{
    private function getConnection(): array
    {
        $con = new Conexion();
        return [$con, $con->getConnection()];
    }

    /* =====================================================
       CREAR SOLICITUD (NO TOCA STOCK)
       ===================================================== */
    public function crearSolicitud(
        string $rut,
        int $lugarId,
        string $nParte,
        int $cantidad
    ): int {
        [$con, $conn] = $this->getConnection();

        try {
            $conn->begin_transaction();

            /* USUARIO */
            $stmt = $conn->prepare(
                "SELECT id FROM usuarios WHERE rut = ? AND activo = 1"
            );
            $stmt->bind_param("s", $rut);
            $stmt->execute();
            $usuario = $stmt->get_result()->fetch_assoc();

            if (!$usuario) {
                throw new Exception("Usuario no existe o está inactivo");
            }

            /* HERRAMIENTA */
            $stmt = $conn->prepare(
                "SELECT id
                 FROM herramientas
                 WHERE n_parte = ? AND activo = 1"
            );
            $stmt->bind_param("s", $nParte);
            $stmt->execute();
            $herramienta = $stmt->get_result()->fetch_assoc();

            if (!$herramienta) {
                throw new Exception("Herramienta no existe o está inactiva");
            }

            /* CALCULAR N_MOVIMIENTO */
            $rs = $conn->query(
                "SELECT IFNULL(MAX(n_movimiento), 0) + 1 AS next_n FROM movimiento"
            );
            $nMovimiento = (int)$rs->fetch_assoc()['next_n'];

            /* INSERT SOLICITUD */
            $stmt = $conn->prepare(
                "INSERT INTO movimiento (
                    n_movimiento,
                    tipo_movimiento_id,
                    herramienta_id,
                    usuario_id,
                    lugar_id,
                    fecha_solicitud,
                    cantidad,
                    activo
                ) VALUES (
                    ?,
                    1,
                    ?, ?, ?, NOW(), ?, 1
                )"
            );

            $stmt->bind_param(
                "iiiii",
                $nMovimiento,
                $herramienta['id'],
                $usuario['id'],
                $lugarId,
                $cantidad
            );

            $stmt->execute();
            $conn->commit();

            return $nMovimiento;

        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        } finally {
            $con->closeConnection();
        }
    }

    /* =====================================================
       ACEPTAR SOLICITUD (DESCUENTA STOCK)
       ===================================================== */
    public function aceptarSolicitud(int $nMovimiento): void
    {
        [$con, $conn] = $this->getConnection();

        try {
            $conn->begin_transaction();

            /* OBTENER SOLICITUD + STOCK DISPONIBLE */
            $stmt = $conn->prepare(
                "SELECT 
                    m.cantidad,
                    m.tipo_movimiento_id,
                    h.id AS herramienta_id,
                    h.cantidad_disponible
                FROM movimiento m
                JOIN herramientas h ON h.id = m.herramienta_id
                WHERE m.n_movimiento = ?
                  AND m.activo = 1
                FOR UPDATE"
            );
            $stmt->bind_param("i", $nMovimiento);
            $stmt->execute();
            $mov = $stmt->get_result()->fetch_assoc();

            if (!$mov) {
                throw new Exception("Solicitud no existe");
            }

            if ((int)$mov['tipo_movimiento_id'] !== 1) {
                throw new Exception("La solicitud no está en estado 'Solicitado'");
            }

            if ($mov['cantidad'] > $mov['cantidad_disponible']) {
                throw new Exception("Stock disponible insuficiente");
            }

            /* DESCONTAR STOCK */
            $stmt = $conn->prepare(
                "UPDATE herramientas
                 SET cantidad_disponible = cantidad_disponible - ?
                 WHERE id = ?"
            );
            $stmt->bind_param("ii", $mov['cantidad'], $mov['herramienta_id']);
            $stmt->execute();

            /* ACTUALIZAR MOVIMIENTO */
            $stmt = $conn->prepare(
                "UPDATE movimiento
                 SET tipo_movimiento_id = 2,
                     fecha_prestamo = NOW(),
                     fecha_resolucion = NOW()
                 WHERE n_movimiento = ?"
            );
            $stmt->bind_param("i", $nMovimiento);
            $stmt->execute();

            $conn->commit();

        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        } finally {
            $con->closeConnection();
        }
    }

    /* =====================================================
       RECHAZAR SOLICITUD
       ===================================================== */
    public function rechazarSolicitud(
        int $nMovimiento,
        ?string $motivo = null
        ): void {
        [$con, $conn] = $this->getConnection();

        try {
            $conn->begin_transaction();

            /* =========================
            OBTENER SOLICITUD
            ========================= */
            $stmt = $conn->prepare(
                "SELECT tipo_movimiento_id
                FROM movimiento
                WHERE n_movimiento = ?
                AND activo = 1
                FOR UPDATE"
            );
            $stmt->bind_param("i", $nMovimiento);
            $stmt->execute();
            $mov = $stmt->get_result()->fetch_assoc();

            if (!$mov) {
                throw new Exception("La solicitud no existe o ya fue cerrada");
            }

            if ((int)$mov['tipo_movimiento_id'] !== 1) {
                throw new Exception(
                    "Solo se pueden rechazar solicitudes en estado 'Solicitado'"
                );
            }

            /* =========================
            RECHAZAR Y CERRAR SOLICITUD
            ========================= */
            $stmt = $conn->prepare(
                "UPDATE movimiento
                SET tipo_movimiento_id = 3,
                    motivo_rechazo = ?,
                    fecha_resolucion = NOW(),
                    activo = 0
                WHERE n_movimiento = ?"
            );
            $stmt->bind_param("si", $motivo, $nMovimiento);
            $stmt->execute();

            $conn->commit();

        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        } finally {
            $con->closeConnection();
        }
    }

    public function devolverPrestamo(int $nMovimiento): void
    {
        [$con, $conn] = $this->getConnection();

        try {
            $conn->begin_transaction();

            /* =========================
            OBTENER PRÉSTAMO
            ========================= */
            $stmt = $conn->prepare(
                "SELECT
                    m.cantidad,
                    m.tipo_movimiento_id,
                    h.id AS herramienta_id
                FROM movimiento m
                JOIN herramientas h ON h.id = m.herramienta_id
                WHERE m.n_movimiento = ?
                AND m.activo = 1
                FOR UPDATE"
            );
            $stmt->bind_param("i", $nMovimiento);
            $stmt->execute();
            $mov = $stmt->get_result()->fetch_assoc();

            if (!$mov) {
                throw new Exception("El movimiento no existe o ya fue cerrado");
            }

            if ((int)$mov['tipo_movimiento_id'] !== 2) {
                throw new Exception("Solo se pueden devolver préstamos activos");
            }

            /* =========================
            RESTITUIR STOCK DISPONIBLE
            ========================= */
            $stmt = $conn->prepare(
                "UPDATE herramientas
                SET cantidad_disponible = cantidad_disponible + ?
                WHERE id = ?"
            );
            $stmt->bind_param("ii", $mov['cantidad'], $mov['herramienta_id']);
            $stmt->execute();

            /* =========================
            CERRAR MOVIMIENTO
            ========================= */
            $stmt = $conn->prepare(
                "UPDATE movimiento
                SET tipo_movimiento_id = 4,
                    fecha_devolucion = NOW(),
                    fecha_resolucion = NOW(),
                    activo = 0
                WHERE n_movimiento = ?"
            );
            $stmt->bind_param("i", $nMovimiento);
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