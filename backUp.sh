#!/bin/bash

script_directory=`dirname "$(readlink -f "$0")"`
php "$script_directory/src/backUp.php" $1
