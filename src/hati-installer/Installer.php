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
	private string $hatiVendor;
	private string $hatiOnRoot;

    protected $composer;

    public function __construct(IOInterface $io, PartialComposer $composer, $root) {
        $this -> composer = $composer;

        $this -> root = $root . DIRECTORY_SEPARATOR;
		$this -> hatiVendor = $this -> root . 'vendor/rootdata21/hati/hati/';
		
		/*
		 * Support 'src' folder if exists
		 * */
		$pathWithSrc = $this->root . 'src' . DIRECTORY_SEPARATOR;
		if (is_dir($pathWithSrc)) {
			$this -> root = $pathWithSrc;
		}
		
		$this -> hatiOnRoot = $this -> root . 'hati/';

        parent::__construct($io, $composer);
    }

    public function install(InstalledRepositoryInterface $repo, PackageInterface $package): ?PromiseInterface {

        return parent::install($repo, $package) -> then(function () {
			/*
			 * Check if we have hati folder on project root
			 * */
			if (!file_exists($this -> hatiOnRoot)) {
				mkdir($this -> hatiOnRoot);
			}

			/*
			 * Move the .htaccess file to the project root
			 * */
			$htaccessPath = "{$this -> root}.htaccess";
			if (!file_exists($htaccessPath)) {
				rename("{$this -> hatiVendor}hati/.htaccess", $htaccessPath);
			}

			/*
			 * Move important files to hati folder on project root
			 * */
			$files = ['db.json', 'init.php', 'tool'];
			foreach ($files as $file) {
				$toPath =  "{$this -> hatiOnRoot}/$file";
				if (file_exists($toPath)) continue;

				// move the file
				rename("{$this -> hatiVendor}hati/$file", $toPath);
			}

			/*
			 * Clean up the hati folder
			 * */
			self::rmdir("{$this -> hatiVendor}hati");

			/*
			 * Check if we have api folder on project root
			 * */
			if (!file_exists($this -> root . 'api')) {
				mkdir($this -> root . 'api');
			}

			/*
			 * Move the api files
			 * */
			$files = ['index.php', 'hati_api_registry.php', 'hati_api_handler.php'];
			foreach ($files as $file) {
				$toPath =  "{$this -> root}api/$file";
				if (file_exists($toPath)) continue;

				// move the file
				rename("{$this -> hatiVendor}api/$file", $toPath);
			}

			/*
             * Generate/update the hati.json file in the hati folder on the project root
			 * */
            $createNewConfig = true;
            if (file_exists("{$this -> hatiOnRoot}hati.json")) {
                while(true) {
                    $ans = $this -> io -> ask("\nExisting hati.json found. Do you want to merge it with new config? [y/n]: ");
                    if ($ans !== 'y' && $ans !== 'n') continue;
                    break;
                }
                $createNewConfig = $ans == 'n';
            }

            require_once "{$this -> hatiVendor}config" . DIRECTORY_SEPARATOR . "ConfigWriter.php";
            $result = ConfigWriter::write($this -> hatiOnRoot, $createNewConfig);

            // show the result to the user
            if ($result['success']) {
                $this -> io -> info($result['msg']);

                $welcomeFile = __DIR__ . '/page/welcome.txt';
                if (file_exists($welcomeFile)) include($welcomeFile);
            } else {
                $this -> io -> error($result['msg']);
            }
        });
    }

    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target) {
        return parent::update($repo, $initial, $target) -> then(function () {
            require_once "{$this -> hatiVendor}config" . DIRECTORY_SEPARATOR . "ConfigWriter.php";
            $result = ConfigWriter::write($this -> hatiOnRoot);

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