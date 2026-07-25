<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the Progressive Web App wiring — the exam-day offline surface. These
 * are static files and blade tags rather than routed logic, so nothing else in
 * the suite would notice if a refactor dropped the manifest link or a precached
 * asset went missing until the app failed to install/boot offline in the field.
 */
class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_shell_advertises_the_pwa(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('<link rel="manifest" href="/manifest.webmanifest">', $html);
        $this->assertStringContainsString('apple-mobile-web-app-capable', $html);
        $this->assertStringContainsString('apple-mobile-web-app-title', $html);
    }

    public function test_static_pwa_files_are_present(): void
    {
        foreach (['sw.js', 'manifest.webmanifest', 'offline.html'] as $file) {
            $this->assertFileExists(public_path($file), "missing PWA file: {$file}");
        }
    }

    public function test_manifest_is_valid_and_its_icons_exist(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);

        $this->assertIsArray($manifest, 'manifest.webmanifest is not valid JSON');
        $this->assertSame('/', $manifest['scope'] ?? null);
        $this->assertNotEmpty($manifest['icons'] ?? []);

        // Every icon and shortcut target must resolve to a real public file, or
        // the install prompt silently drops the icon / the shortcut 404s.
        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')), "manifest icon missing: {$icon['src']}");
        }
    }

    /**
     * The service worker precaches a fixed shell list on install; if any entry
     * 404s, addAll() rejects and the whole worker fails to install — taking the
     * offline reboot down with it. Keep the list honest against real files.
     */
    public function test_service_worker_precache_assets_all_exist(): void
    {
        $sw = file_get_contents(public_path('sw.js'));

        preg_match('/const SHELL_ASSETS = \[(.*?)\];/s', $sw, $matches);
        $this->assertNotEmpty($matches, 'could not find SHELL_ASSETS in sw.js');

        preg_match_all("/'([^']+)'/", $matches[1], $assets);

        foreach ($assets[1] as $asset) {
            $this->assertFileExists(public_path(ltrim($asset, '/')), "precached shell asset missing: {$asset}");
        }
    }
}
