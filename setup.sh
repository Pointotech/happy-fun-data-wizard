#!/bin/bash

script_directory=`dirname "$(readlink -f "$0")"`
php "$script_directory/setup.php" $1