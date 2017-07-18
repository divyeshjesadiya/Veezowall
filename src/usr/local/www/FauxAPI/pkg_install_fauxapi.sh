#!/bin/sh

pkg_curl=`pkg info curl | head -n 1`
        if [ "$pkg_curl" = "curl-7.50.3" 2>&1 >/dev/null ]; then
                echo "Curl is already installed"
        else
                pkg install -y curl
                echo "Curl Installed"
        fi

curdir=`pwd`
chdir="/tmp"
cd $chdir

pkg_fauxapi=`pkg info pfSense-pkg-FauxAPI-1_2 | head -n 1`
if [ "$pkg_fauxapi" = "pfSense-pkg-FauxAPI-1_2" 2>&1 >/dev/null ]; then
        echo "FauxAPI is already installed"
else
        curl -O https://raw.githubusercontent.com/ndejong/pfsense_fauxapi/master/package/pfSense-pkg-FauxAPI-1_2.txz --silent --output /dev/null
        pkg install -y pfSense-pkg-FauxAPI-1_2.txz 2>&1 >/dev/null
        echo "Installing FauxAPI"
fi

rm -rf pfSense-pkg-FauxAPI-1_2.txz
cd $curdir
