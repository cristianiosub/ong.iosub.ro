<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
initDb();
sessionStart();

if (isAdmin()) { header('Location: /admin/dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    $row  = dbOne("SELECT * FROM `" . T_ADMIN . "` WHERE username = ?", [$user]);

    if ($row && password_verify($pass, $row['password_hash'])) {
        $_SESSION[ADMIN_SESSION_KEY]          = true;
        $_SESSION[ADMIN_SESSION_KEY . '_user'] = $user;
        header('Location: /admin/dashboard.php'); exit;
    }
    $error = 'Credențiale incorecte.';
}
?>
<!DOCTYPE html>
<html lang="ro" class="h-full">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Login – <?= SITE_NAME ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>.gradient-bg{background:linear-gradient(135deg,#1e1b4b 0%,#312e81 50%,#4338ca 100%)}</style>
</head>
<body class="h-full gradient-bg flex items-center justify-center p-4">
  <div class="w-full max-w-sm">
    <div class="text-center mb-8">
      <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-white/10 backdrop-blur mb-4">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
        </svg>
      </div>
      <h1 class="text-2xl font-bold text-white">Panou Admin</h1>
      <p class="text-indigo-300 text-sm mt-1"><?= SITE_NAME ?></p>
    </div>

    <div class="bg-white rounded-2xl shadow-2xl p-8">
      <?php if ($error): ?>
        <div class="mb-4 px-4 py-3 rounded-lg text-sm font-medium bg-red-50 text-red-700 border border-red-200">
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST" class="space-y-5">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Utilizator</label>
          <input type="text" name="username" required autofocus
            class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"/>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Parolă</label>
          <input type="password" name="password" required
            class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"/>
        </div>
        <button type="submit"
          class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 rounded-xl transition text-sm">
          Autentificare
        </button>
      </form>
      <p class="text-center text-xs text-gray-400 mt-6">
        <a href="/index.php" class="text-indigo-500 hover:text-indigo-700">← Înapoi la site</a>
      </p>
    </div>
  </div>
</body>
</html>
