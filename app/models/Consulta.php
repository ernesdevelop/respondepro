<?php

declare(strict_types=1);

class Consulta
{
    public function __construct(private PDO $pdo)
    {
    }

    public function guardarConsulta(string $mensaje, string $categoria): bool
    {
        $sql = 'INSERT INTO consultas (mensaje, categoria) VALUES (:mensaje, :categoria)';
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            'mensaje' => $mensaje,
            'categoria' => $categoria,
        ]);
    }

    public function obtenerHistorial(int $limite = 10): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, mensaje, categoria, fecha FROM consultas ORDER BY fecha DESC LIMIT :limite'
        );
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
