#!/bin/bash

# 1. Edit DIRECTORY variable where the language packs are installed. Be sure you are on the branch you want to be.
DIRECTORY='/Users/user/git/moodle-langpacks'

# 2. Edit the branch name and USERINFO.
USERINFO='Initial install <install@moodle.invalid>'

# 3. Run the script from local/amos directory.
# ./cli/install.sh

# 4. Execute this SQL (number 2 is the userid to make amos manager).
# Also an AMOS manager role should be added and assigned to the user.
# INSERT INTO mdl_amos_translators ( userid, lang, status ) VALUES (2, 'X', 0);

pushd "$DIRECTORY" > /dev/null
BRANCH=`git symbolic-ref --short HEAD`
popd > /dev/null

filelist=`ls $DIRECTORY/en/*.php`
for langfile in $filelist
do
   php cli/import-strings.php --message='First import' --version=$BRANCH --userinfo="$USERINFO" --yes $langfile
done