<?php
/**
 * PBX Client — Centralized logic for interacting with Ovijat PBX (FreePBX)
 */

class PbxClient {
    private $url;
    private $username;
    private $password;
    private $cookieFile;
    private $lastError;

    public function __construct(string $url, string $username, string $password) {
        $this->url      = rtrim($url, '/');
        $this->username = $username;
        $this->password = $password;
        $this->cookieFile = sys_get_temp_dir() . '/pbx_cc_' . md5($this->username . $this->url) . '.txt';
    }

    /**
     * Get the last error message
     */
    public function getLastError(): ?string {
        return $this->lastError;
    }

    public function getCookieFile(): string {
        return $this->cookieFile;
    }

    /**
     * Ensure we are logged in to the PBX
     */
    public function login(bool $force = false): bool {
        if (!$force && file_exists($this->cookieFile) && (time() - filemtime($this->cookieFile)) < 3600) {
            return true; // Assume session still valid
        }

        $loginUrl = $this->url . '/core/user_settings/user_dashboard.php';
        $res = $this->request($loginUrl, [
            'username' => $this->username,
            'password' => $this->password
        ]);

        if (isset($res['error'])) {
            $this->lastError = "Login Request Failed: " . $res['error'];
            return false;
        }

        if (stripos($res['body'], 'logout') !== false || stripos($res['body'], 'user_dashboard') !== false) {
            touch($this->cookieFile);
            return true;
        }

        $this->lastError = "Login failed: Invalid credentials or PBX response";
        return false;
    }

    /**
     * Perform an HTTP request to the PBX
     */
    public function request(string $url, ?array $post = null): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_COOKIEJAR      => $this->cookieFile,
            CURLOPT_COOKIEFILE     => $this->cookieFile,
        ]);

        if ($post) {
            curl_setopt($ch, CURLOPT_POST,       true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        }

        $body  = curl_exec($ch);
        $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err   = curl_error($ch);
        curl_close($ch);

        if ($err)        return ['error' => $err];
        if ($code !== 200) return ['error' => "HTTP $code from PBX"];
        return ['body' => $body];
    }

    /**
     * Stream a file directly to the browser
     */
    public function stream(string $url, string $mime, string $filename, bool $asAttachment = false): void {
        if ($asAttachment) {
            header('Content-Disposition: attachment; filename="' . $filename . '"');
        } else {
            header('Content-Disposition: inline; filename="' . $filename . '"');
        }
        header('Content-Type: ' . $mime);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_COOKIEFILE     => $this->cookieFile,
            CURLOPT_USERAGENT      => 'Mozilla/5.0',
            CURLOPT_WRITEFUNCTION  => function($ch, $data) { echo $data; return strlen($data); },
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}
