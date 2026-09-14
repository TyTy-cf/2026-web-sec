#!/bin/sh

set -e

USER_ID=${1}
GROUP_ID=${2}
USER_NAME="appuser"
GROUP_NAME="appgroup"

if getent group ${GROUP_ID} >/dev/null; then
    echo "Group ID ${GROUP_ID} already exists. Skipping creation."
else
    groupadd -g ${GROUP_ID} ${GROUP_NAME}
fi

if getent passwd ${USER_ID} >/dev/null; then
    echo "User ID ${USER_ID} already exists. Skipping creation."
else
    useradd -u ${USER_ID} -m -g ${GROUP_ID} ${USER_NAME}
fi
