#!/bin/sh
#
# builder_defaults.sh
#

if [ -z "${BUILDER_ROOT}" ]; then
	echo ">>> ERROR: BUILDER_ROOT must be defined by script that includes builder_defaults.sh"
	exit 1
fi

if [ ! -d "${BUILDER_ROOT}" ]; then
	echo ">>> ERROR: BUILDER_ROOT is invalid"
	exit 1
fi

export BUILDER_TOOLS=${BUILDER_TOOLS:-"${BUILDER_ROOT}/tools"}

if [ ! -d "${BUILDER_TOOLS}" ]; then
	echo ">>> ERROR: BUILDER_TOOLS is invalid"
	exit 1
fi

# Make sure pkg will not be interactive
export ASSUME_ALWAYS_YES=true

# Architecture, supported ARCH values are:
#  Tier 1: i386, AMD64, and PC98
#  Tier 2: ARM, PowerPC, ia64, Sparc64 and sun4v
#  Tier 3: MIPS and S/390
#  Tier 4: None at the moment
export TARGET=${TARGET:-"`uname -m`"}
export TARGET_ARCH=${TARGET_ARCH:-${TARGET}}
# Set TARGET_ARCH_CONF_DIR
if [ "$TARGET_ARCH" = "" ]; then
        export TARGET_ARCH=`uname -p`
fi

# Directory to be used for writing temporary information
export SCRATCHDIR=${SCRATCHDIR:-"${BUILDER_ROOT}/tmp"}
if [ ! -d ${SCRATCHDIR} ]; then
	mkdir -p ${SCRATCHDIR}
fi

# Product details
export PRODUCT_NAME=${PRODUCT_NAME:-"AISense"}
export PRODUCT_NAME_SUFFIX=${PRODUCT_NAME_SUFFIX:-"-CE"}
export PRODUCT_URL=${PRODUCT_URL:-"https://github.com/AISense-UTM"}
export PRODUCT_SRC=${PRODUCT_SRC:-"${BUILDER_ROOT}/src"}
export PRODUCT_EMAIL=${PRODUCT_EMAIL:-"sales@infrassist.com"}
export XML_ROOTOBJ=${XML_ROOTOBJ:-$(echo "${PRODUCT_NAME}" | tr '[[:upper:]]' '[[:lower:]]')}

if [ -z "${PRODUCT_VERSION}" ]; then
	if [ ! -f ${PRODUCT_SRC}/etc/version ]; then
		echo ">>> ERROR: PRODUCT_VERSION is not defined and ${PRODUCT_SRC}/etc/version was not found"
		print_error_pfS
	fi

	export PRODUCT_VERSION=$(head -n 1 ${PRODUCT_SRC}/etc/version)
fi
export PRODUCT_REVISION=${PRODUCT_REVISION:-"2"}

# Product repository tag to build
_cur_git_repo_branch_or_tag=$(git -C ${BUILDER_ROOT} rev-parse --abbrev-ref HEAD)
if [ "${_cur_git_repo_branch_or_tag}" = "HEAD" ]; then
	# We are on a tag, lets find out its name
	export GIT_REPO_BRANCH_OR_TAG=$(git -C ${BUILDER_ROOT} describe --tags)
else
	export GIT_REPO_BRANCH_OR_TAG="${_cur_git_repo_branch_or_tag}"
fi
# Use vX_Y instead of RELENG_X_Y for poudriere to make it shorter
# Replace . by _ to make tag names look correct
POUDRIERE_BRANCH=$(echo "${GIT_REPO_BRANCH_OR_TAG}" | sed 's,RELENG_,v,; s,\.,_,g')

GIT_REPO_BASE=$(git -C ${BUILDER_ROOT} config --get remote.origin.url | sed -e 's,/[^/]*$,,')

