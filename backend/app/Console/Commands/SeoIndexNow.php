<?php

namespace App\Console\Commands;

use Google\Client;
use Google\Service\Indexing;
use Google\Service\Indexing\UrlNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SeoIndexNow extends Command
{
    protected $signature = 'seo:indexnow';
    protected $description = 'Submit all sitemap URLs to IndexNow and Google Indexing API';

    public function handle(): void
    {
        $xml  = simplexml_load_file(public_path('sitemap.xml'));
        $urls = collect(iterator_to_array($xml->url, false))->map(fn($u) => (string) $u->loc)->values()->all();
        $this->submitToIndexNow($urls);
        $this->submitToGoogleIndexing($urls);
    }

    private function submitToIndexNow(array $urls): void
    {
        $key  = config('app.indexnow_key');
        $host = 'sql-designer.com';

        $response = Http::post('https://api.indexnow.org/indexnow', [
            'host'        => $host,
            'key'         => $key,
            'keyLocation' => "https://{$host}/{$key}.txt",
            'urlList'     => $urls,
        ]);

        if ($response->successful() || $response->status() === 202) {
            $this->info("IndexNow: submitted " . count($urls) . " URLs (HTTP {$response->status()}).");
        } else {
            $this->error("IndexNow: HTTP {$response->status()}: " . $response->body());
        }
    }

    private function submitToGoogleIndexing(array $urls): void
    {
        $keyPath = config('app.google_indexing_sa_key_path');

        if (!$keyPath || !file_exists($keyPath)) {
            $this->warn('Google Indexing: SA key file not configured or not found, skipping.');
            return;
        }

        $client = new Client();
        $client->setAuthConfig($keyPath);
        $client->addScope('https://www.googleapis.com/auth/indexing');

        $service = new Indexing($client);

        $ok   = 0;
        $fail = 0;

        foreach ($urls as $url) {
            try {
                $notification = new UrlNotification();
                $notification->setUrl($url);
                $notification->setType('URL_UPDATED');
                $service->urlNotifications->publish($notification);
                $ok++;
            } catch (\Exception $e) {
                $this->error("Google Indexing [{$url}]: " . $e->getMessage());
                $fail++;
            }
        }

        $this->info("Google Indexing: {$ok} submitted, {$fail} failed.");
    }
}
