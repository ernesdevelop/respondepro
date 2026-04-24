<?php

declare(strict_types=1);

$pageTitle = 'RespondePro';
$appConfig = require dirname(__DIR__, 3) . '/config/app.php';
$detectedBaseUrl = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$baseUrl = $appConfig['base_url'] !== '' ? $appConfig['base_url'] : ($detectedBaseUrl === '/' ? '' : $detectedBaseUrl);
$publicAsset = static function (string $path) use ($baseUrl): string {
    return ($baseUrl !== '' ? $baseUrl : '') . '/' . ltrim($path, '/');
};

require dirname(__DIR__) . '/layouts/header.php';
?>
<main class="container">
    <section class="hero">
        <div>
            <p class="eyebrow">Analisis inteligente de mensajes</p>
            <h1>Convierte objeciones en respuestas claras y listas para copiar.</h1>
            <p class="hero-copy">
                Pega un mensaje real de cliente, recibe una categoria sugerida y genera 3 respuestas utiles
                segun el contexto comercial, emocional o de redes.
            </p>
        </div>
        <div class="usage-card">
            <span>Consultas de hoy</span>
            <strong id="usageCounter"><?= (int) $usage['used']; ?> / <?= (int) $usage['limit']; ?></strong>
            <small>Limite preparado para futuras cuentas de usuario.</small>
        </div>
    </section>

    <section class="panel">
        <form id="analysisForm" class="analysis-form">
            <label for="mensaje">Mensaje del cliente</label>
            <textarea
                id="mensaje"
                name="mensaje"
                rows="7"
                maxlength="2000"
                placeholder="Ej: El cliente me dijo que le parece caro y que quiere pensarlo."
                required
            ></textarea>

            <div class="actions">
                <button type="submit" id="analyzeBtn">Analizar</button>
                <button type="button" id="generateBtn" class="secondary" disabled>Generar respuestas</button>
            </div>
        </form>

        <div id="feedback" class="feedback hidden"></div>

        <section id="resultPanel" class="result-panel hidden">
            <div class="result-grid">
                <article class="result-box">
                    <span class="label">Categoria sugerida</span>
                    <strong id="categoriaSugerida">-</strong>
                </article>

                <article class="result-box">
                    <span class="label">Tono detectado</span>
                    <strong id="tonoDetectado">-</strong>
                </article>
            </div>

            <div class="selector-row">
                <label for="categoriaSelect">Categoria aplicada</label>
                <select id="categoriaSelect" name="categoria">
                    <option value="ventas">ventas</option>
                    <option value="emocional">emocional</option>
                    <option value="redes">redes</option>
                </select>
            </div>

            <article class="explanation-box">
                <span class="label">Motivo de la sugerencia</span>
                <p id="explicacionCategoria">-</p>
            </article>

            <section>
                <div class="section-head">
                    <h2>Respuestas generadas</h2>
                    <span>3 opciones copiables</span>
                </div>
                <div id="responsesList" class="responses-list"></div>
            </section>
        </section>
    </section>

    <section class="panel history-panel">
        <div class="section-head">
            <h2>Historial reciente</h2>
            <span>Ultimas consultas guardadas en MySQL</span>
        </div>

        <?php if ($historial !== []): ?>
            <div class="history-list">
                <?php foreach ($historial as $item): ?>
                    <article class="history-item">
                        <strong><?= htmlspecialchars((string) $item['categoria']); ?></strong>
                        <p><?= htmlspecialchars((string) $item['mensaje']); ?></p>
                        <small><?= htmlspecialchars((string) $item['fecha']); ?></small>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="empty-state">Todavia no hay consultas guardadas.</p>
        <?php endif; ?>
    </section>

    <section class="foot-note">
        <p>Autenticacion futura: la aplicacion ya usa sesiones y puede enlazar consultas y limites a usuarios cuando se agregue login.</p>
    </section>
</main>

<script>
    window.RespondePro = {
        analyzeUrl: '<?= htmlspecialchars(($baseUrl !== '' ? $baseUrl : '') . '/analizar', ENT_QUOTES, 'UTF-8'); ?>'
    };
</script>
<script src="<?= htmlspecialchars($publicAsset('assets/js/app.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
