#!/bin/bash

default_sender="noreply@localhost"
has_sender=false
has_recipients=false
recipients=()
args=()

while [ $# -gt 0 ]; do
	case "$1" in
	-i) shift ;; # msmtp and nullmailer don't support this option
	-t)
		has_recipients=true
		shift
		;; # Handle -t flag separately for msmtp
	-f)
		has_sender=true
		args+=("$1" "$2")
		shift 2
		;;
	*)
		# Collect non-option arguments as potential recipients
		recipients+=("$1")
		args+=("$1")
		shift
		;;
	esac
done

if [ "$has_sender" = false ]; then
	args=("-f" "$default_sender" "${args[@]}")
fi

# Check which mailer is available and use appropriate command
if command -v msmtp >/dev/null 2>&1; then
	# For msmtp, use -t flag if:
	# 1. -t was explicitly passed, OR
	# 2. No recipients were provided as arguments (WordPress typical case)
	if [ "$has_recipients" = true ] || [ ${#recipients[@]} -eq 0 ]; then
		echo "Called '/usr/bin/msmtp' with arguments: -t ${args[*]}"
		exec /usr/bin/msmtp -t "${args[@]}"
	else
		echo "Called '/usr/bin/msmtp' with arguments: ${args[*]}"
		exec /usr/bin/msmtp "${args[@]}"
	fi
elif command -v nullmailer-inject >/dev/null 2>&1; then
	echo "Called '/usr/bin/nullmailer-inject' with arguments: ${args[*]}"
	exec /usr/bin/nullmailer-inject "${args[@]}"
else
	# Does not return an error (exit code 0)
	# Prints all email output to the container logs
	# Shows headers (From, To, Subject, Date) and message body
	# Perfect for debugging during development
	echo "No mailer found (msmtp or nullmailer-inject), using /bin/cat as fallback"
	exec /bin/cat
fi
