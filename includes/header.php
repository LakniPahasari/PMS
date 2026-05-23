<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'PharmaTrack' ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<?php require_once __DIR__ . '/sidebar.php'; ?>

<div class="main-wrapper">
    <header class="top-header">
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">&#9776;</button>
        <h1 class="page-title"><?= $pageTitle ?? '' ?></h1>
        <div class="header-right">
            <span class="header-branch">📍 <?= htmlspecialchars(currentUser()['branch']) ?></span>
        </div>
    </header>

    <main class="main-content">