# This is used for using svn for retrieving src
export FREEBSD_REPO_BASE=${FREEBSD_REPO_BASE:-"${GIT_REPO_BASE}/FreeBSD-src.git"}
export FREEBSD_BRANCH=${FREEBSD_BRANCH:-"RELENG_2_3_2"}
export FREEBSD_PARENT_BRANCH=${FREEBSD_PARENT_BRANCH:-"releng/10.3"}
export FREEBSD_SRC_DIR=${FREEBSD_SRC_DIR:-"${SCRATCHDIR}/FreeBSD-src"}

if [ "${TARGET}" = "i386" ]; then
	export BUILD_KERNELS=${BUILD_KERNELS:-"${PRODUCT_NAME} ${PRODUCT_NAME}_wrap ${PRODUCT_NAME}_wrap_vga"}
else
	export BUILD_KERNELS=${BUILD_KERNELS:-"${PRODUCT_NAME}"}
fi

# XXX: Poudriere doesn't like ssh short form
case "${FREEBSD_REPO_BASE}" in
	git@*)
		export FREEBSD_REPO_BASE_POUDRIERE="ssh://$(echo ${FREEBSD_REPO_BASE} | sed 's,:,/,')"
		;;
	*)
		export FREEBSD_REPO_BASE_POUDRIERE="${FREEBSD_REPO_BASE}"
		;;
esac

# Leave this alone.
export SRC_CONF=${SRC_CONF:-"${FREEBSD_SRC_DIR}/release/conf/${PRODUCT_NAME}_src.conf"}
export MAKE_CONF=${MAKE_CONF:-"${FREEBSD_SRC_DIR}/release/conf/${PRODUCT_NAME}_make.conf"}

# Extra tools to be added to ITOOLS
export EXTRA_TOOLS=${EXTRA_TOOLS:-"uuencode uudecode ex"}

# Path to kernel files being built
export KERNEL_BUILD_PATH=${KERNEL_BUILD_PATH:-"${SCRATCHDIR}/kernels"}

# Do not touch builder /usr/obj
export MAKEOBJDIRPREFIX=${MAKEOBJDIRPREFIX:-"${SCRATCHDIR}/obj"}

# Controls how many concurrent make processes are run for each stage
_CPUS=""
if [ -z "${NO_MAKEJ}" ]; then
	_CPUS=$(expr $(sysctl -n kern.smp.cpus) '*' 2)
	if [ -n "${_CPUS}" ]; then
		_CPUS="-j${_CPUS}"
	fi
fi

export MAKEJ_WORLD=${MAKEJ_WORLD:-"${_CPUS}"}
export MAKEJ_KERNEL=${MAKEJ_KERNEL:-"${_CPUS}"}

if [ "${TARGET}" = "i386" ]; then
	export MODULES_OVERRIDE=${MODULES_OVERRIDE:-"i2c ipmi ndis ipfw ipdivert dummynet fdescfs opensolaris zfs glxsb if_stf coretemp amdtemp hwpmc"}
else
	export MODULES_OVERRIDE=${MODULES_OVERRIDE:-"i2c ipmi ndis ipfw ipdivert dummynet fdescfs opensolaris zfs glxsb if_stf coretemp amdtemp aesni sfxge hwpmc vmm nmdm ixgbe"}
fi

# Area that the final image will appear in
export IMAGES_FINAL_DIR=${IMAGES_FINAL_DIR:-"${SCRATCHDIR}/${PRODUCT_NAME}/"}

export BUILDER_LOGS=${BUILDER_LOGS:-"${BUILDER_ROOT}/logs"}
if [ ! -d ${BUILDER_LOGS} ]; then
	mkdir -p ${BUILDER_LOGS}
fi

# This is where files will be staged
export STAGE_CHROOT_DIR=${STAGE_CHROOT_DIR:-"${SCRATCHDIR}/stage-dir"}

# Directory that will clone to in order to create
# iso staging area.
export FINAL_CHROOT_DIR=${FINAL_CHROOT_DIR:-"${SCRATCHDIR}/final-dir"}

