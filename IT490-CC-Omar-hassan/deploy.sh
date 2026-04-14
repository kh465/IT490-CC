#!/bin/bash
# deploy.sh

PACKAGE=$1
PACKAGES_DIR="/var/www/sample/packages"
RELEASES_DIR="/var/www/sample/releases"
SHARED_DIR="/var/www/sample/shared"
CURRENT_LINK="/var/www/sample/current"
KEEP_RELEASES=5

if [ -z "$PACKAGE" ]; then
  echo "Usage: deploy.sh <package-file.tar.gz>"
  exit 1
fi

PACKAGE_PATH="$PACKAGES_DIR/$PACKAGE"

if [ ! -f "$PACKAGE_PATH" ]; then
  echo "ERROR: Package not found: $PACKAGE_PATH"
  exit 1
fi


VERSION=$(echo $PACKAGE | sed 's/booking-v//' | sed 's/.tar.gz//')
RELEASE_DIR="$RELEASES_DIR/$VERSION"


if [ -d "$RELEASE_DIR" ]; then
  echo "ERROR: This version number $VERSION already deployed. Bump the version number."
  exit 1
fi

echo ">>> Deploying version number: $VERSION"


mkdir -p $RELEASE_DIR
tar -xzf $PACKAGE_PATH -C $RELEASE_DIR --strip-components=1


ln -sf $SHARED_DIR/.env $RELEASE_DIR/.env


echo "good" > $RELEASE_DIR/.status


ln -sfn $RELEASE_DIR $CURRENT_LINK


sudo systemctl reload apache2

#
ls -dt $RELEASES_DIR/*/ | tail -n +$((KEEP_RELEASES + 1)) | xargs rm -rf 2>/dev/null

echo ">>> SUCCESS: v$VERSION is now live and running"
echo ">>> VerifyTest: cat $CURRENT_LINK/VERSION"
