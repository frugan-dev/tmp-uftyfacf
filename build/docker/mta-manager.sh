#!/bin/bash

set -euo pipefail

ACTION="${1:-start}"

echo "=== MTA Manager ==="

# Detect available MTA
detect_mta() {
	if command -v msmtp >/dev/null 2>&1; then
		echo "msmtp"
	elif command -v nullmailer-inject >/dev/null 2>&1; then
		if command -v nullmailer-send >/dev/null 2>&1; then
			# Built from source
			echo "nullmailer-source"
		else
			# Installed via package manager
			echo "nullmailer-package"
		fi
	else
		echo "none"
	fi
}

MTA=$(detect_mta)
echo "Detected MTA: $MTA"

case "$ACTION" in
start)
	echo "Starting MTA daemon..."

	case "$MTA" in
	msmtp)
		echo "✅ msmtp is configured (no daemon required)"
		;;

	nullmailer-source)
		# Ensure required directories exist with proper permissions
		mkdir -p /var/spool/nullmailer/queue /var/spool/nullmailer/tmp /var/log/nullmailer
		chown -R root:root /var/spool/nullmailer /var/log/nullmailer
		chmod 755 /var/spool/nullmailer /var/spool/nullmailer/queue /var/spool/nullmailer/tmp /var/log/nullmailer

		# Ensure configuration directory has proper permissions
		if [ -d /etc/nullmailer ]; then
			chown -R root:root /etc/nullmailer
			chmod 755 /etc/nullmailer
			chmod 644 /etc/nullmailer/* 2>/dev/null || true

			# Create required configuration files if missing
			[ ! -f /etc/nullmailer/me ] && echo "localhost.localdomain" >/etc/nullmailer/me
			[ ! -f /etc/nullmailer/defaultdomain ] && echo "localdomain" >/etc/nullmailer/defaultdomain
			[ ! -f /etc/nullmailer/adminaddr ] && echo "admin@localhost" >/etc/nullmailer/adminaddr

			# Ensure all config files have correct permissions
			chmod 644 /etc/nullmailer/me /etc/nullmailer/defaultdomain /etc/nullmailer/adminaddr 2>/dev/null || true
		fi

		# Check if nullmailer-send is already running
		if pgrep -f nullmailer-send >/dev/null 2>&1; then
			echo "✅ Nullmailer daemon is already running"
			exit 0
		fi

		# Start nullmailer-send daemon in background
		nullmailer-send &
		NULLMAILER_PID=$!

		if [ -n "$NULLMAILER_PID" ]; then
			echo "✅ Nullmailer daemon started (PID: $NULLMAILER_PID)"

			# Give it a moment to start
			sleep 2

			# Check if process is still running
			if kill -0 "$NULLMAILER_PID" 2>/dev/null; then
				echo "✅ Nullmailer daemon is running successfully"
			else
				echo "⚠️  Nullmailer daemon started but stopped immediately"
				exit 1
			fi
		else
			echo "❌ Failed to start nullmailer daemon"
			exit 1
		fi
		;;

	nullmailer-package)
		# Check if service is already running
		if service nullmailer status >/dev/null 2>&1; then
			echo "✅ Nullmailer service is already running"
			exit 0
		fi

		# Start nullmailer service
		if service nullmailer start; then
			echo "✅ Nullmailer service started successfully"

			# Verify it's running
			if service nullmailer status >/dev/null 2>&1; then
				echo "✅ Nullmailer service is running"
			else
				echo "⚠️  Nullmailer service started but status check failed"
			fi
		else
			echo "❌ Nullmailer service failed to start"
			exit 1
		fi
		;;

	none)
		echo "⚠️  No MTA found - emails will use fallback handler"
		;;
	esac
	;;

stop)
	echo "Stopping MTA daemon..."

	case "$MTA" in
	msmtp)
		echo "ℹ️  msmtp has no daemon to stop"
		;;

	nullmailer-source)
		if pgrep -f nullmailer-send >/dev/null 2>&1; then
			pkill -f nullmailer-send
			echo "✅ Nullmailer daemon stopped"
		else
			echo "⚠️  Nullmailer daemon was not running"
		fi
		;;

	nullmailer-package)
		if service nullmailer stop; then
			echo "✅ Nullmailer service stopped"
		else
			echo "⚠️  Failed to stop nullmailer service (may not have been running)"
		fi
		;;

	none)
		echo "ℹ️  No MTA to stop"
		;;
	esac
	;;

status)
	echo "Checking MTA status..."

	case "$MTA" in
	msmtp)
		echo "✅ msmtp is available (version: $(msmtp --version | head -n1))"
		echo "Configuration file: /etc/msmtprc"
		if [ -f /etc/msmtprc ]; then
			echo "Configuration exists and is readable"
		else
			echo "⚠️  Configuration file not found"
		fi

		# Check for active msmtp processes (msmtp doesn't have persistent queue)
		if pgrep -f msmtp >/dev/null 2>&1; then
			MSMTP_PROCESSES=$(pgrep -f msmtp 2>/dev/null | wc -l)
			echo "📧 Active msmtp processes: $MSMTP_PROCESSES"
			pgrep -fl msmtp 2>/dev/null || true
		else
			echo "📧 No active msmtp processes"
		fi

		# Check if using syslog or logfile
		if [ -f /etc/msmtprc ]; then
			if grep -q "^syslog" /etc/msmtprc 2>/dev/null; then
				echo "📝 Logging: syslog (visible with 'docker compose logs')"
			elif grep -q "^logfile" /etc/msmtprc 2>/dev/null; then
				LOGFILE=$(grep "^logfile" /etc/msmtprc 2>/dev/null | awk '{print $2}' || echo "")
				if [ -n "$LOGFILE" ] && [ -f "$LOGFILE" ]; then
					RECENT_ENTRIES=$(tail -n 5 "$LOGFILE" 2>/dev/null | wc -l || echo "0")
					if [ "$RECENT_ENTRIES" -gt 0 ]; then
						echo "Recent log entries (last 5):"
						tail -n 5 "$LOGFILE" 2>/dev/null || true
					fi
				else
					echo "📝 Logfile configured but not found: $LOGFILE"
				fi
			else
				echo "📝 No specific logging configured"
			fi
		fi
		;;

	nullmailer-source)
		if pgrep -f nullmailer-send >/dev/null 2>&1; then
			echo "✅ Nullmailer daemon is running"
			echo "Process info:"
			pgrep -fl nullmailer-send

			# Check mail queue
			if [ -d /var/spool/nullmailer/queue ]; then
				QUEUE_COUNT=$(find /var/spool/nullmailer/queue -maxdepth 1 -type f 2>/dev/null | wc -l)
				echo "📧 Queue: $QUEUE_COUNT messages"
				if [ "$QUEUE_COUNT" -gt 0 ]; then
					echo "Queued messages:"
					ls -la /var/spool/nullmailer/queue/
				fi
			else
				echo "⚠️  Queue directory not found"
			fi
		else
			echo "❌ Nullmailer daemon is not running"
			exit 1
		fi
		;;

	nullmailer-package)
		if service nullmailer status >/dev/null 2>&1; then
			echo "✅ Nullmailer service is running"
			service nullmailer status

			# Check mail queue
			if [ -d /var/spool/nullmailer/queue ]; then
				QUEUE_COUNT=$(find /var/spool/nullmailer/queue -maxdepth 1 -type f 2>/dev/null | wc -l)
				echo "📧 Queue: $QUEUE_COUNT messages"
				if [ "$QUEUE_COUNT" -gt 0 ]; then
					echo "Queued messages:"
					ls -la /var/spool/nullmailer/queue/
				fi
			else
				echo "⚠️  Queue directory not found"
			fi
		else
			echo "❌ Nullmailer service is not running"
			exit 1
		fi
		;;

	none)
		echo "❌ No MTA available"
		echo "Fallback: sendmail will use /bin/cat"
		echo "📧 No queue (emails are immediately processed by fallback)"
		exit 1
		;;
	esac
	;;

restart)
	echo "Restarting MTA..."

	case "$MTA" in
	nullmailer-source | nullmailer-package)
		"$0" stop
		sleep 1
		"$0" start
		;;

	msmtp | none)
		echo "ℹ️  $MTA doesn't require restart"
		;;
	esac
	;;

queue)
	echo "Checking mail queue..."

	case "$MTA" in
	msmtp)
		echo "msmtp doesn't use a persistent queue"
		if pgrep -f msmtp >/dev/null 2>&1; then
			MSMTP_PROCESSES=$(pgrep -f msmtp 2>/dev/null | wc -l)
			echo "📧 Currently sending: $MSMTP_PROCESSES active processes"
			pgrep -fl msmtp 2>/dev/null || true
		else
			echo "📧 No emails currently being sent"
		fi

		# Check if using syslog or logfile
		if [ -f /etc/msmtprc ]; then
			if grep -q "^syslog" /etc/msmtprc 2>/dev/null; then
				echo ""
				echo "📝 Logging: syslog (use 'docker compose logs wordpress' to see recent activity)"
			elif grep -q "^logfile" /etc/msmtprc 2>/dev/null; then
				LOGFILE=$(grep "^logfile" /etc/msmtprc 2>/dev/null | awk '{print $2}' || echo "")
				if [ -n "$LOGFILE" ] && [ -f "$LOGFILE" ]; then
					echo ""
					echo "Recent activity (last 10 lines from log):"
					tail -n 10 "$LOGFILE" 2>/dev/null || true
				else
					echo ""
					echo "📝 Logfile configured but not found: $LOGFILE"
				fi
			fi
		fi
		;;

	nullmailer-source | nullmailer-package)
		if [ -d /var/spool/nullmailer/queue ]; then
			QUEUE_COUNT=$(find /var/spool/nullmailer/queue -maxdepth 1 -type f 2>/dev/null | wc -l)
			echo "📧 Queue: $QUEUE_COUNT messages"

			if [ "$QUEUE_COUNT" -gt 0 ]; then
				echo ""
				echo "Queued messages (detailed):"
				ls -la /var/spool/nullmailer/queue/
				echo ""
				echo "Queue contents:"
				for file in /var/spool/nullmailer/queue/*; do
					if [ -f "$file" ]; then
						echo "--- Message: $(basename "$file") ---"
						head -n 10 "$file" 2>/dev/null | grep -E "^(From|To|Subject|Date):" || echo "No headers found"
						echo ""
					fi
				done
			else
				echo "Queue is empty"
			fi
		else
			echo "❌ Queue directory /var/spool/nullmailer/queue not found"
			exit 1
		fi
		;;

	none)
		echo "📧 No queue - fallback handler processes emails immediately"
		;;
	esac
	;;

test)
	echo "Testing MTA configuration..."

	case "$MTA" in
	msmtp)
		echo "msmtp configuration:"

		echo "Version: $(msmtp --version | head -n1)"
		echo "Configuration file: /etc/msmtprc"

		if [ -f /etc/msmtprc ]; then
			echo "Configuration exists"
			echo "Testing connection..."
			# SMTP has two different recipient concepts:
			# 1. Envelope recipient (SMTP protocol level - where email is delivered)
			# 2. Header recipient (message level - what appears in email headers)
			# Without "To:" header, email clients show recipient as BCC (hidden).
			# Using -t flag tells msmtp to read recipients from headers instead of command line.
			# See: https://www.reddit.com/r/linuxadmin/comments/g43lbg/msmtp_and_ssmtp_commands_takes_my_recipients_as/
			if printf "To: admin@localhost\nSubject: Test message from msmtp\n\nTest message from msmtp at %s\n" "$(date)" | msmtp -t --debug; then
				echo "✅ msmtp test successful"
			else
				echo "⚠️  msmtp test had issues (check debug output above)"
			fi
		else
			echo "❌ Configuration file not found"
			exit 1
		fi
		;;

	nullmailer-source | nullmailer-package)
		echo "Nullmailer configuration:"

		echo "Configuration files:"
		ls -la /etc/nullmailer/ 2>/dev/null || echo "❌ /etc/nullmailer not found"

		echo "Spool directory:"
		ls -la /var/spool/nullmailer/ 2>/dev/null || echo "❌ /var/spool/nullmailer not found"

		echo "Testing email injection..."
		# nullmailer-inject accepts email with headers on stdin
		if printf "To: admin@localhost\nSubject: Test message from nullmailer\n\nTest message from nullmailer at %s\n" "$(date)" | nullmailer-inject -f "test@localhost" "admin@localhost" 2>&1; then
			echo "✅ Email injection test successful"
		else
			echo "❌ Email injection test failed"
			exit 1
		fi
		;;

	none)
		echo "No MTA available - testing fallback handler..."
		echo "Testing sendmail wrapper with /bin/cat fallback:"
		if printf "To: admin@localhost\nSubject: Test message from fallback\n\nTest message from fallback at %s\n" "$(date)" | /usr/sbin/sendmail admin@localhost; then
			echo "✅ Fallback handler test successful (output should appear above)"
		else
			echo "⚠️  Fallback handler test had issues"
		fi
		;;
	esac
	;;

test-sendmail)
	echo "Testing sendmail..."

	if [ ! -f /usr/sbin/sendmail ]; then
		echo "❌ Sendmail not found at /usr/sbin/sendmail"
		exit 1
	fi

	if [ ! -x /usr/sbin/sendmail ]; then
		echo "❌ Sendmail is not executable"
		exit 1
	fi

	echo "✅ Sendmail exists and is executable"

	# Test sendmail MTA detection and argument handling
	echo "Testing sendmail with debug output..."
	if printf "To: admin@localhost\nSubject: Test message from sendmail\n\nTest message from sendmail at %s\n" "$(date)" | /usr/sbin/sendmail -f test@localhost admin@localhost; then
		echo "✅ Sendmail test successful"
		echo "ℹ️  Check above output to see which MTA was selected"
	else
		echo "⚠️  Sendmail test had issues"
	fi
	;;

*)
	echo "Usage: $0 {start|stop|status|restart|queue|test|test-sendmail}"
	echo ""
	echo "Commands:"
	echo "  start         - Start MTA daemon (if applicable)"
	echo "  stop          - Stop MTA daemon (if applicable)"
	echo "  status        - Check MTA status and queue summary"
	echo "  restart       - Restart MTA daemon (if applicable)"
	echo "  queue         - Show detailed mail queue information"
	echo "  test          - Test MTA configuration and send test email"
	echo "  test-sendmail - Test sendmail functionality"
	echo ""
	echo "Supported MTAs: msmtp, nullmailer (package/source), fallback (/bin/cat)"
	exit 1
	;;
esac