# 400M is not enough for amd64
export MEMORYDISK_SIZE=${MEMORYDISK_SIZE:-"1024M"}

# OVF/vmdk parms
# Name of ovf file included inside OVA archive
export OVFTEMPLATE=${OVFTEMPLATE:-"${BUILDER_TOOLS}/templates/ovf/${PRODUCT_NAME}.ovf"}
# / partition to be used by mkimg
export OVFUFS=${OVFUFS:-"${PRODUCT_NAME}${PRODUCT_NAME_SUFFIX}-disk1.ufs"}
# Raw disk to be converted to vmdk
export OVFRAW=${OVFRAW:-"${PRODUCT_NAME}${PRODUCT_NAME_SUFFIX}-disk1.raw"}
# On disk name of VMDK file included in OVA
export OVFVMDK=${OVFVMDK:-"${PRODUCT_NAME}${PRODUCT_NAME_SUFFIX}-disk1.vmdk"}
# 8 gigabyte on disk VMDK size
export VMDK_DISK_CAPACITY_IN_GB=${VMDK_DISK_CAPACITY_IN_GB:-"8"}
# swap partition size (freebsd-swap)
export OVA_SWAP_PART_SIZE_IN_GB=${OVA_SWAP_PART_SIZE_IN_GB:-"0"}
# Temporary place to save files
export OVA_TMP=${OVA_TMP:-"${SCRATCHDIR}/ova_tmp"}
# end of OVF

# Number of code images on media (1 or 2)
export NANO_IMAGES=2
# 0 -> Leave second image all zeroes so it compresses better.
# 1 -> Initialize second image with a copy of the first
export NANO_INIT_IMG2=1
export NANO_NEWFS="-b 4096 -f 512 -i 8192 -O1"
export FLASH_SIZE=${FLASH_SIZE:-"2g"}
# Size of code file system in 512 bytes sectors
# If zero, size will be as large as possible.
export NANO_CODESIZE=0
# Size of data file system in 512 bytes sectors
# If zero: no partition configured.
# If negative: max size possible
export NANO_DATASIZE=0
# Size of Product /conf partition  # 102400 = 50 megabytes.
export NANO_CONFSIZE=102400
# packet is OK for 90% of embedded
export NANO_BOOT0CFG="-o packet -s 1 -m 3"

# NOTE: Date string is used for creating file names of images
#       The file is used for sharing the same value with build_snapshots.sh
export DATESTRINGFILE=${DATESTRINGFILE:-"$SCRATCHDIR/version.snapshots"}
if [ -z "${DATESTRING}" ]; then
	if [ -f "${DATESTRINGFILE}" -a -n "${_USE_OLD_DATESTRING}" ]; then
		export DATESTRING=$(cat $DATESTRINGFILE)
	else
		export DATESTRING=$(date "+%Y%m%d-%H%M")
	fi
fi
echo "$DATESTRING" > $DATESTRINGFILE

# NOTE: Date string is placed on the final image etc folder to help detect new updates
#       The file is used for sharing the same value with build_snapshots.sh
export BUILTDATESTRINGFILE=${BUILTDATESTRINGFILE:-"$SCRATCHDIR/version.buildtime"}
if [ -z "${BUILTDATESTRING}" ]; then
	if [ -f "${BUILTDATESTRINGFILE}" -a -n "${_USE_OLD_DATESTRING}" ]; then
		export BUILTDATESTRING=$(cat $BUILTDATESTRINGFILE)
	else
		export BUILTDATESTRING=$(date "+%a %b %d %T %Z %Y")
	fi
fi
echo "$BUILTDATESTRING" > $BUILTDATESTRINGFILE

STAGING_HOSTNAME=${STAGING_HOSTNAME:-"18.185.190.151"}
DEVELOPMENT_HOSTNAME=${DEVELOPMENT_HOSTNAME:-"18.130.34.134"}

