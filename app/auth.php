<?php
// app/auth.php

function auth_start_session(): void {
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }
}

function auth_login(array $user): void {
  auth_start_session();
  session_regenerate_id(true);
  $_SESSION['user'] = [
    'id' => (int)$user['id'],
    'username' => $user['username'],
    'rol' => $user['rol'],
  ];
}

function auth_user(): ?array {
  auth_start_session();
  return $_SESSION['user'] ?? null;
}

function auth_logout(): void {
  auth_start_session();
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
  }
  session_destroy();
}

function require_login(): void {
  if (!auth_user()) {
    header('Location: /Control-Acceso/auth/login.php');
    exit;
  }
}

function require_role(string $rol): void {
  $u = auth_user();
  if (!$u || $u['rol'] !== $rol) {
    http_response_code(403);
    echo "403 - No autorizado";
    exit;
  }
}

function csrf_token(): string {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}

function csrf_validate(?string $token): bool {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  return is_string($token)
    && isset($_SESSION['csrf_token'])
    && hash_equals($_SESSION['csrf_token'], $token);
}

function auth_role(): string {
  $u = auth_user();
  return strtoupper($u['rol'] ?? '');
}

function require_roles(array $allowed) {
  require_login();
  $role = auth_role();
  $allowed = array_map('strtoupper', $allowed);

  if (!in_array($role, $allowed, true)) {
    header('Location: /Control-Acceso/home.php');
    exit;
  }
}