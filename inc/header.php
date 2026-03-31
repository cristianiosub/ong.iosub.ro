<!DOCTYPE html>
<html lang="ro" class="h-full">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= htmlspecialchars($pageTitle ?? SITE_NAME) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <style>
    [x-cloak]{display:none!important}
    .gradient-hero{background:linear-gradient(135deg,#1e1b4b 0%,#312e81 40%,#4338ca 100%)}
    .card-hover{transition:transform .2s,box-shadow .2s}
    .card-hover:hover{transform:translateY(-2px);box-shadow:0 8px 30px rgba(0,0,0,.12)}
  </style>
</head>
<body class="h-full bg-gray-50 text-gray-800 flex flex-col">

<nav class="gradient-hero shadow-lg">
  <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between">
    <a href="/index.php" class="flex items-center gap-2 text-white font-bold text-lg tracking-tight">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
      </svg>
      ONG Search
    </a>
    <div class="flex items-center gap-4 text-sm text-indigo-200">
      <a href="/index.php" class="hover:text-white transition">Acasă</a>
      <a href="/admin/login.php" class="hover:text-white transition">Admin</a>
    </div>
  </div>
</nav>

<?php if (!empty($flashMsg)): ?>
<div class="max-w-6xl mx-auto px-4 mt-4 w-full">
  <div class="rounded-lg px-4 py-3 text-sm font-medium
    <?= $flashType === 'error' ? 'bg-red-100 text-red-800 border border-red-200' : 'bg-green-100 text-green-800 border border-green-200' ?>">
    <?= htmlspecialchars($flashMsg) ?>
  </div>
</div>
<?php endif; ?>

<main class="flex-1">