# Poudriere
# Poudriere
export ZFS_TANK=${ZFS_TANK:-"zroot"}
export ZFS_ROOT=${ZFS_ROOT:-"/poudriere"}
export POUDRIERE_PORTS_NAME=${POUDRIERE_PORTS_NAME:-"${PRODUCT_NAME}_${POUDRIERE_BRANCH}"}

export POUDRIERE_BULK=${POUDRIERE_BULK:-"${BUILDER_TOOLS}/conf/pfPorts/poudriere_bulk"}
export POUDRIERE_PORTS_GIT_URL=${POUDRIERE_PORTS_GIT_URL:-"${GIT_REPO_BASE}/Veezowall-FreeBSD-ports.git"}
export POUDRIERE_PORTS_GIT_BRANCH=${POUDRIERE_PORTS_GIT_BRANCH:-"RELENG_2_3_2"}

unset _IS_RELEASE
unset CORE_PKG_DATESTRING
export TIMESTAMP_SUFFIX="-${DATESTRING}"
# pkg doesn't like - as version separator, use . instead
export PKG_DATESTRING=$(echo "${DATESTRING}" | sed 's,-,.,g')
case "${PRODUCT_VERSION##*-}" in
	RELEASE)
		export _IS_RELEASE=yes
		unset TIMESTAMP_SUFFIX
		;;
	ALPHA|DEVELOPMENT)
		export CORE_PKG_DATESTRING=".a.${PKG_DATESTRING}"
		;;
	BETA*)
		export CORE_PKG_DATESTRING=".b.${PKG_DATESTRING}"
		;;
	RC*)
		export CORE_PKG_DATESTRING=".r.${PKG_DATESTRING}"
		;;
	*)
		echo ">>> ERROR: Invalid PRODUCT_VERSION format ${PRODUCT_VERSION}"
		exit 1
esac

# Host to rsync pkg repos from poudriere
export PKG_RSYNC_HOSTNAME=${PKG_RSYNC_HOSTNAME:-${STAGING_HOSTNAME}}
export PKG_RSYNC_USERNAME=${PKG_RSYNC_USERNAME:-"root"}
export PKG_RSYNC_SSH_PORT=${PKG_RSYNC_SSH_PORT:-"2224"}
export PKG_RSYNC_DESTDIR=${PKG_RSYNC_DESTDIR:-"/usr/local/poudriere/data/packages/RELEASE"}
export PKG_RSYNC_LOGS=${PKG_RSYNC_LOGS:-"/usr/local/poudriere/data/packages/logs/${POUDRIERE_BRANCH}/${TARGET}"}

# Final packages server
if [ -n "${_IS_RELEASE}" ]; then
	export PKG_FINAL_RSYNC_HOSTNAME=${PKG_FINAL_RSYNC_HOSTNAME:-${DEVELOPMENT_HOSTNAME}}
	export PKG_FINAL_RSYNC_DESTDIR=${PKG_FINAL_RSYNC_DESTDIR:-"/usr/local/poudriere/data/packages/RELEASE"}
else
	export PKG_FINAL_RSYNC_HOSTNAME=${PKG_FINAL_RSYNC_HOSTNAME:-${DEVELOPMENT_HOSTNAME}}
	export PKG_FINAL_RSYNC_DESTDIR=${PKG_FINAL_RSYNC_DESTDIR:-"/usr/local/poudriere/data/packages/DEVELOPMENT"}
fi
export PKG_FINAL_RSYNC_USERNAME=${PKG_FINAL_RSYNC_USERNAME:-"root"}
export PKG_FINAL_RSYNC_SSH_PORT=${PKG_FINAL_RSYNC_SSH_PORT:-"2224"}
export SKIP_FINAL_RSYNC=${SKIP_FINAL_RSYNC:-}

