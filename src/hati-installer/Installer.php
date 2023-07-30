<?php

namespace hati\installer;

use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\PartialComposer;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Script\ScriptEvents;
use hati\config\ConfigWriter;
use React\Promise\PromiseInterface;

class Installer extends LibraryInstaller {

    private string $root;
    private string $hatiDir;
    protected $composer;

    public function __construct(IOInterface $io, PartialComposer $composer, $root) {
        $this -> composer = $composer;
        $this -> root = $root . DIRECTORY_SEPARATOR;
        $this -> hatiDir = $root . DIRECTORY_SEPARATOR . 'hati' . DIRECTORY_SEPARATOR;

        parent::__construct($io, $composer);
    }

    public function getInstallPath(PackageInterface $package): string {
        return 'rootdata21';
    }

    public function install(InstalledRepositoryInterface $repo, PackageInterface $package): ?PromiseInterface {

        if (file_exists($this -> hatiDir)) {
            $choice = $this -> io -> ask('Existing hati folder found. Do you want to delete it? [y/n]: ', 'n');
            if ($choice === 'y') {
                self::rmdir($this -> hatiDir);
            } else {
                $this -> io -> critical('Hati installation has been cancelled. Please delete hati folder manually.');
                return null;
            }
        }

        return parent::install($repo, $package)->then(function () {

            // Move hati folder to project root directory
            $old = $this -> root . 'rootdata21'. DIRECTORY_SEPARATOR .'hati';
            rename($old, $this -> hatiDir);

            // delete the rootdata21 folder
            self::rmdir($this -> root . 'rootdata21');

            // generate/update the hati.json file on the project root directory
            $createNewConfig = true;
            if (file_exists($this -> root . 'hati.json')) {

                while(true) {
                    $ans = $this -> io -> ask('Existing hati.json found. Do you want to merge it with new config? [y/n]: ');
                    if ($ans !== 'y' && $ans !== 'n') continue;
                    break;
                }
                $createNewConfig = $ans == 'n';
            }

            require_once "{$this -> hatiDir}config" . DIRECTORY_SEPARATOR . "ConfigWriter.php";
            $result = ConfigWriter::write($this->root, $createNewConfig);

            // show the result to the user
            if ($result['success']) {
                $this -> io -> info($result['msg']);

                $welcomeFile = $this -> hatiDir . 'page/welcome.txt';
                if (file_exists($welcomeFile)) include($welcomeFile);

            } else {
                $this -> io -> error($result['msg']);
            }
        }) -> then (function () {
            $this -> dumpAutoload();
        });
    }

    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target) {
        return parent::update($repo, $initial, $target) -> then(function () {
            require_once "{$this -> hatiDir}config" . DIRECTORY_SEPARATOR . "ConfigWriter.php";
            $result = ConfigWriter::write($this->root);

            // show the result to the user
            if ($result['success']) {
                $this -> io -> info('Hati has been updated successfully');
            } else {
                $this -> io -> error($result['msg']);
            }
        });
    }

    public function supports($packageType): bool {
        return 'hati-installer' === $packageType;
    }

    private function dumpAutoload(): void {
        $composerJsonPath = $this -> root . 'composer.json';
        $composerJson = json_decode(file_get_contents($composerJsonPath), true);
        $composerJson['autoload']['psr-4']['hati\\'] = 'hati/';
        file_put_contents($composerJsonPath, json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Regenerate the Composer autoload files to include your classes
        $this -> composer -> getEventDispatcher() -> dispatchScript(ScriptEvents::POST_AUTOLOAD_DUMP);
    }

    public static function rmdir($dir): bool {
        if (!file_exists($dir)) return true;

        if (!is_dir($dir)) return unlink($dir);

        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') continue;

            if (!self::rmdir($dir . DIRECTORY_SEPARATOR . $item)) return false;
        }

        return rmdir($dir);
    }

}