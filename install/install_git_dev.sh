#!/usr/bin/env bash
set -e

source ./scripts/preinst.sh
source ./scripts/postinst.sh


#################################################################################################
################################### NextDom Installation from git ###############################
#################################################################################################

if [[! -f ${ROOT_DIRECTORY}/.git  ]] ; then
    addLogInfo "Il n'y a pas de dossier .git"
fi

sed -i "s|PRODUCTION=true|PRODUCTION=false|g" ./scripts/config.sh

scripts/preinst.sh
scripts/postinst.sh