# pkg repo variables
export REPO_HOSTNAME=${REPO_HOSTNAME:-"18.185.190.151"}
export USE_PKG_REPO_STAGING="1"
export PKG_REPO_SERVER_DEVEL=${PKG_REPO_SERVER_DEVEL:-"pkg+http://${REPO_HOSTNAME}"}
export PKG_REPO_SERVER_RELEASE=${PKG_REPO_SERVER_RELEASE:-"pkg+http://${REPO_HOSTNAME}"}
export PKG_REPO_SERVER_STAGING=${PKG_REPO_SERVER_STAGING:-"pkg+http://${REPO_HOSTNAME}"}

if [ -n "${_IS_RELEASE}" ]; then
	export PKG_REPO_BRANCH_RELEASE=${PKG_REPO_BRANCH_RELEASE:-${POUDRIERE_BRANCH}}
	export PKG_REPO_BRANCH_DEVEL=${PKG_REPO_BRANCH_DEVEL:-"v2_3"}
	export PKG_REPO_BRANCH_STAGING=${PKG_REPO_BRANCH_STAGING:-${PKG_REPO_BRANCH_RELEASE}}
else
	export PKG_REPO_BRANCH_RELEASE=${PKG_REPO_BRANCH_RELEASE:-"v2_3_2"}
	export PKG_REPO_BRANCH_DEVEL=${PKG_REPO_BRANCH_DEVEL:-${POUDRIERE_BRANCH}}
	export PKG_REPO_BRANCH_STAGING=${PKG_REPO_BRANCH_STAGING:-${PKG_REPO_BRANCH_DEVEL}}
fi

if [ -n "${_IS_RELEASE}" ]; then
	export PKG_REPO_SIGN_KEY=${PKG_REPO_SIGN_KEY:-"release${PRODUCT_NAME_SUFFIX}"}
else
	export PKG_REPO_SIGN_KEY=${PKG_REPO_SIGN_KEY:-"beta${PRODUCT_NAME_SUFFIX}"}
fi
# Command used to sign pkg repo
export PKG_REPO_SIGNING_COMMAND=${PKG_REPO_SIGNING_COMMAND:-"/usr/local/etc/ssl/repo.key"}

# Define base package version, based on date for snaps
export CORE_PKG_VERSION="${PRODUCT_VERSION%%-*}${CORE_PKG_DATESTRING}${PRODUCT_REVISION:+_}${PRODUCT_REVISION}"
export CORE_PKG_PATH=${CORE_PKG_PATH:-"${SCRATCHDIR}/${PRODUCT_NAME}_${POUDRIERE_BRANCH}_${TARGET_ARCH}-core"}
export CORE_PKG_REAL_PATH="${CORE_PKG_PATH}/.real_${DATESTRING}"
export CORE_PKG_ALL_PATH="${CORE_PKG_PATH}/All"
export CORE_PKG_TMP=${CORE_PKG_TMP:-"${SCRATCHDIR}/core_pkg_tmp"}

export PKG_REPO_BASE=${PKG_REPO_BASE:-"${FREEBSD_SRC_DIR}/release/pkg_repos"}
export PKG_REPO_DEFAULT=${PKG_REPO_DEFAULT:-"${PKG_REPO_BASE}/${PRODUCT_NAME}-repo.conf"}
export PKG_REPO_PATH=${PKG_REPO_PATH:-"/usr/local/etc/pkg/repos/${PRODUCT_NAME}.conf"}

export PRODUCT_SHARE_DIR=${PRODUCT_SHARE_DIR:-"/usr/local/share/${PRODUCT_NAME}"}

# Package overlay. This gives people a chance to build product
# installable image that already contains certain extra packages.
#
# Needs to contain comma separated package names. Of course
# package names must be valid. Using non existent
# package name would yield an error.
#
#export custom_package_list=""

