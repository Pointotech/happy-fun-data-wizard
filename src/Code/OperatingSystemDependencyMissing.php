<?php

namespace Pointotech\Code;

use Exception;

class OperatingSystemDependencyMissing extends Exception
{
    function __construct(
        string $dependencyDescription,
        string $dependencyInstallationCommand
    ) {
        parent::__construct(
            'The operating system dependency "' . $dependencyDescription . '" is missing. Try running `' . $dependencyInstallationCommand . '` to install it (this command may vary depending on the operating system and how PHP is installed).'
        );
    }
}
