#!/bin/bash

scriptDirectory=$(dirname "$(readlink -f "$0")")
php8.2 $scriptDirectory/backUp.php
