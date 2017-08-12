#!/bin/sh

pkg_fauxapi=`pkg info pfSense-pkg-FauxAPI-1.1 | head -n 1`
if [ "$pkg_fauxapi" = "pfSense-pkg-FauxAPI-1.1" 2>&1 >/dev/null ]; then
        echo "FauxAPI is already installed"
else
        pkg install -y pfSense-pkg-FauxAPI-1.1.txz 2>&1 >/dev/null
        echo "Installing FauxAPI"
fi
