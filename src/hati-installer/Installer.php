<?php

namespace hati\installer;

use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\PartialComposer;
use Composer\Repository\InstalledRepositoryInterface;
use FilesystemIterator;
use hati\config\ConfigWriter;
use React\Promise\PromiseInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Installer extends LibraryInstaller {

    private string $root;
    private string $hatiDir;
    private string $configPath;
    protected $composer;

    public function __construct(IOInterface $io, PartialComposer $composer, $root) {
        $this -> composer = $composer;
        $this -> root = $root . DIRECTORY_SEPARATOR;
        $this -> hatiDir = $root . DIRECTORY_SEPARATOR . 'hati' . DIRECTORY_SEPARATOR;
		$this -> configPath = $this -> root . 'config' . DIRECTORY_SEPARATOR;

        parent::__construct($io, $composer);
    }

    public function getInstallPath(PackageInterface $package): string {
        return 'hati';
    }

    public function install(InstalledRepositoryInterface $repo, PackageInterface $package): ?PromiseInterface {

        // add custom classmap for hati folder being on the root directory
        $autoload = $package -> getAutoload();
        if (isset($autoload['psr-4'])) {
            $customPSR4 = ['hati\\' => '/',];
            $autoload['psr-4'] = array_merge($autoload['psr-4'], $customPSR4);
            $package -> setAutoload($autoload);
        }

        return parent::install($repo, $package) -> then(function () {

            // move hati folder to _temp, rename
            self::copy($this -> root . 'hati' . DIRECTORY_SEPARATOR . 'hati', $this -> root . '_temp');
            self::rmdir($this -> root . 'hati');
            rename($this -> root . '_temp',$this -> root . 'hati');

			// move the config folder out to the root path
			$dbConfigFile = $this -> configPath . 'db.json';
			if (!file_exists($dbConfigFile)) {
				self::copy($this -> hatiDir . 'config', $this -> root . 'config');
				self::rmdir($this -> hatiDir . 'config');
			}

            // generate/update the hati.json file on the project root directory
            $createNewConfig = true;
            if (file_exists($this -> configPath . 'hati.json')) {
                while(true) {
                    $ans = $this -> io -> ask('\nExisting hati.json found. Do you want to merge it with new config? [y/n]: ');
                    if ($ans !== 'y' && $ans !== 'n') continue;
                    break;
                }
                $createNewConfig = $ans == 'n';
            }

            require_once "{$this -> hatiDir}hati_config" . DIRECTORY_SEPARATOR . "ConfigWriter.php";
            $result = ConfigWriter::write($this -> configPath, $createNewConfig);

            // show the result to the user
            if ($result['success']) {
                $this -> io -> info($result['msg']);

                $welcomeFile = $this -> hatiDir . 'page/welcome.txt';
                if (file_exists($welcomeFile)) include($welcomeFile);
            } else {
                $this -> io -> error($result['msg']);
            }
        });
    }

    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target) {
        return parent::update($repo, $initial, $target) -> then(function () {
            require_once "{$this -> hatiDir}hati_config" . DIRECTORY_SEPARATOR . "ConfigWriter.php";
            $result = ConfigWriter::write($this -> configPath);

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

    public static function rmdir($dir): bool {
        if (!file_exists($dir)) return true;

        if (!is_dir($dir)) return unlink($dir);

        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') continue;

            if (!self::rmdir($dir . DIRECTORY_SEPARATOR . $item)) return false;
        }

        return rmdir($dir);
    }

    public static function copy($source, $destination): void {
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $dirIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($dirIterator as $file) {
            $target = $destination . DIRECTORY_SEPARATOR . $dirIterator -> getSubPathName();
            if ($file->isDir()) {
                mkdir($target);
            } else {
                copy($file, $target);
            }
        }
    }

}