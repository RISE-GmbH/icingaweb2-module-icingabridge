<?php
// SPDX-FileCopyrightText: 2026 Research Industrial Systems Engineering (RISE) Forschungs-, Entwicklungs- und Großprojektberatung GmbH
// SPDX-License-Identifier: GPL-3.0-or-later
// AI-assisted: OpenAI ChatGPT (GPT-5.6 Sol)

namespace Icinga\Module\Icingabridge;

trait SSLHashUtil
{
    protected function getSSLHash(string $url)
    {
        $urlParts = parse_url($url);

        $host = $urlParts['host'];
        $port = $urlParts['port'] ?? "443";

        // Create stream context to capture certificate
        $context = stream_context_create([
            "ssl" => [
                "capture_peer_cert" => true,
                "verify_peer" => false,
                "verify_peer_name" => false,
            ]
        ]);

        // Open SSL connection
        $client = stream_socket_client(
            "ssl://{$host}:{$port}",
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$client) {
            return "UNKNOWN";
        }

        // Extract certificate
        $params = stream_context_get_params($client);
        $cert = $params["options"]["ssl"]["peer_certificate"];

        // Export certificate to string
        openssl_x509_export($cert, $certout);

        // Convert to fingerprint (SHA-256 recommended)
        $fingerprint = openssl_x509_fingerprint($cert, "sha256");

        return strtoupper($fingerprint);
    }

}