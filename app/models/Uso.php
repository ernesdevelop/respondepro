<?php

declare(strict_types=1);

class Uso
{
    public function __construct(private int $limiteDiario)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['usage_tracker'] ??= [];
    }

    public function obtenerEstado(): array
    {
        $hoy = date('Y-m-d');
        $usadas = (int) ($_SESSION['usage_tracker'][$hoy] ?? 0);

        return [
            'used' => $usadas,
            'limit' => $this->limiteDiario,
            'remaining' => max(0, $this->limiteDiario - $usadas),
            'scope' => 'session',
            'ready_for_user_auth' => true,
        ];
    }

    public function excedioLimite(): bool
    {
        $estado = $this->obtenerEstado();

        return $estado['used'] >= $estado['limit'];
    }

    public function registrarUso(): void
    {
        $hoy = date('Y-m-d');
        $_SESSION['usage_tracker'][$hoy] = (int) ($_SESSION['usage_tracker'][$hoy] ?? 0) + 1;
    }
}
