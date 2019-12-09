#!/usr/bin/env bash
set -e

##source ./scripts/utils.sh


#################################################################################################
################################### NextDom Installation from git ###############################
#################################################################################################

##if [[ ! -f ${ROOT_DIRECTORY}/.git  ]] ; then
##    addLogInfo "Il n'y a pas de dossier .git"
##fi

bash scripts/preinst.sh
bash ./scripts/postinst.sh



