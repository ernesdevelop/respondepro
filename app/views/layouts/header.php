<?php

declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'RespondePro'); ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars($publicAsset('assets/css/style.css'), ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>
    <header class="topbar">
        <div class="brand">
            <span class="brand-mark">RP</span>
            <div>
                <strong>RespondePro</strong>
                <small>Analisis de respuestas con IA</small>
            </div>
        </div>
    </header>
