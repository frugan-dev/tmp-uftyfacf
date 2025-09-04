ARG WORDPRESS_TAG=latest
FROM docker.io/frugan/bitnamilegacy-wordpress:${WORDPRESS_TAG}

USER root

ENV DEBIAN_FRONTEND=noninteractive

# Set shell options for better error handling
SHELL ["/bin/bash", "-o", "pipefail", "-c"]

ARG WORDPRESS_MTA=""
ARG WORDPRESS_SMTP_HOST
ARG WORDPRESS_SMTP_PORT_NUMBER
ARG WORDPRESS_SMTP_USER
ARG WORDPRESS_SMTP_PASSWORD
ARG WORDPRESS_SMTP_PROTOCOL

# hadolint ignore=DL3008
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        # required by composer
        git \
        unzip \
        # required by phpstan pro
        xdg-utils \
    && if [[ -n "${WORDPRESS_MTA}" && ! "${WORDPRESS_MTA,,}" =~ ^(no|false|0)$ ]]; then \
        if [ "$WORDPRESS_MTA" = "msmtp" ]; then \
            # Install msmtp
            apt-get install -y --no-install-recommends msmtp msmtp-mta; \
        elif [ "$WORDPRESS_MTA" = "nullmailer" ]; then \
            # Install nullmailer
            #echo "nullmailer nullmailer/defaultdomain string example.com" | debconf-set-selections \
            #echo "nullmailer nullmailer/relayhost string smtp.example.com" | debconf-set-selections \
            #echo "nullmailer nullmailer/relayuser string username" | debconf-set-selections \
            #echo "nullmailer nullmailer/relaypass password" | debconf-set-selections \
            #echo "nullmailer nullmailer/adminaddr string admin@example.com" | debconf-set-selections \
            echo "nullmailer nullmailer/mailname string me" | debconf-set-selections \
            && apt-get install -y --no-install-recommends nullmailer; \
        fi; \
    fi \
    && rm -rf /var/lib/apt/lists/*

COPY build/docker/mta-manager.sh /usr/local/bin/mta-manager.sh
COPY build/docker/msmtprc.dist /tmp/msmtprc.dist

# Method #1
COPY build/docker/sendmail-wrapper.sh /usr/local/bin/sendmail-wrapper.sh

RUN chmod +x /usr/local/bin/mta-manager.sh /usr/local/bin/sendmail-wrapper.sh \
    && ln -sf /usr/local/bin/sendmail-wrapper.sh /usr/sbin/sendmail

# Configure MTA
RUN if [[ -n "${WORDPRESS_MTA}" && ! "${WORDPRESS_MTA,,}" =~ ^(no|false|0)$ ]]; then \
        if [ "$WORDPRESS_MTA" = "msmtp" ]; then \
            # Configure msmtp
            mkdir -p /etc/msmtp \
            && cp /tmp/msmtprc.dist /etc/msmtprc \
            && sed -i "s/{{WORDPRESS_SMTP_HOST}}/${WORDPRESS_SMTP_HOST}/g" /etc/msmtprc \
            && sed -i "s/{{WORDPRESS_SMTP_PORT_NUMBER}}/${WORDPRESS_SMTP_PORT_NUMBER}/g" /etc/msmtprc \
            && sed -i "s/{{WORDPRESS_SMTP_USER}}/${WORDPRESS_SMTP_USER}/g" /etc/msmtprc \
            && sed -i "s/{{WORDPRESS_SMTP_PASSWORD}}/${WORDPRESS_SMTP_PASSWORD}/g" /etc/msmtprc \
            # Ensure msmtprc is readable by all users
            # Note: msmtp requires 600 permissions, but we set 644 to allow user 1001 to read it
            && chmod 644 /etc/msmtprc; \
        elif [ "$WORDPRESS_MTA" = "nullmailer" ]; then \
            # Configure nullmailer
            echo "${WORDPRESS_SMTP_HOST} smtp \
                --port=${WORDPRESS_SMTP_PORT_NUMBER} \
                --user=${WORDPRESS_SMTP_USER} \
                --pass=${WORDPRESS_SMTP_PASSWORD} \
                # https://manpages.debian.org/experimental/nullmailer/remotes.5#remotes
                # `tls` option automatically switch the default port to 465
                #--${WORDPRESS_SMTP_PROTOCOL} \
                --insecure" > /etc/nullmailer/remotes; \
        fi; \
    fi \
    && rm -f /tmp/msmtprc.dist

RUN { \
        # Available in bitnami/wordpress, but not actived
        echo 'extension = apcu'; \
        # Superfluous as bitnami/wordpress uses mod_php, so the .user.ini file doesn't work
        echo 'user_ini.cache_ttl = 0'; \
        #https://docs.bitnami.com/aws/infrastructure/lamp/administration/disable-cache/
        echo 'opcache.enable = 0'; \
        # Method #2
        #echo 'sendmail_path = "/usr/bin/msmtp -f noreply@localhost"'; \
        #echo 'sendmail_path = "/usr/bin/nullmailer-inject -f noreply@localhost"'; \
    } >> /opt/bitnami/php/etc/php.ini

USER 1001
