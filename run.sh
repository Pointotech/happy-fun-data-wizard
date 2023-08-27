#!/bin/bash

script_directory=`dirname "$(readlink -f "$0")"`
php8.1 "$script_directory/main.php" $1
