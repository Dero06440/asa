<?php
// Echappe une valeur pour l'affichage HTML
function h(?string $str): string {
    return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
}

// Formate un montant en euros
function euros(?float $amount): string {
    if ($amount === null) return '—';
    return number_format($amount, 2, ',', ' ') . ' €';
}

// Formate une surface
function surface(?float $m2): string {
    if ($m2 === null) return '—';
    return number_format($m2, 0, ',', ' ') . ' m²';
}

// Formate une date FR depuis une date SQL (YYYY-MM-DD)
function dateFR(?string $date): string {
    if (empty($date)) return '—';
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d ? $d->format('d/m/Y') : $date;
}

// Pagination : retourne le numéro de page courant
function currentPage(): int {
    return max(1, (int) ($_GET['page'] ?? 1));
}

// Génère les paramètres GET actuels sans 'page' (pour les liens de pagination)
function queryStringWithout(array $exclude = ['page']): string {
    $params = $_GET;
    foreach ($exclude as $key) unset($params[$key]);
    return $params ? '?' . http_build_query($params) . '&' : '?';
}

// Retourne un message flash (stocké en session)
function flashGet(): ?array {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function flashSet(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

// Affiche le message flash Bootstrap
function flashRender(): string {
    $flash = flashGet();
    if (!$flash) return '';
    $icon = $flash['type'] === 'success' ? '✅' : ($flash['type'] === 'danger' ? '❌' : 'ℹ️');
    return '<div class="alert alert-' . h($flash['type']) . ' alert-dismissible fade show" role="alert">'
         . $icon . ' ' . h($flash['message'])
         . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>'
         . '</div>';
}

function tableExists(PDO $db, string $tableName): bool {
    $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $stmt->execute([$tableName]);
    return (int) $stmt->fetchColumn() > 0;
}
