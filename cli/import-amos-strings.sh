#!/bin/bash

sudo -u www-data php /opt/app/local/amos/cli/import-strings.php --message='AMOS strings update' --versioncode=$(sudo -u www-data php /opt/app/admin/cli/cfg.php --name=branch) /opt/app/local/amos/lang/en/local_amos.php
