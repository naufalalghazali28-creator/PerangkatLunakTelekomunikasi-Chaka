<?php

namespace App\Services;

use App\Models\BEMS\Node;
use Illuminate\Support\Facades\Log;

class MqttService
{
    /**
     * Publish dummy data ke MQTT broker.
     * Menggunakan php-mqtt/laravel-client (PhpMqtt\Client\Facades\MQTT).
     * Jika broker tidak terjangkau, return false tanpa crash.
     */
    public function publishDummy(Node $node): bool
    {
        try {
            $config   = $node->config ?? [];
            $broker   = $config['broker']   ?? config('mqtt.connections.default.host', '127.0.0.1');
            $port     = (int)($config['port'] ?? config('mqtt.connections.default.port', 1883));

            // Gunakan facade dari php-mqtt/laravel-client
            $mqtt = new \PhpMqtt\Client\MqttClient(
                $broker,
                $port,
                'chaka_op_' . $node->id . '_' . uniqid()
            );

            $settings = (new \PhpMqtt\Client\ConnectionSettings())
                ->setConnectTimeout(3)
                ->setUseTls(false);

            if (!empty($config['username'])) {
                $settings->setUsername($config['username'])
                         ->setPassword($config['password'] ?? '');
            }

            $mqtt->connect($settings, true);
            $payload = json_encode($this->generateDummyPayload($node->node_type));
            $mqtt->publish($node->mqtt_topic, $payload, 0);
            $mqtt->disconnect();

            Log::info("MQTT published to {$node->mqtt_topic}");
            return true;

        } catch (\Throwable $e) {
            Log::warning("MQTT gagal untuk node #{$node->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate dummy payload — selalu tersedia meski MQTT offline.
     */
    public function generateDummyPayload(string $type): array
    {
        return match($type) {
            'temperature' => [
                'suhu'       => round(mt_rand(180, 350) / 10, 1),
                'kelembaban' => round(mt_rand(400, 900) / 10, 1),
                'unit_suhu'  => '°C',
                'unit_humid' => '%',
                'timestamp'  => now()->toIso8601String(),
            ],
            'current' => [
                'arus'      => round(mt_rand(5, 150) / 10, 2),
                'unit'      => 'A',
                'timestamp' => now()->toIso8601String(),
            ],
            'voltage' => [
                'tegangan'  => round(mt_rand(2100, 2400) / 10, 1),
                'unit'      => 'V',
                'timestamp' => now()->toIso8601String(),
            ],
            'light' => [
                'cahaya'    => mt_rand(100, 1000),
                'unit'      => 'lux',
                'timestamp' => now()->toIso8601String(),
            ],
            default => [
                'value'     => round(mt_rand(0, 1000) / 10, 1),
                'timestamp' => now()->toIso8601String(),
            ],
        };
    }
}