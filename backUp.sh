#!/bin/bash

script_directory=$(dirname "$(readlink -f "$0")")
php8.2 "$script_directory/src/backUp.php" $1
