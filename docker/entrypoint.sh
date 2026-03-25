#!/bin/sh
set -e

# PhpQuality Docker Entrypoint
# Usage: docker run amoifr13/phpquality phpquality:analyze --source=/project/src
#        docker run amoifr13/phpquality analyze --source=/project/src (alias)
#        docker run amoifr13/phpquality --source=/project/src (shorthand)

# If first argument starts with -, assume it's an option for phpquality:analyze
if [ "${1#-}" != "$1" ]; then
    set -- phpquality:analyze "$@"
fi

# Support both old 'analyze' and new 'phpquality:analyze' commands
if [ "$1" = "analyze" ]; then
    shift
    exec php /app/bin/console phpquality:analyze "$@"
fi

if [ "$1" = "phpquality:analyze" ]; then
    shift
    exec php /app/bin/console phpquality:analyze "$@"
fi

# If first argument is a known command, run it
case "$1" in
    list|help|about|--version|--help|-V|-h)
        exec php /app/bin/console "$@"
        ;;
esac

# Otherwise, execute the command as-is
exec "$@"
