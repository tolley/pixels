<?php

function _jwtB64Encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function _jwtB64Decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/'));
}

function _jwtSecret(): string {
    return getenv('JWT_SECRET') ?: 'dev-secret-change-in-production';
}

function jwtEncode(array $payload): string {
    $header = _jwtB64Encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $body   = _jwtB64Encode(json_encode($payload));
    $sig    = _jwtB64Encode(hash_hmac('sha256', "$header.$body", _jwtSecret(), true));
    return "$header.$body.$sig";
}

function jwtDecode(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;

    [$header, $body, $sig] = $parts;
    $expected = _jwtB64Encode(hash_hmac('sha256', "$header.$body", _jwtSecret(), true));
    if (!hash_equals($expected, $sig)) return null;

    $data = json_decode(_jwtB64Decode($body), true);
    if (!is_array($data)) return null;
    if (isset($data['exp']) && $data['exp'] < time()) return null;

    return $data;
}

function jwtFromRequest(): ?array {
    $token = $_COOKIE['jwt'] ?? null;
    if (!$token) return null;
    return jwtDecode($token);
}

function jwtSetCookie(int $userId, string $username, bool $isAdmin = false, bool $emailAllowed = true ): void {
    $exp = time() + 86400 * 7;
    setcookie('jwt', jwtEncode([
        'sub'           => $userId,
        'username'      => $username,
        'email_allowed' => $emailAllowed,
        'is_admin'      => $isAdmin,
        'iat'           => time(),
        'exp'           => $exp,
    ]), [
        'expires'  => $exp,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

function jwtClearCookie(): void {
    setcookie('jwt', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}
