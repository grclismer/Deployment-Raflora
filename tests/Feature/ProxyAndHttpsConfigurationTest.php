<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class ProxyAndHttpsConfigurationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Register a dedicated inspection route to verify request state through the full kernel lifecycle
        Route::get('/_test/proxy-inspection', function (Request $request) {
            return response()->json([
                'ip' => $request->ip(),
                'is_secure' => $request->isSecure(),
                'scheme' => $request->getScheme(),
                'host' => $request->getHost(),
                'url' => $request->url(),
                'full_url' => $request->fullUrl(),
                'generated_login_url' => route('login'),
                'generated_home_url' => route('home'),
            ]);
        })->middleware('web');
    }

    public function test_trusted_proxies_is_defined_in_laravel_configuration(): void
    {
        $this->assertSame('*', config('trustedproxy.proxies'));
        $this->assertSame('*', config('app.trusted_proxies'));
    }

    public function test_https_is_detected_from_trusted_forwarded_proto_header(): void
    {
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '10.0.15.22', // Proxy IP (e.g. Railway edge)
            'HTTP_X_FORWARDED_FOR' => '203.0.113.195',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
        ])->get('https://raflora.up.railway.app/_test/proxy-inspection');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertTrue($data['is_secure'], 'Request should be recognized as secure HTTPS behind trusted proxy.');
        $this->assertSame('https', $data['scheme']);
        $this->assertSame('raflora.up.railway.app', $data['host']);
        $this->assertStringStartsWith('https://raflora.up.railway.app', $data['url']);
        $this->assertStringStartsWith('https://raflora.up.railway.app', $data['generated_login_url']);
    }

    public function test_client_ip_is_resolved_from_forwarded_for_behind_proxy(): void
    {
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '10.0.15.22', // Internal proxy IP
            'HTTP_X_FORWARDED_FOR' => '198.51.100.42, 10.0.15.22',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ])->get('https://raflora.up.railway.app/_test/proxy-inspection');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertSame('198.51.100.42', $data['ip'], 'Real client IP should be resolved from X-Forwarded-For instead of proxy IP.');
    }

    public function test_untrusted_header_x_forwarded_host_is_ignored_preventing_host_spoofing(): void
    {
        // An attacker attempts Host Header Injection / Cache Poisoning by injecting X-Forwarded-Host
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '10.0.15.22',
            'HTTP_X_FORWARDED_HOST' => 'malicious-phishing-site.example.com',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.195',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ])->get('https://raflora.up.railway.app/_test/proxy-inspection');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertSame('raflora.up.railway.app', $data['host'], 'Host must come from legitimate HTTP_HOST, not untrusted X-Forwarded-Host.');
        $this->assertStringNotContainsString('malicious-phishing-site.example.com', $data['url']);
        $this->assertStringNotContainsString('malicious-phishing-site.example.com', $data['generated_login_url']);
    }

    public function test_local_development_requests_without_proxy_headers_remain_compatible(): void
    {
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
        ])->get('http://localhost:8000/_test/proxy-inspection');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertFalse($data['is_secure'], 'Local HTTP request without proxy headers should remain HTTP.');
        $this->assertSame('http', $data['scheme']);
        $this->assertSame('127.0.0.1', $data['ip']);
        $this->assertSame('localhost', $data['host']);
        $this->assertStringStartsWith('http://localhost:8000', $data['url']);
    }

    public function test_session_cookie_auto_enables_secure_attribute_on_https_requests(): void
    {
        Config::set('session.secure', null); // default auto-detect behavior

        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '10.0.15.22',
            'HTTP_HOST' => 'raflora.up.railway.app',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.195',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ])->get('/about');

        $response->assertStatus(200);

        $sessionCookieName = config('session.cookie');
        $cookie = $response->getCookie($sessionCookieName);

        $this->assertNotNull($cookie, 'Session cookie should be attached by web middleware.');
        $this->assertTrue($cookie->isSecure(), 'Session cookie should have Secure flag auto-enabled on HTTPS.');
    }

    public function test_session_cookie_omits_secure_attribute_on_http_requests_for_local_dev(): void
    {
        Config::set('session.secure', null); // default auto-detect behavior

        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_HOST' => 'localhost:8000',
            'SERVER_PORT' => '8000',
        ])->get('/about');

        $response->assertStatus(200);

        $sessionCookieName = config('session.cookie');
        $cookie = $response->getCookie($sessionCookieName);

        $this->assertNotNull($cookie, 'Session cookie should be attached by web middleware.');
        $this->assertFalse($cookie->isSecure(), 'Session cookie should not have Secure flag on local HTTP development.');
    }

    public function test_explicit_session_secure_cookie_configuration_enforces_secure_flag(): void
    {
        Config::set('session.secure', true);

        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_HOST' => 'localhost',
        ])->get('/about');

        $response->assertStatus(200);

        $sessionCookieName = config('session.cookie');
        $cookie = $response->getCookie($sessionCookieName);

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isSecure(), 'Explicit session.secure=true must enforce Secure flag even on HTTP.');
    }

    public function test_trust_hosts_middleware_pattern_matches_app_url_and_subdomains(): void
    {
        Config::set('app.url', 'https://raflora.up.railway.app');

        $trustHosts = new \Illuminate\Http\Middleware\TrustHosts(app());
        $patterns = $trustHosts->hosts();

        $this->assertNotEmpty($patterns);
        $pattern = $patterns[0];

        // Should match the domain and subdomains
        $this->assertSame(1, preg_match('/' . $pattern . '/', 'raflora.up.railway.app'));
        $this->assertSame(1, preg_match('/' . $pattern . '/', 'sub.raflora.up.railway.app'));

        // Should reject attacker domains
        $this->assertSame(0, preg_match('/' . $pattern . '/', 'evil.com'));
        $this->assertSame(0, preg_match('/' . $pattern . '/', 'raflora.up.railway.app.evil.com'));
    }
}
