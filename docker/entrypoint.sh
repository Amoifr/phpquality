#!/bin/sh
set -e

# PhpQuality Docker Entrypoint
# Usage: docker run amoifr/phpquality analyze --source=/project/src

# If first argument starts with -, assume it's an option for analyze
if [ "${1#-}" != "$1" ]; then
    set -- analyze "$@"
fi

# If first argument is "analyze", run the Symfony command
if [ "$1" = "analyze" ]; then
    shift
    exec php /app/bin/console analyze "$@"
fi

# If first argument is a known command, run it
case "$1" in
    list|help|about|--version|--help|-V|-h)
        exec php /app/bin/console "$@"
        ;;
esac

# Otherwise, execute the command as-is
exec "$@"
