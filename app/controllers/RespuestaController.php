<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__) . '/models/Consulta.php';
require_once dirname(__DIR__) . '/models/Uso.php';

class RespuestaController
{
    private Consulta $consultaModel;
    private Uso $usoModel;
    private array $config;

    public function __construct()
    {
        $this->config = require dirname(__DIR__, 2) . '/config/app.php';
        $pdo = database();

        $this->consultaModel = new Consulta($pdo);
        $this->usoModel = new Uso($this->config['usage_limit_per_day']);
    }

    public function index(): void
    {
        $historial = $this->consultaModel->obtenerHistorial();
        $usage = $this->usoModel->obtenerEstado();
        $authReady = [
            'enabled' => false,
            'message' => 'La estructura ya usa sesiones y puede asociar el limite a un usuario cuando se agregue autenticacion.',
        ];

        require dirname(__DIR__) . '/views/respuesta/index.php';
    }

    public function analizar(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Metodo no permitido.',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $raw = file_get_contents('php://input');
        $payload = json_decode($raw ?: '', true);
        if (!is_array($payload)) {
            $payload = $_POST;
        }

        $mensaje = trim((string) ($payload['mensaje'] ?? ''));
        $categoriaElegida = trim((string) ($payload['categoria'] ?? ''));

        if ($mensaje === '') {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'El mensaje es obligatorio.',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (mb_strlen($mensaje) < 6) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Escribe un mensaje mas descriptivo para analizarlo.',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($this->usoModel->excedioLimite()) {
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'message' => 'Has alcanzado el limite diario de consultas.',
                'usage' => $this->usoModel->obtenerEstado(),
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $resultado = $this->procesarAnalisis($mensaje, $categoriaElegida);
            $this->consultaModel->guardarConsulta($mensaje, $resultado['categoria_aplicada']);
            $this->usoModel->registrarUso();

            echo json_encode([
                'success' => true,
                'message' => 'Analisis completado.',
                'data' => $resultado,
                'usage' => $this->usoModel->obtenerEstado(),
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $exception) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'No fue posible procesar la solicitud.',
                'error' => $exception->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    private function procesarAnalisis(string $mensaje, string $categoriaElegida): array
    {
        $resultado = $this->consultarIA($mensaje, $categoriaElegida);
        $resultado['categoria_sugerida'] = $this->normalizarCategoria($resultado['categoria_sugerida'] ?? '');

        $categoriaAplicada = $categoriaElegida !== ''
            ? $this->normalizarCategoria($categoriaElegida)
            : $resultado['categoria_sugerida'];

        if ($categoriaAplicada === '') {
            $categoriaAplicada = 'ventas';
        }

        $resultado['categoria_aplicada'] = $categoriaAplicada;
        $resultado['tono'] = trim((string) ($resultado['tono'] ?? 'neutral'));
        $resultado['explicacion'] = trim((string) ($resultado['explicacion'] ?? 'La IA detecto esta categoria por el contexto general del mensaje.'));
        $resultado['respuestas'] = $this->sanitizarRespuestas($resultado['respuestas'] ?? [], $mensaje, $categoriaAplicada);

        return $resultado;
    }

    private function consultarIA(string $mensaje, string $categoriaElegida): array
    {
        $apiKey = $this->config['openai']['key'];
        if ($apiKey === '' || !function_exists('curl_init')) {
            return $this->analisisLocal($mensaje, $categoriaElegida);
        }

        $prompt = $this->crearPrompt($mensaje, $categoriaElegida);
        $payload = [
            'model' => $this->config['openai']['model'],
            'temperature' => 0.7,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Eres un asistente comercial. Responde siempre con JSON valido.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
        ];

        $ch = curl_init($this->config['openai']['url']);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => $this->config['openai']['timeout'],
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $curlError !== '') {
            return $this->analisisLocal($mensaje, $categoriaElegida);
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException('La API de IA devolvio un estado no valido: ' . $statusCode);
        }

        $decoded = json_decode($response, true);
        $content = $decoded['choices'][0]['message']['content'] ?? '';
        $parsed = json_decode((string) $content, true);

        if (!is_array($parsed)) {
            throw new RuntimeException('No se pudo interpretar la respuesta de la IA.');
        }

        return $parsed;
    }

    private function crearPrompt(string $mensaje, string $categoriaElegida): string
    {
        $categoriaTexto = $categoriaElegida !== ''
            ? 'La categoria elegida manualmente por el usuario es: ' . $this->normalizarCategoria($categoriaElegida) . '. Genera las respuestas siguiendo esa categoria.'
            : 'Sugiere la mejor categoria y genera las respuestas basadas en esa categoria.';

        return <<<PROMPT
Analiza el siguiente mensaje de cliente: "{$mensaje}".

Necesito que:
1. Clasifiques el mensaje en una de estas categorias exactas: ventas, emocional, redes.
2. Detectes el tono principal.
3. Expliques brevemente por que sugieres esa categoria.
4. Generes exactamente 3 respuestas breves, utiles, profesionales y copiables en espanol.
5. Si el usuario ya eligio una categoria manual, respeta esa categoria al generar las respuestas.

{$categoriaTexto}

Devuelve solo JSON valido con esta estructura:
{
  "categoria_sugerida": "ventas|emocional|redes",
  "tono": "string",
  "explicacion": "string",
  "respuestas": ["respuesta 1", "respuesta 2", "respuesta 3"]
}
PROMPT;
    }

    private function analisisLocal(string $mensaje, string $categoriaElegida): array
    {
        $categoriaSugerida = $this->detectarCategoriaLocal($mensaje);
        $categoriaAplicada = $categoriaElegida !== ''
            ? $this->normalizarCategoria($categoriaElegida)
            : $categoriaSugerida;

        $tono = $this->detectarTonoLocal($mensaje);

        return [
            'categoria_sugerida' => $categoriaSugerida,
            'tono' => $tono,
            'explicacion' => 'Se utilizo un analisis local de respaldo para mantener la app operativa sin credenciales de IA.',
            'respuestas' => $this->generarRespuestasLocal($mensaje, $categoriaAplicada, $tono),
        ];
    }

    private function detectarCategoriaLocal(string $mensaje): string
    {
        $mensaje = mb_strtolower($mensaje);

        $mapa = [
            'ventas' => ['precio', 'caro', 'descuento', 'presupuesto', 'comprar', 'costo', 'pago', 'oferta'],
            'emocional' => ['miedo', 'duda', 'confianza', 'angustia', 'preocupado', 'inseguro', 'nervioso', 'no se'],
            'redes' => ['instagram', 'facebook', 'whatsapp', 'comentario', 'mensaje directo', 'dm', 'publicacion', 'redes'],
        ];

        foreach ($mapa as $categoria => $palabras) {
            foreach ($palabras as $palabra) {
                if (str_contains($mensaje, $palabra)) {
                    return $categoria;
                }
            }
        }

        return 'ventas';
    }

    private function detectarTonoLocal(string $mensaje): string
    {
        $mensaje = mb_strtolower($mensaje);

        if (preg_match('/(caro|no puedo|dif[ií]cil|problema|complicado)/u', $mensaje) === 1) {
            return 'objecion';
        }

        if (preg_match('/(miedo|duda|preocup|nervioso|angust)/u', $mensaje) === 1) {
            return 'sensible';
        }

        if (preg_match('/(hola|info|quiero|me interesa)/u', $mensaje) === 1) {
            return 'interesado';
        }

        return 'neutral';
    }

    private function generarRespuestasLocal(string $mensaje, string $categoria, string $tono): array
    {
        $templates = [
            'ventas' => [
                'Entiendo que el precio importa. Si quieres, te muestro el valor concreto que recibes para que compares con claridad.',
                'Gracias por comentarlo. Podemos revisar una opcion que se ajuste mejor a tu presupuesto sin perder calidad.',
                'Es una duda muy comun. Si te parece, te explico en un minuto por que esta propuesta termina siendo rentable.',
            ],
            'emocional' => [
                'Te entiendo, es normal sentir esa duda. Estoy aqui para ayudarte a tomar una decision con calma y sin presion.',
                'Gracias por decirme como te sientes. Podemos revisar paso a paso lo que te preocupa para que tengas mas claridad.',
                'Lo importante es que te sientas seguro con la decision. Si quieres, resolvemos juntos cada inquietud antes de avanzar.',
            ],
            'redes' => [
                'Gracias por escribirnos. Cuéntanos un poco mas y te respondemos con una opcion clara y personalizada.',
                'Hola, gracias por tu mensaje. Si quieres, te comparto por aqui la informacion mas importante para ayudarte rapido.',
                'Gracias por contactarnos por redes. Te puedo resumir las mejores opciones segun lo que necesitas.',
            ],
        ];

        $respuestas = $templates[$categoria] ?? $templates['ventas'];

        if ($tono === 'objecion') {
            $respuestas[0] = 'Entiendo la objecion. Antes de decidir, te comparto de forma simple que incluye la propuesta y por que puede convenirte.';
        }

        return $respuestas;
    }

    private function sanitizarRespuestas(array $respuestas, string $mensaje, string $categoria): array
    {
        $respuestas = array_values(array_filter(array_map(
            static fn ($respuesta) => trim((string) $respuesta),
            $respuestas
        )));

        if (count($respuestas) === 3) {
            return $respuestas;
        }

        return $this->generarRespuestasLocal($mensaje, $categoria, 'neutral');
    }

    private function normalizarCategoria(string $categoria): string
    {
        $categoria = mb_strtolower(trim($categoria));
        $permitidas = ['ventas', 'emocional', 'redes'];

        return in_array($categoria, $permitidas, true) ? $categoria : '';
    }
}
