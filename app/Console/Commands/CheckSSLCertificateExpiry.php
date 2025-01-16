<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Hosting; // Make sure to import your Hosting model
use Illuminate\Support\Facades\Log;

class CheckSSLCertificateExpiry extends Command
{
    protected $signature = 'ssl:check-expiry {--filter=}';
    protected $description = 'Check SSL expiry date for all hosting domains and save it to the Hosting model';

    public function handle()
    {
        $hostings = Hosting::excludeIgnored()
            ->whereNull('ssl_expiry_date')
            ->orWhere('ssl_expiry_date', '<=', now()->endOfDay()->format('Y-m-d')); // Retrieve all Hosting records

        $filter = $this->option('filter');
        if ($filter) {
            $hostings = $hostings->where('domain', 'like', '%' . $filter . '%');
        }

        $hostings = $hostings->get();

        foreach ($hostings as $hosting) {
            $domain = $hosting->domain; // Assuming 'domain' is the attribute in your Hosting model

            try {
                $expiryDate = $this->getSSLCertificateExpiryDate($domain);
                $hosting->ssl_expiry_date = $expiryDate; // Assuming 'ssl_expiry_date' is the attribute in your Hosting model
                $hosting->save();

                if ($hosting->ssl_expiry_date->lte(now())) {
                    $this->error("Updated SSL expiry date for $domain: $expiryDate");
                } else {
                    $this->info("Updated SSL expiry date for $domain: $expiryDate");
                }
            } catch (\Exception $e) {
                Log::error("Error checking SSL for $domain: " . $e->getMessage());
                $this->error("Error checking SSL for $domain: " . $e->getMessage());
            }
        }
    }

    private function getSSLCertificateExpiryDate($domain, $port = 443)
    {
        $context = stream_context_create([
            "ssl" => [
                "capture_peer_cert" => true,
            ],
        ]);

        $socket = @stream_socket_client("ssl://$domain:$port", $errno, $errstr, 30, STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT, $context);

        if (!$socket) {
            throw new \Exception("Error: $errstr ($errno)");
        }

        $params = stream_context_get_params($socket);
        $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
        fclose($socket);

        return date('Y-m-d H:i:s', $cert['validTo_time_t']);
    }
}
