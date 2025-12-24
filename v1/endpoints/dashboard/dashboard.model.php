<?php

class DashboardModel
{
    private function getConnection(): array
    {
        $con = new Conexion();
        return [$con, $con->getConnection()];
    }

    /* =========================
       KPIs / CARDS
       ========================= */
    private function getMetrics(): array
    {
        [$con, $conn] = $this->getConnection();

        try {
            $sql = "
                SELECT
                    (SELECT COUNT(*) 
                     FROM herramientas 
                     WHERE activo = 1) AS total_herramientas,

                    (SELECT COUNT(*) 
                     FROM movimiento m
                     JOIN tipo_movimiento tm ON tm.id = m.tipo_movimiento_id
                     WHERE tm.nombre = 'Préstamo'
                       AND m.fecha_devolucion IS NULL
                       AND m.activo = 1) AS prestamos_activos,

                    (SELECT COUNT(*)
                     FROM movimiento m
                     JOIN tipo_movimiento tm ON tm.id = m.tipo_movimiento_id
                     WHERE tm.nombre = 'Solicitado'
                       AND m.activo = 1) AS solicitudes_pendientes
            ";

            $rs = mysqli_query($conn, $sql);
            return mysqli_fetch_assoc($rs);

        } finally {
            $con->closeConnection();
        }
    }

    /* =========================
       TABLA MOVIMIENTOS
       ========================= */
    private function getMovimientos(): array
    {
        [$con, $conn] = $this->getConnection();

        $sql = "
            SELECT
                m.n_movimiento,
                h.n_parte,
                h.nombre              AS herramienta,
                u.rut                 AS usuario,
                l.nombre              AS lugar,
                tm.nombre             AS tipo_movimiento,
                m.fecha_prestamo,
                m.fecha_devolucion,
                m.cantidad
            FROM movimiento m
            JOIN herramientas h      ON h.id = m.herramienta_id
            JOIN usuarios u          ON u.id = m.usuario_id
            JOIN lugares l           ON l.id = m.lugar_id
            JOIN tipo_movimiento tm  ON tm.id = m.tipo_movimiento_id
            WHERE m.activo = 1
            ORDER BY m.fecha_prestamo DESC
            LIMIT 50
        ";

        try {
            $rs = mysqli_query($conn, $sql);
            return mysqli_fetch_all($rs, MYSQLI_ASSOC);

        } finally {
            $con->closeConnection();
        }
    }

    public function getDashboardData(): array
    {
        return [
            "cards" => $this->getMetrics(),
            "tabla" => $this->getMovimientos()
        ];
    }
}