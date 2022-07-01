<?php

namespace hati\installer;

use Composer\Installer\LibraryInstaller;
use Composer\Package\PackageInterface;

class Installer extends LibraryInstaller {

    public function getInstallPath(PackageInterface $package): string {
        return 'rootdata21';
    }

    public function supports($packageType): bool {
        return 'hati-installer' === $packageType;
    }

}