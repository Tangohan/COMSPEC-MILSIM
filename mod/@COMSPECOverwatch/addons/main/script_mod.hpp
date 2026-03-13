#define MAINPREFIX z
#define PREFIX comspec_overwatch

#include "script_version.hpp"

#define VERSION MAJOR.MINOR
#define VERSION_AR MAJOR,MINOR,PATCH,BUILD

#define REQUIRED_VERSION 2.02
#define REQUIRED_CBA_VERSION 3.15

#define VERSION_CONFIG version = MAJOR.MINOR.PATCH.BUILD; versionStr = QUOTE(MAJOR.MINOR.PATCH.BUILD); versionAr[] = {VERSION_AR}
