#!/bin/bash

if [[ "$2" == "" ]]; then
    echo "Usage: $0 <dirroot> <dirsymlinks>"
    echo
    echo "Example:"
    echo "# cd  /home/mudrd8mz/public_html/moodle25"
    echo "# mkdir tmp"
    echo "# $0 \$(pwd) tmp"
    exit
fi

DIRROOT=$1
SYMLINKSDIR=$2

if [[ ! -d $DIRROOT ]]; then
    echo "Moodle root directory not found!"
    exit
fi

if [[ ! -f $DIRROOT/config-dist.php ]]; then
    echo "Does not seem to be Moodle root directory!"
    exit
fi

if [[ ! -d $SYMLINKSDIR ]]; then
    echo "Target directory for symlinks not found!"
    exit
fi

###########################################

pushd $SYMLINKSDIR

for f in $(find $DIRROOT -wholename '*/lang/en/*.php' -not -wholename '*/tests/fixtures/*'); do
    if [[ $f == $DIRROOT/install/* ]]; then
        echo Skipping $f
    else
        ln -s $f $(basename $f)
    fi
done

popd

echo "Done! Now you may want to run something like:"
echo "# php enfix-merge.php --symlinksdir=/home/mudrd8mz/public_html/moodle25/tmp --enfixdir=/home/mudrd8mz/tmp/enfix/2.5"
