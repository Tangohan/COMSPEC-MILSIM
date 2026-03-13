#include "script_component.hpp"

#define DFUNC(var) TRIPLES(ADDON,fnc,var)
#define DEFUNC(var) TRIPLES(DOUBLES(PREFIX,main),fnc,var)

#define QFUNC(var) QUOTE(DFUNC(var))
#define QEFUNC(var) QUOTE(DEFUNC(var))

#define TRIPLES(a,b,c) a##_##b##_##c
#define DOUBLES(a,b) a##_##b

#ifdef DEBUG_MODE_FULL
    #define LOG_SYSFORMAT(LEVEL,MESSAGE) systemChat format ['[COMSPEC %1] %2', LEVEL, MESSAGE]
    #define LOG(message) LOG_SYSFORMAT('INFO', message)
#else
    #define LOG(message)
#endif
