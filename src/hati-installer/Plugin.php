<?php

namespace hati\installer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

class Plugin implements PluginInterface {

    public function activate(Composer $composer, IOInterface $io): void {
        $installer = new Installer($io, $composer, $this->getRootPath($composer));
        $composer -> getInstallationManager() -> addInstaller($installer);
    }

    public function deactivate(Composer $composer, IOInterface $io) {}

    public function uninstall(Composer $composer, IOInterface $io) {}

    protected function getRootPath($composer) : string {
        $config = $composer -> getConfig();
        $vendorDir = $config -> get('vendor-dir');
        return realpath($vendorDir . '/..');
    }

}