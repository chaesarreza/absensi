<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Panel Guru' ?></title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="guru-panel">

<nav class="navbar navbar-expand-lg navbar-guru">
    <div class="container-fluid px-4">
        <a class="navbar-brand" href="index.php">
            <img src="../assets/img/logo.png" alt="Logo" style="height: 35px;" class="me-2">
            Guru Workspace
        </a>
        <div class="ms-auto d-flex align-items-center">
            <div id="theme-toggle" class="theme-toggle me-3">
                <i class="bi bi-moon-stars-fill"></i>
            </div>
            <a href="../logout.php" class="btn btn-outline-primary">Logout</a>
        </div>
    </div>
</nav>