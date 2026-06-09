<?php
require_once __DIR__ . '/app/bootstrap.php';
start_session();
$_csrf = csrf_token();
?>
<!doctype html>
<html lang="pt-BR">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="Permissions-Policy" content="camera=(), microphone=(), geolocation=(), payment=(), usb=()">
    <title>LabCon | Redefinir senha</title>
    <link rel="stylesheet" href="assets/css/styles.css">
  </head>
  <body>
    <main class="auth-page">
      <section class="auth-panel" aria-labelledby="reset-title">
        <div class="brand auth-brand">
          <span class="brand-mark">LC</span>
          <div>
            <strong>LabCon</strong>
            <small>Recuperacao de acesso</small>
          </div>
        </div>

        <form class="auth-form active" id="reset-form">
          <div>
            <p class="eyebrow">Nova senha</p>
            <h1 id="reset-title">Redefinir senha</h1>
          </div>
          <input id="reset-token" type="hidden" value="<?= htmlspecialchars($_GET['token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
          <div class="field">
            <label for="reset-password">Nova senha</label>
            <input id="reset-password" type="password" required autocomplete="new-password" minlength="8" maxlength="128">
          </div>
          <div class="field">
            <label for="reset-password-confirm">Confirmar senha</label>
            <input id="reset-password-confirm" type="password" required autocomplete="new-password" minlength="8" maxlength="128">
          </div>
          <button class="button primary" type="submit">Salvar nova senha</button>
        </form>

        <div class="auth-footer">
          <a href="login.php">Voltar ao login</a>
        </div>
      </section>
    </main>

    <div class="toast" id="toast" role="status" aria-live="polite"></div>
    <script src="src/config.js"></script>
    <script>window.LabConCsrfToken = <?= json_encode($_csrf) ?>;</script>
    <script>
      (function () {
        "use strict";

        const toast = document.querySelector("#toast");
        const cfg = window.LabConConfig || { toastDuration: 2600 };
        function showToast(message) {
          toast.textContent = message;
          toast.classList.add("show");
          window.clearTimeout(showToast._timer);
          showToast._timer = window.setTimeout(() => toast.classList.remove("show"), cfg.toastDuration);
        }

        document.querySelector("#reset-form").addEventListener("submit", async (event) => {
          event.preventDefault();
          const token = document.querySelector("#reset-token").value;
          const password = document.querySelector("#reset-password").value;
          const confirm = document.querySelector("#reset-password-confirm").value;
          if (!token) { showToast("Link de recuperacao invalido."); return; }
          if (password !== confirm) { showToast("As senhas nao conferem."); return; }

          try {
            const res = await fetch("api/auth.php", {
              method: "POST",
              headers: { "Content-Type": "application/json", "X-CSRF-Token": window.LabConCsrfToken || "" },
              body: JSON.stringify({ action: "resetPassword", token, password })
            });
            const result = await res.json();
            if (!result.success) { showToast(result.error || "Nao foi possivel redefinir a senha."); return; }
            showToast("Senha redefinida. Redirecionando para o login.");
            window.setTimeout(() => { window.location.href = "login.php"; }, 900);
          } catch {
            showToast("Erro de conexao.");
          }
        });
      }());
    </script>
  </body>
</html>