# General builder output filenames
export UPDATESDIR=${UPDATESDIR:-"${IMAGES_FINAL_DIR}/updates"}
export ISOPATH=${ISOPATH:-"${IMAGES_FINAL_DIR}/${PRODUCT_NAME}${PRODUCT_NAME_SUFFIX}-${PRODUCT_VERSION}${PRODUCT_REVISION:+-p}${PRODUCT_REVISION}-${TARGET}${TIMESTAMP_SUFFIX}.iso"}
export MEMSTICKPATH=${MEMSTICKPATH:-"${IMAGES_FINAL_DIR}/${PRODUCT_NAME}${PRODUCT_NAME_SUFFIX}-memstick-${PRODUCT_VERSION}${PRODUCT_REVISION:+-p}${PRODUCT_REVISION}-${TARGET}${TIMESTAMP_SUFFIX}.img"}
export MEMSTICKSERIALPATH=${MEMSTICKSERIALPATH:-"${IMAGES_FINAL_DIR}/${PRODUCT_NAME}${PRODUCT_NAME_SUFFIX}-memstick-serial-${PRODUCT_VERSION}${PRODUCT_REVISION:+-p}${PRODUCT_REVISION}-${TARGET}${TIMESTAMP_SUFFIX}.img"}
export MEMSTICKADIPATH=${MEMSTICKADIPATH:-"${IMAGES_FINAL_DIR}/${PRODUCT_NAME}${PRODUCT_NAME_SUFFIX}-memstick-ADI-${PRODUCT_VERSION}${PRODUCT_REVISION:+-p}${PRODUCT_REVISION}-${TARGET}${TIMESTAMP_SUFFIX}.img"}
export OVAPATH=${OVAPATH:-"${IMAGES_FINAL_DIR}/${PRODUCT_NAME}${PRODUCT_NAME_SUFFIX}-${PRODUCT_VERSION}${PRODUCT_REVISION:+-p}${PRODUCT_REVISION}-${TARGET}${TIMESTAMP_SUFFIX}.ova"}
export MEMSTICK_VARIANTS=${MEMSTICK_VARIANTS:-}
export VARIANTIMAGES=""
export VARIANTUPDATES=""

# set full-update update filename
export UPDATES_TARBALL_FILENAME=${UPDATES_TARBALL_FILENAME:-"${UPDATESDIR}/${PRODUCT_NAME}${PRODUCT_NAME_SUFFIX}-Full-Update-${PRODUCT_VERSION}${PRODUCT_REVISION:+-p}${PRODUCT_REVISION}-${TARGET}${TIMESTAMP_SUFFIX}.tgz"}

# Rsync data to send snapshots
export RSYNCIP=${RSYNCIP:-${STAGING_HOSTNAME}}
export RSYNCUSER=${RSYNCUSER:-"root"}
export RSYNC_SSH_PORT=${RSYNC_SSH_PORT:-"2224"}
export RSYNCPATH=${RSYNCPATH:-"/usr/local/poudriere/data/packages/snapshots/${TARGET}/${PRODUCT_NAME}_${GIT_REPO_BRANCH_OR_TAG}"}

# staging area used on snapshots build
STAGINGAREA=${STAGINGAREA:-"${SCRATCHDIR}/staging"}
mkdir -p ${STAGINGAREA}

export SNAPSHOTSLOGFILE=${SNAPSHOTSLOGFILE:-"${SCRATCHDIR}/snapshots-build.log"}
export SNAPSHOTSLASTUPDATE=${SNAPSHOTSLASTUPDATE:-"${SCRATCHDIR}/snapshots-lastupdate.log"}

if [ -n "${POUDRIERE_SNAPSHOTS}" ]; then
	export SNAPSHOTS_RSYNCIP=${PKG_RSYNC_HOSTNAME}
	export SNAPSHOTS_RSYNCUSER=${PKG_RSYNC_USERNAME}
else
	export SNAPSHOTS_RSYNCIP=${RSYNCIP}
	export SNAPSHOTS_RSYNCUSER=${RSYNCUSER}
fi

export VENDOR_NAME=${VENDOR_NAME:-"AISense"}
export OVF_INFO=${OVF_INFO:-"none"}

