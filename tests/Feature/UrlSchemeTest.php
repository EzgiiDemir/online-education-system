<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Generated URLs follow APP_URL. The first production server ran on plain
 * http (no certificate yet) while the code forced https in production, so
 * every stylesheet, form and link pointed at a port nobody was listening on.
 */
class UrlSchemeTest extends TestCase
{
    private function bootWith(string $appUrl, string $env = 'production'): void
    {
        config(['app.url' => $appUrl]);
        $this->app['env'] = $env;
        URL::forceScheme(null);
        URL::forceRootUrl($appUrl);
        (new AppServiceProvider($this->app))->boot();
    }

    public function test_an_http_production_server_generates_http_urls(): void
    {
        $this->bootWith('http://ecenter.arucad.edu.tr');

        $this->assertStringStartsWith('http://ecenter.arucad.edu.tr/', asset('vendor/bootstrap/bootstrap.custom.min.css'));
        $this->assertStringStartsWith('http://', route('login'));
    }

    public function test_an_https_app_url_forces_https_everywhere(): void
    {
        $this->bootWith('https://ecenter.arucad.edu.tr');

        $this->assertStringStartsWith('https://ecenter.arucad.edu.tr/', asset('vendor/bootstrap/bootstrap.custom.min.css'));
        $this->assertStringStartsWith('https://', route('login'));
    }
}
